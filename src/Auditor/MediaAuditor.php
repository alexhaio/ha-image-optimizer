<?php
/**
 * Media Auditor - Detects orphan, unused, and attached inactive media.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Auditor;

use ha\ImageOptimizer\Common\Settings;
use ha\ImageOptimizer\Media\BackupManager;
use ha\ImageOptimizer\Engine\SizeGuard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MediaAuditor
 */
class MediaAuditor {

	/**
	 * Option key for caching audit results.
	 */
	public const OPTION_RESULTS = 'ha_io_audit_results';

	/**
	 * Status constants.
	 */
	public const STATUS_USED              = 'used';
	public const STATUS_ATTACHED_UNUSED   = 'attached_unused';
	public const STATUS_UNATTACHED_UNUSED = 'unattached_unused';

	/**
	 * In-memory cache for active theme file contents.
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $theme_files_cache = null;

	/**
	 * In-memory cache for custom third-party plugin tables (Smart Slider, RevSlider, LayerSlider).
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $custom_tables_cache = null;

	/**
	 * In-memory & transient cached schema of custom third-party database tables.
	 *
	 * @var array<string, array{columns: array<string>, label: string, url_template: string, id_col: string, title_col: string}>|null
	 */
	private static ?array $generic_custom_tables_cache = null;

	/**
	 * Get list of all eligible media attachment IDs to audit.
	 *
	 * @param bool $skip_scanned Whether to skip attachment IDs that already have cached audit results. Default false.
	 * @return array<int>
	 */
	public static function get_audit_queue( bool $skip_scanned = false ): array {
		global $wpdb;

		$sql = "SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'attachment'
			AND post_mime_type LIKE 'image/%'
			AND post_status != 'trash'
			ORDER BY ID DESC";

		$results = $wpdb->get_col( $sql );
		$ids     = array_map( 'absint', (array) $results );

		if ( ! $skip_scanned ) {
			return $ids;
		}

		$cached = self::get_cached_results();
		if ( empty( $cached['items'] ) || ! is_array( $cached['items'] ) ) {
			return $ids;
		}

		// Prune any cached entries for attachments that no longer exist or are trashed
		$existing_map  = array_flip( $ids );
		$needs_pruning = false;
		foreach ( array_keys( $cached['items'] ) as $cached_id ) {
			$cached_id = (int) $cached_id;
			if ( ! isset( $existing_map[ $cached_id ] ) ) {
				unset( $cached['items'][ $cached_id ] );
				$needs_pruning = true;
			}
		}
		if ( $needs_pruning ) {
			self::recalculate_and_save( $cached );
		}

		$scanned_map = array_flip( array_map( 'absint', array_keys( $cached['items'] ) ) );

		return array_values(
			array_filter(
				$ids,
				static fn( int $id ): bool => ! isset( $scanned_map[ $id ] )
			)
		);
	}

	/**
	 * Audit a single attachment ID.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, mixed>
	 */
	public static function audit_attachment( int $attachment_id ): array {
		global $wpdb;

		$attachment_id = absint( $attachment_id );
		$post          = get_post( $attachment_id );

		if ( ! $post || 'attachment' !== $post->post_type ) {
			return [
				'id'     => $attachment_id,
				'error'  => __( 'Attachment nicht gefunden.', 'ha-image-optimizer' ),
				'status' => self::STATUS_UNATTACHED_UNUSED,
			];
		}

		$file      = get_attached_file( $attachment_id );
		$filename  = $file ? wp_basename( $file ) : ( 'ID ' . $attachment_id );
		$parent_id = (int) $post->post_parent;
		$parent_title = '';

		if ( $parent_id > 0 ) {
			$parent_post = get_post( $parent_id );
			$parent_title = $parent_post ? get_the_title( $parent_id ) : '';
		}

		$thumb_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
		if ( ! $thumb_url && function_exists( 'wp_get_attachment_url' ) ) {
			$thumb_url = wp_get_attachment_url( $attachment_id );
		}

		$disk_bytes = self::calculate_disk_bytes( $attachment_id, $file );
		$references = [];

		$upload_dir = wp_upload_dir();
		$rel_file   = '';
		if ( $file && ! empty( $upload_dir['basedir'] ) ) {
			$rel_file = ltrim( str_replace( wp_normalize_path( $upload_dir['basedir'] ), '', wp_normalize_path( $file ) ), '/' );
		}

		// -------------------------------------------------------------
		// 1. Featured Image (_thumbnail_id)
		// -------------------------------------------------------------
		$featured_sql = $wpdb->prepare(
			"SELECT p.ID, p.post_title, p.post_type FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON (pm.post_id = p.ID)
			WHERE pm.meta_key = '_thumbnail_id'
			AND pm.meta_value = %d
			AND p.post_status != 'trash'
			LIMIT 5",
			$attachment_id
		);
		$featured_posts = $wpdb->get_results( $featured_sql );

		if ( ! empty( $featured_posts ) ) {
			foreach ( $featured_posts as $fp ) {
				$title   = ! empty( $fp->post_title ) ? $fp->post_title : ( 'ID ' . ( $fp->ID ?? 0 ) );
				$post_id = (int) ( $fp->ID ?? 0 );
				$references[] = [
					'label' => sprintf(
						/* translators: 1: post title, 2: post ID, 3: post type */
						__( 'Beitragsbild in "%1$s" (#%2$d, %3$s)', 'ha-image-optimizer' ),
						$title,
						$post_id,
						$fp->post_type ?? 'post'
					),
					'url'   => self::safe_admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
				];
			}
		}

		// -------------------------------------------------------------
		// 2. WooCommerce Product Gallery (_product_image_gallery)
		// -------------------------------------------------------------
		$id_str      = (string) $attachment_id;
		$gallery_sql = $wpdb->prepare(
			"SELECT p.ID, p.post_title FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON (pm.post_id = p.ID)
			WHERE pm.meta_key = '_product_image_gallery'
			AND (
				pm.meta_value = %s
				OR pm.meta_value LIKE %s
				OR pm.meta_value LIKE %s
				OR pm.meta_value LIKE %s
			)
			AND p.post_status != 'trash'
			LIMIT 5",
			$id_str,
			$id_str . ',%',
			'%,' . $id_str,
			'%,' . $id_str . ',%'
		);
		$gallery_posts = $wpdb->get_results( $gallery_sql );

		if ( ! empty( $gallery_posts ) ) {
			foreach ( $gallery_posts as $gp ) {
				$title   = ! empty( $gp->post_title ) ? $gp->post_title : ( 'ID ' . ( $gp->ID ?? 0 ) );
				$post_id = (int) ( $gp->ID ?? 0 );
				$references[] = [
					'label' => sprintf(
						/* translators: 1: product title, 2: product ID */
						__( 'WooCommerce Produktgalerie in "%1$s" (#%2$d)', 'ha-image-optimizer' ),
						$title,
						$post_id
					),
					'url'   => self::safe_admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
				];
			}
		}

		// -------------------------------------------------------------
		// 3. Site Identity & Theme Options (Icon, Logo, Header, Background)
		// -------------------------------------------------------------
		$site_icon = (int) get_option( 'site_icon', 0 );
		if ( $site_icon === $attachment_id ) {
			$references[] = [
				'label' => __( 'Website-Icon (Browser Favicon)', 'ha-image-optimizer' ),
				'url'   => self::safe_admin_url( 'customize.php?autofocus[section]=title_tagline' ),
			];
		}

		$custom_logo = (int) get_theme_mod( 'custom_logo', 0 );
		if ( $custom_logo === $attachment_id ) {
			$references[] = [
				'label' => __( 'Offizielles Website-Logo (Theme Header)', 'ha-image-optimizer' ),
				'url'   => self::safe_admin_url( 'customize.php?autofocus[control]=custom_logo' ),
			];
		}

		$site_logo = (int) get_option( 'site_logo', 0 );
		if ( $site_logo === $attachment_id && $custom_logo !== $attachment_id ) {
			$references[] = [
				'label' => __( 'Website-Logo (FSE Block Theme)', 'ha-image-optimizer' ),
				'url'   => self::safe_admin_url( 'site-editor.php' ),
			];
		}

		// Header Image
		$header_img_url  = (string) get_theme_mod( 'header_image', '' );
		$header_img_data = get_theme_mod( 'header_image_data' );
		$is_header_match = false;
		if ( is_object( $header_img_data ) && isset( $header_img_data->attachment_id ) && (int) $header_img_data->attachment_id === $attachment_id ) {
			$is_header_match = true;
		} elseif ( is_array( $header_img_data ) && isset( $header_img_data['attachment_id'] ) && (int) $header_img_data['attachment_id'] === $attachment_id ) {
			$is_header_match = true;
		} elseif ( ! empty( $rel_file ) && str_contains( $header_img_url, $rel_file ) ) {
			$is_header_match = true;
		}
		if ( $is_header_match ) {
			$references[] = [
				'label' => __( 'Theme Header-Bild (Customizer)', 'ha-image-optimizer' ),
				'url'   => self::safe_admin_url( 'customize.php?autofocus[control]=header_image' ),
			];
		}

		// Background Image
		$bg_image_url = (string) get_theme_mod( 'background_image', '' );
		if ( ! empty( $rel_file ) && str_contains( $bg_image_url, $rel_file ) ) {
			$references[] = [
				'label' => __( 'Theme Hintergrundbild (Customizer)', 'ha-image-optimizer' ),
				'url'   => self::safe_admin_url( 'customize.php?autofocus[control]=background_image' ),
			];
		}

		// -------------------------------------------------------------
		// 4. Taxonomy Meta (Term Thumbnails, e.g. WooCommerce Categories)
		// -------------------------------------------------------------
		if ( ! empty( $wpdb->termmeta ) ) {
			$term_sql = $wpdb->prepare(
				"SELECT t.term_id, t.name, tt.taxonomy FROM {$wpdb->termmeta} tm
				INNER JOIN {$wpdb->terms} t ON (tm.term_id = t.term_id)
				INNER JOIN {$wpdb->term_taxonomy} tt ON (t.term_id = tt.term_id)
				WHERE tm.meta_key IN ('thumbnail_id', 'product_cat_thumbnail_id')
				AND tm.meta_value = %d
				LIMIT 5",
				$attachment_id
			);
			$term_rows = $wpdb->get_results( $term_sql );

			if ( ! empty( $term_rows ) ) {
				foreach ( $term_rows as $tr ) {
					$tax     = $tr->taxonomy ?? 'category';
					$term_id = (int) ( $tr->term_id ?? 0 );
					$references[] = [
						'label' => sprintf(
							/* translators: 1: category name, 2: taxonomy name */
							__( 'Kategorie-Vorschaubild in "%1$s" (%2$s)', 'ha-image-optimizer' ),
							$tr->name ?? 'Kategorie',
							$tax
						),
						'url'   => self::safe_admin_url( 'term.php?taxonomy=' . $tax . '&tag_ID=' . $term_id ),
					];
				}
			}
		}

		// -------------------------------------------------------------
		// 5. Content References (Gutenberg blocks, HTML <img>, URLs, Shortcodes)
		// -------------------------------------------------------------
		$content_patterns = [
			'wp-image-' . $attachment_id,
			'"id":' . $attachment_id,
			'"id": ' . $attachment_id,
			'image="' . $attachment_id . '"',
			'images="' . $attachment_id . '"',
		];

		if ( ! empty( $rel_file ) ) {
			$content_patterns[] = $rel_file;
		}

		if ( strlen( $filename ) >= 6 ) {
			$content_patterns[] = $filename;
		}

		// Build safe LIKE clauses for content searching
		$like_clauses = [];
		$like_params  = [];
		foreach ( array_unique( $content_patterns ) as $pattern ) {
			$like_clauses[] = 'p.post_content LIKE %s';
			$like_params[]  = '%' . $wpdb->esc_like( $pattern ) . '%';
		}

		$where_content = implode( ' OR ', $like_clauses );
		$content_sql   = $wpdb->prepare(
			"SELECT p.ID, p.post_title, p.post_type FROM {$wpdb->posts} p
			WHERE p.post_type NOT IN ('attachment', 'revision')
			AND p.post_status != 'trash'
			AND ({$where_content})
			LIMIT 5",
			...$like_params
		);

		$content_posts = $wpdb->get_results( $content_sql );
		if ( ! empty( $content_posts ) ) {
			foreach ( $content_posts as $cp ) {
				$title   = ! empty( $cp->post_title ) ? $cp->post_title : ( 'ID ' . ( $cp->ID ?? 0 ) );
				$post_id = (int) ( $cp->ID ?? 0 );
				$p_type  = $cp->post_type ?? 'post';

				$type_label = $p_type;
				if ( 'wp_template' === $p_type || 'wp_template_part' === $p_type ) {
					$type_label = 'Theme-Template (FSE)';
				}

				$references[] = [
					'label' => sprintf(
						/* translators: 1: post title, 2: post ID, 3: post type */
						__( 'Eingebunden im Inhalt von "%1$s" (#%2$d, %3$s)', 'ha-image-optimizer' ),
						$title,
						$post_id,
						$type_label
					),
					'url'   => self::safe_admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
				];
			}
		}

		// -------------------------------------------------------------
		// 6. Page Builder Postmeta (Elementor, Beaver Builder, Bricks, Oxygen)
		// -------------------------------------------------------------
		$builder_like_clauses = [
			'pm.meta_value LIKE %s',
			'pm.meta_value LIKE %s',
			'pm.meta_value LIKE %s',
		];
		$builder_like_params = [
			'%"id":' . $attachment_id . '%',
			'%"id": ' . $attachment_id . '%',
			'%"id":"' . $attachment_id . '"%',
		];

		if ( ! empty( $rel_file ) ) {
			$builder_like_clauses[] = 'pm.meta_value LIKE %s';
			$builder_like_params[]  = '%' . $wpdb->esc_like( $rel_file ) . '%';
		}

		$where_builder = implode( ' OR ', $builder_like_clauses );
		$builder_sql   = $wpdb->prepare(
			"SELECT p.ID, p.post_title, p.post_type, pm.meta_key FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON (pm.post_id = p.ID)
			WHERE pm.meta_key IN ('_elementor_data', '_elementor_css', '_fl_builder_data', '_bricks_page_content_2', 'ct_builder_json')
			AND ({$where_builder})
			AND p.post_status != 'trash'
			LIMIT 5",
			...$builder_like_params
		);
		$builder_posts = $wpdb->get_results( $builder_sql );

		if ( ! empty( $builder_posts ) ) {
			foreach ( $builder_posts as $bp ) {
				$title   = ! empty( $bp->post_title ) ? $bp->post_title : ( 'ID ' . ( $bp->ID ?? 0 ) );
				$post_id = (int) ( $bp->ID ?? 0 );

				$builder_name = 'Page Builder';
				$meta_k       = (string) ( $bp->meta_key ?? '' );
				if ( str_contains( $meta_k, 'elementor' ) ) {
					$builder_name = 'Elementor Layout';
				} elseif ( str_contains( $meta_k, 'fl_builder' ) ) {
					$builder_name = 'Beaver Builder';
				} elseif ( str_contains( $meta_k, 'bricks' ) ) {
					$builder_name = 'Bricks Builder';
				} elseif ( str_contains( $meta_k, 'ct_builder' ) ) {
					$builder_name = 'Oxygen Builder';
				}

				$references[] = [
					'label' => sprintf(
						/* translators: 1: builder name, 2: post title, 3: post ID */
						__( '%1$s in "%2$s" (#%3$d)', 'ha-image-optimizer' ),
						$builder_name,
						$title,
						$post_id
					),
					'url'   => self::safe_admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
				];
			}
		}

		// -------------------------------------------------------------
		// 7. Custom Postmeta & ACF (Advanced Custom Fields)
		// -------------------------------------------------------------
		$acf_sql = $wpdb->prepare(
			"SELECT p.ID, p.post_title, p.post_type, pm.meta_key FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON (pm.post_id = p.ID)
			WHERE pm.meta_key NOT LIKE '\_%'
			AND pm.meta_key NOT IN ('_wp_attached_file', '_wp_attachment_metadata')
			AND (
				pm.meta_value = %s
				OR pm.meta_value LIKE %s
			)
			AND p.post_type NOT IN ('attachment', 'revision')
			AND p.post_status != 'trash'
			LIMIT 3",
			(string) $attachment_id,
			'%i:' . $attachment_id . ';%'
		);
		$acf_posts = $wpdb->get_results( $acf_sql );

		if ( ! empty( $acf_posts ) ) {
			foreach ( $acf_posts as $ap ) {
				$title   = ! empty( $ap->post_title ) ? $ap->post_title : ( 'ID ' . ( $ap->ID ?? 0 ) );
				$post_id = (int) ( $ap->ID ?? 0 );
				$references[] = [
					'label' => sprintf(
						/* translators: 1: field name, 2: post title, 3: post ID, 4: post type */
						__( 'Benutzerdefiniertes Feld / ACF ("%1$s") in "%2$s" (#%3$d, %4$s)', 'ha-image-optimizer' ),
						$ap->meta_key ?? 'Custom Field',
						$title,
						$post_id,
						$ap->post_type ?? 'post'
					),
					'url'   => self::safe_admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
				];
			}
		}

		// -------------------------------------------------------------
		// 8. Generic Postmeta Deep Scan (Custom Fields, Meta Boxes, Theme Fields)
		// -------------------------------------------------------------
		$pm_clauses = [
			'pm.meta_value LIKE %s',
			'pm.meta_value LIKE %s',
			'pm.meta_value LIKE %s',
		];
		$pm_params  = [
			'%i:' . $attachment_id . ';%',
			'%"id":' . $attachment_id . '%',
			'%"imageId":' . $attachment_id . '%',
		];

		if ( ! empty( $rel_file ) ) {
			$pm_clauses[] = 'pm.meta_value LIKE %s';
			$pm_params[]  = '%' . $wpdb->esc_like( $rel_file ) . '%';
		}

		$where_pm = implode( ' OR ', $pm_clauses );
		$other_postmeta = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_type, pm.meta_key
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON (pm.post_id = p.ID)
				WHERE pm.post_id != %d
				AND p.post_type NOT IN ('attachment', 'revision')
				AND pm.meta_key NOT LIKE '_wp_%'
				AND pm.meta_key NOT LIKE '_ha_io_%'
				AND pm.meta_key NOT IN ('_thumbnail_id', '_product_image_gallery', '_elementor_data', '_elementor_page_settings', '_elementor_css')
				AND pm.meta_key NOT LIKE '_acf_%'
				AND p.post_status != 'trash'
				AND ({$where_pm})
				LIMIT 3",
				$attachment_id,
				...$pm_params
			)
		);

		if ( ! empty( $other_postmeta ) ) {
			foreach ( $other_postmeta as $opm ) {
				$title   = ! empty( $opm->post_title ) ? $opm->post_title : ( 'ID ' . ( $opm->ID ?? 0 ) );
				$post_id = (int) ( $opm->ID ?? 0 );
				$references[] = [
					'label' => sprintf(
						/* translators: 1: meta key, 2: post title, 3: post ID */
						__( 'Beitragsfeld ("%1$s") in "%2$s" (#%3$d)', 'ha-image-optimizer' ),
						$opm->meta_key,
						$title,
						$post_id
					),
					'url'   => self::safe_admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
				];
			}
		}

		// -------------------------------------------------------------
		// 9. Active Theme Files Scan (style.css, templates, theme.json)
		// -------------------------------------------------------------
		$theme_file = self::search_active_theme_files( $filename, $rel_file );
		if ( $theme_file ) {
			$references[] = [
				'label' => sprintf(
					/* translators: %s: theme file name */
					__( 'Verwendet in Theme-Datei: "%s"', 'ha-image-optimizer' ),
					$theme_file
				),
				'url'   => self::safe_admin_url( 'theme-editor.php' ),
			];
		}

		// -------------------------------------------------------------
		// 10. Slider Plugins (Smart Slider 3, Slider Revolution, LayerSlider)
		// -------------------------------------------------------------
		// Smart Slider 3 (Nextend)
		$table_smart_slides = self::get_custom_table( '%smartslider%slides' );
		if ( $table_smart_slides ) {
			$table_smart_sliders = self::get_custom_table( '%smartslider%sliders' );

			$ss_clauses = [];
			$ss_params  = [];

			if ( ! empty( $rel_file ) ) {
				$ss_clauses[] = 's.params LIKE %s';
				$ss_params[]  = '%' . $wpdb->esc_like( $rel_file ) . '%';

				$ss_clauses[] = 's.thumbnail LIKE %s';
				$ss_params[]  = '%' . $wpdb->esc_like( $rel_file ) . '%';
			}

			if ( strlen( $filename ) >= 5 ) {
				$ss_clauses[] = 's.params LIKE %s';
				$ss_params[]  = '%' . $wpdb->esc_like( $filename ) . '%';

				$ss_clauses[] = 's.thumbnail LIKE %s';
				$ss_params[]  = '%' . $wpdb->esc_like( $filename ) . '%';
			}

			$id_patterns = [
				'%"imageId":' . $attachment_id . '%',
				'%"image_id":' . $attachment_id . '%',
				'%"imageId":"' . $attachment_id . '"%',
				'%"image_id":"' . $attachment_id . '"%',
				'%"id":' . $attachment_id . '%',
				'%"id":"' . $attachment_id . '"%',
			];

			foreach ( $id_patterns as $id_pat ) {
				$ss_clauses[] = 's.params LIKE %s';
				$ss_params[]  = $id_pat;
			}

			if ( ! empty( $ss_clauses ) ) {
				$where_ss = implode( ' OR ', $ss_clauses );

				if ( $table_smart_sliders ) {
					$ss_sql = $wpdb->prepare(
						"SELECT s.id, s.slider, s.title, sl.title AS slider_title
						FROM {$table_smart_slides} s
						LEFT JOIN {$table_smart_sliders} sl ON (s.slider = sl.id)
						WHERE ({$where_ss})
						LIMIT 5",
						...$ss_params
					);
				} else {
					$ss_sql = $wpdb->prepare(
						"SELECT s.id, s.slider, s.title, '' AS slider_title
						FROM {$table_smart_slides} s
						WHERE ({$where_ss})
						LIMIT 5",
						...$ss_params
					);
				}

				$ss_rows = $wpdb->get_results( $ss_sql );
				if ( ! empty( $ss_rows ) ) {
					foreach ( $ss_rows as $sr ) {
						$slider_name = ! empty( $sr->slider_title ) ? $sr->slider_title : ( 'Slider #' . ( $sr->slider ?? 0 ) );
						$slide_name  = ! empty( $sr->title ) ? $sr->title : ( 'Slide #' . ( $sr->id ?? 0 ) );
						$slider_id   = (int) ( $sr->slider ?? 0 );
						$slide_id    = (int) ( $sr->id ?? 0 );

						$references[] = [
							'label' => sprintf(
								/* translators: 1: slider title, 2: slide title, 3: slide ID */
								__( 'Smart Slider 3: "%1$s" (Slide: "%2$s", #%3$d)', 'ha-image-optimizer' ),
								$slider_name,
								$slide_name,
								$slide_id
							),
							'url'   => self::safe_admin_url( 'admin.php?page=smartslider3&sliderid=' . $slider_id . '&slideid=' . $slide_id ),
						];
					}
				}
			}

			// Slider-level global settings / background
			if ( $table_smart_sliders && ( ! empty( $rel_file ) || strlen( $filename ) >= 5 ) ) {
				$ssl_clauses = [];
				$ssl_params  = [];
				if ( ! empty( $rel_file ) ) {
					$ssl_clauses[] = 'params LIKE %s';
					$ssl_params[]  = '%' . $wpdb->esc_like( $rel_file ) . '%';
				}
				if ( strlen( $filename ) >= 5 ) {
					$ssl_clauses[] = 'params LIKE %s';
					$ssl_params[]  = '%' . $wpdb->esc_like( $filename ) . '%';
				}
				if ( ! empty( $ssl_clauses ) ) {
					$where_ssl = implode( ' OR ', $ssl_clauses );
					$ssl_sql   = $wpdb->prepare(
						"SELECT id, title FROM {$table_smart_sliders}
						WHERE ({$where_ssl})
						LIMIT 3",
						...$ssl_params
					);
					$ssl_rows = $wpdb->get_results( $ssl_sql );
					if ( ! empty( $ssl_rows ) ) {
						foreach ( $ssl_rows as $slr ) {
							$slider_title = ! empty( $slr->title ) ? $slr->title : ( 'Slider #' . ( $slr->id ?? 0 ) );
							$slider_id    = (int) ( $slr->id ?? 0 );
							$references[] = [
								'label' => sprintf(
									/* translators: 1: slider title, 2: slider ID */
									__( 'Smart Slider 3 (Projekt-Hintergrund): "%1$s" (#%2$d)', 'ha-image-optimizer' ),
									$slider_title,
									$slider_id
								),
								'url'   => self::safe_admin_url( 'admin.php?page=smartslider3&sliderid=' . $slider_id ),
							];
						}
					}
				}
			}
		}

		// Slider Revolution
		$table_rev_slides = self::get_custom_table( '%revslider%slides' );
		if ( $table_rev_slides ) {
			$table_rev_sliders = self::get_custom_table( '%revslider%sliders' );
			$rev_clauses = [];
			$rev_params  = [];

			if ( ! empty( $rel_file ) ) {
				$rev_clauses[] = 's.params LIKE %s';
				$rev_params[]  = '%' . $wpdb->esc_like( $rel_file ) . '%';
				$rev_clauses[] = 's.layers LIKE %s';
				$rev_params[]  = '%' . $wpdb->esc_like( $rel_file ) . '%';
			}

			if ( strlen( $filename ) >= 5 ) {
				$rev_clauses[] = 's.params LIKE %s';
				$rev_params[]  = '%' . $wpdb->esc_like( $filename ) . '%';
				$rev_clauses[] = 's.layers LIKE %s';
				$rev_params[]  = '%' . $wpdb->esc_like( $filename ) . '%';
			}

			$rev_clauses[] = 's.params LIKE %s';
			$rev_params[]  = '%"image_id":' . $attachment_id . '%';

			if ( ! empty( $rev_clauses ) ) {
				$where_rev = implode( ' OR ', $rev_clauses );
				if ( $table_rev_sliders ) {
					$rev_sql = $wpdb->prepare(
						"SELECT s.id, s.slider_id, sl.title AS slider_title
						FROM {$table_rev_slides} s
						LEFT JOIN {$table_rev_sliders} sl ON (s.slider_id = sl.id)
						WHERE ({$where_rev})
						LIMIT 5",
						...$rev_params
					);
				} else {
					$rev_sql = $wpdb->prepare(
						"SELECT s.id, s.slider_id, '' AS slider_title
						FROM {$table_rev_slides} s
						WHERE ({$where_rev})
						LIMIT 5",
						...$rev_params
					);
				}

				$rev_rows = $wpdb->get_results( $rev_sql );
				if ( ! empty( $rev_rows ) ) {
					foreach ( $rev_rows as $rr ) {
						$slider_name = ! empty( $rr->slider_title ) ? $rr->slider_title : ( 'Slider #' . ( $rr->slider_id ?? 0 ) );
						$slide_id    = (int) ( $rr->id ?? 0 );
						$references[] = [
							'label' => sprintf(
								/* translators: 1: slider title, 2: slide ID */
								__( 'Slider Revolution: "%1$s" (Slide #%2$d)', 'ha-image-optimizer' ),
								$slider_name,
								$slide_id
							),
							'url'   => self::safe_admin_url( 'admin.php?page=revslider&view=slide&id=' . $slide_id ),
						];
					}
				}
			}
		}

		// LayerSlider
		$table_layerslider = self::get_custom_table( '%layerslider%' );
		if ( $table_layerslider ) {
			$ls_clauses = [];
			$ls_params  = [];

			if ( ! empty( $rel_file ) ) {
				$ls_clauses[] = 'data LIKE %s';
				$ls_params[]  = '%' . $wpdb->esc_like( $rel_file ) . '%';
			}
			if ( strlen( $filename ) >= 5 ) {
				$ls_clauses[] = 'data LIKE %s';
				$ls_params[]  = '%' . $wpdb->esc_like( $filename ) . '%';
			}
			$ls_clauses[] = 'data LIKE %s';
			$ls_params[]  = '%"imageId":' . $attachment_id . '%';

			if ( ! empty( $ls_clauses ) ) {
				$where_ls = implode( ' OR ', $ls_clauses );
				$ls_sql   = $wpdb->prepare(
					"SELECT id, name FROM {$table_layerslider}
					WHERE ({$where_ls})
					LIMIT 3",
					...$ls_params
				);
				$ls_rows = $wpdb->get_results( $ls_sql );
				if ( ! empty( $ls_rows ) ) {
					foreach ( $ls_rows as $lsr ) {
						$name  = ! empty( $lsr->name ) ? $lsr->name : ( 'Slider #' . ( $lsr->id ?? 0 ) );
						$ls_id = (int) ( $lsr->id ?? 0 );
						$references[] = [
							'label' => sprintf(
								/* translators: 1: slider name, 2: slider ID */
								__( 'LayerSlider: "%1$s" (#%2$d)', 'ha-image-optimizer' ),
								$name,
								$ls_id
							),
							'url'   => self::safe_admin_url( 'admin.php?page=layerslider&action=edit&id=' . $ls_id ),
						];
					}
				}
			}
		}

		// -------------------------------------------------------------
		// 11. Generic Third-Party Database Tables Scanner
		// -------------------------------------------------------------
		if ( Settings::get( 'auditor_generic_db_scan', true ) ) {
			$custom_tables = self::get_generic_custom_tables();
			$skip_tables   = [
				$wpdb->prefix . 'nextend2_smartslider3_slides',
				$wpdb->prefix . 'nextend_smartslider3_slides',
				$wpdb->prefix . 'nextend2_smartslider3_sliders',
				$wpdb->prefix . 'nextend_smartslider3_sliders',
				$wpdb->prefix . 'revslider_slides',
				$wpdb->prefix . 'revslider_sliders',
				$wpdb->prefix . 'layerslider',
			];

			foreach ( $custom_tables as $table_name => $tbl_info ) {
				if ( in_array( $table_name, $skip_tables, true ) ) {
					continue;
				}

				$cols = $tbl_info['columns'] ?? [];
				if ( empty( $cols ) ) {
					continue;
				}

				$tbl_clauses = [];
				$tbl_params  = [];

				foreach ( $cols as $col ) {
					$tbl_clauses[] = "`{$col}` LIKE %s";
					$tbl_params[]  = '%i:' . $attachment_id . ';%';

					$tbl_clauses[] = "`{$col}` LIKE %s";
					$tbl_params[]  = '%"id":' . $attachment_id . '%';

					$tbl_clauses[] = "`{$col}` LIKE %s";
					$tbl_params[]  = '%"id":"' . $attachment_id . '"%';

					$tbl_clauses[] = "`{$col}` LIKE %s";
					$tbl_params[]  = '%"imageId":' . $attachment_id . '%';

					$tbl_clauses[] = "`{$col}` LIKE %s";
					$tbl_params[]  = '%"image_id":' . $attachment_id . '%';

					if ( ! empty( $rel_file ) ) {
						$tbl_clauses[] = "`{$col}` LIKE %s";
						$tbl_params[]  = '%' . $wpdb->esc_like( $rel_file ) . '%';
					}
				}

				if ( empty( $tbl_clauses ) ) {
					continue;
				}

				$where_tbl = implode( ' OR ', $tbl_clauses );
				$tbl_sql   = $wpdb->prepare(
					"SELECT * FROM `{$table_name}` WHERE ({$where_tbl}) LIMIT 3",
					...$tbl_params
				);
				$tbl_rows  = $wpdb->get_results( $tbl_sql );

				if ( ! empty( $tbl_rows ) ) {
					$id_col    = $tbl_info['id_col'] ?? '';
					$title_col = $tbl_info['title_col'] ?? '';
					$url_tpl   = $tbl_info['url_template'] ?? '';
					$base_lbl  = $tbl_info['label'] ?? ( 'Tabelle "' . str_replace( $wpdb->prefix, '', $table_name ) . '"' );

					foreach ( $tbl_rows as $trow ) {
						$row_id    = '';
						$row_title = '';

						if ( ! empty( $id_col ) && isset( $trow->$id_col ) ) {
							$row_id = (string) $trow->$id_col;
						} elseif ( isset( $trow->id ) ) {
							$row_id = (string) $trow->id;
						} elseif ( isset( $trow->ID ) ) {
							$row_id = (string) $trow->ID;
						}

						if ( ! empty( $title_col ) && isset( $trow->$title_col ) ) {
							$row_title = (string) $trow->$title_col;
						} elseif ( isset( $trow->title ) ) {
							$row_title = (string) $trow->title;
						} elseif ( isset( $trow->name ) ) {
							$row_title = (string) $trow->name;
						}

						if ( ! empty( $row_title ) && ! empty( $row_id ) ) {
							$label = sprintf(
								/* translators: 1: table label, 2: row title, 3: row ID */
								__( '%1$s: "%2$s" (#%3$s)', 'ha-image-optimizer' ),
								$base_lbl,
								$row_title,
								$row_id
							);
						} elseif ( ! empty( $row_title ) ) {
							$label = sprintf(
								/* translators: 1: table label, 2: row title */
								__( '%1$s: "%2$s"', 'ha-image-optimizer' ),
								$base_lbl,
								$row_title
							);
						} elseif ( ! empty( $row_id ) ) {
							$label = sprintf(
								/* translators: 1: table label, 2: row ID */
								__( '%1$s: Eintrag #%2$s', 'ha-image-optimizer' ),
								$base_lbl,
								$row_id
							);
						} else {
							$label = sprintf(
								/* translators: 1: table label */
								__( 'Verwendet in %1$s', 'ha-image-optimizer' ),
								$base_lbl
							);
						}

						$url = '';
						if ( ! empty( $url_tpl ) && ! empty( $row_id ) ) {
							$url = self::safe_admin_url( str_replace( '%id%', $row_id, $url_tpl ) );
						}

						$references[] = [
							'label' => $label,
							'url'   => $url,
						];
					}
				}
			}
		}

		// -------------------------------------------------------------
		// 12. WordPress Widgets & Options (wp_options)
		// -------------------------------------------------------------
		if ( ! empty( $wpdb->options ) ) {
			$opt_clauses = [
				'option_value LIKE %s',
				'option_value LIKE %s',
				'option_value LIKE %s',
			];
			$opt_params  = [
				'%i:' . $attachment_id . ';%',
				'%"id":' . $attachment_id . '%',
				'%"imageId":' . $attachment_id . '%',
			];

			if ( ! empty( $rel_file ) ) {
				$opt_clauses[] = 'option_value LIKE %s';
				$opt_params[]  = '%' . $wpdb->esc_like( $rel_file ) . '%';
			}

			$where_opts = implode( ' OR ', $opt_clauses );
			$opts_sql   = $wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options}
				WHERE option_name NOT LIKE '_transient%'
				AND option_name NOT LIKE '_site_transient%'
				AND option_name NOT LIKE '%_ha_io_%'
				AND ({$where_opts})
				LIMIT 3",
				...$opt_params
			);
			$opt_rows = $wpdb->get_results( $opts_sql );

			if ( ! empty( $opt_rows ) ) {
				foreach ( $opt_rows as $orow ) {
					$opt_name  = (string) ( $orow->option_name ?? '' );
					$is_widget = str_starts_with( $opt_name, 'widget_' );
					$label     = $is_widget
						? sprintf( __( 'WordPress Widget ("%s")', 'ha-image-optimizer' ), $opt_name )
						: sprintf( __( 'WordPress Option ("%s")', 'ha-image-optimizer' ), $opt_name );
					$url       = $is_widget ? self::safe_admin_url( 'widgets.php' ) : self::safe_admin_url( 'options-general.php' );

					$references[] = [
						'label' => $label,
						'url'   => $url,
					];
				}
			}
		}

		// -------------------------------------------------------------
		// 13. Safety Buffer: Protect recently uploaded media from accidental deletion
		// -------------------------------------------------------------
		$safety_days = (int) Settings::get( 'auditor_safety_buffer_days', 14 );
		if ( function_exists( 'apply_filters' ) ) {
			$safety_days = (int) apply_filters( 'ha_io_audit_safety_period_days', $safety_days, $attachment_id );
		}

		if ( empty( $references ) && $safety_days > 0 ) {
			$post_date = ! empty( $post->post_date_gmt ) && '0000-00-00 00:00:00' !== $post->post_date_gmt
				? $post->post_date_gmt
				: $post->post_date;

			$created_time = strtotime( (string) $post_date );
			if ( $created_time ) {
				$day_in_sec = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
				$age_days   = (int) floor( ( time() - $created_time ) / $day_in_sec );
				if ( $age_days <= $safety_days ) {
					$references[] = [
						'label' => sprintf(
							/* translators: 1: age in days, 2: configured safety period */
							__( '🛡️ Schutzfrist aktiv: Vor %1$d Tagen hochgeladen (Schutz vor versehentlichem Löschen, Frist: %2$d Tage)', 'ha-image-optimizer' ),
							max( 0, $age_days ),
							$safety_days
						),
						'url'   => self::safe_admin_url( 'post.php?post=' . $attachment_id . '&action=edit' ),
					];
				}
			}
		}

		// -------------------------------------------------------------
		// 14. Extensibility Hook for Developers & Plugins
		// -------------------------------------------------------------
		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter detected references for an audited attachment.
			 *
			 * @param array<array{label: string, url: string}> $references Detected references.
			 * @param int                                      $attachment_id Attachment ID.
			 * @param \WP_Post                                 $post Attachment post object.
			 */
			$references = (array) apply_filters( 'ha_io_audit_attachment_references', $references, $attachment_id, $post );
		}

		// -------------------------------------------------------------
		// 15. Status Classification & Deduplication
		// -------------------------------------------------------------
		$deduped = [];
		$seen    = [];
		foreach ( $references as $ref ) {
			$label = is_array( $ref ) ? ( $ref['label'] ?? '' ) : (string) $ref;
			if ( ! empty( $label ) && ! isset( $seen[ $label ] ) ) {
				$seen[ $label ] = true;
				$deduped[]      = $ref;
			}
		}
		$references = $deduped;

		if ( ! empty( $references ) ) {
			$status = self::STATUS_USED;
		} elseif ( $parent_id > 0 ) {
			$status = self::STATUS_ATTACHED_UNUSED;
		} else {
			$status = self::STATUS_UNATTACHED_UNUSED;
		}

		$reference_labels = array_map( function ( $r ) {
			return is_array( $r ) ? ( $r['label'] ?? '' ) : (string) $r;
		}, $references );

		return [
			'id'             => $attachment_id,
			'filename'       => $filename,
			'mime'           => $post->post_mime_type,
			'thumb_url'      => (string) $thumb_url,
			'date'           => get_the_date( 'Y-m-d H:i', $attachment_id ),
			'parent_id'      => $parent_id,
			'parent_title'   => $parent_title,
			'parent_url'     => $parent_id > 0 ? self::safe_admin_url( 'post.php?post=' . $parent_id . '&action=edit' ) : '',
			'disk_bytes'     => $disk_bytes,
			'disk_human'     => SizeGuard::format_size( $disk_bytes, 1 ),
			'status'         => $status,
			'references'     => $references,
			'reference_desc' => implode( ', ', $reference_labels ),
			'scanned_at'     => time(),
		];
	}

	/**
	 * Search active theme files (style.css, templates, theme.json) for image references.
	 *
	 * @param string $filename Image basename.
	 * @param string $rel_file Relative upload path.
	 * @return string|null Found file name or null.
	 */
	public static function search_active_theme_files( string $filename, string $rel_file ): ?string {
		if ( null === self::$theme_files_cache ) {
			self::$theme_files_cache = [];
			$dirs = [];

			if ( function_exists( 'get_stylesheet_directory' ) ) {
				$dirs[] = get_stylesheet_directory();
			}
			if ( function_exists( 'get_template_directory' ) ) {
				$dirs[] = get_template_directory();
			}

			$dirs = array_unique( array_filter( $dirs ) );

			foreach ( $dirs as $dir ) {
				if ( ! is_dir( $dir ) ) {
					continue;
				}

				// Check primary styling and config files first
				$key_files = [ 'style.css', 'theme.json', 'header.php', 'footer.php', 'front-page.php' ];
				foreach ( $key_files as $kf ) {
					$full = trailingslashit( $dir ) . $kf;
					if ( file_exists( $full ) && is_readable( $full ) && (int) @filesize( $full ) < 500000 ) {
						self::$theme_files_cache[ $kf ] = (string) file_get_contents( $full );
					}
				}
			}
		}

		foreach ( self::$theme_files_cache as $file_name => $content ) {
			if ( ! empty( $rel_file ) && str_contains( $content, $rel_file ) ) {
				return $file_name;
			}
			if ( strlen( $filename ) >= 6 && str_contains( $content, $filename ) ) {
				return $file_name;
			}
		}

		return null;
	}

	/**
	 * Safe helper to generate admin URLs.
	 *
	 * @param string $path Relative admin path.
	 * @return string
	 */
	private static function safe_admin_url( string $path ): string {
		if ( function_exists( 'admin_url' ) ) {
			return admin_url( $path );
		}
		return 'wp-admin/' . ltrim( $path, '/' );
	}

	/**
	 * Find a custom table by LIKE pattern, cached in memory.
	 *
	 * @param string $pattern Table pattern, e.g. '%smartslider%slides'.
	 * @return string|null Table name if exists, null otherwise.
	 */
	private static function get_custom_table( string $pattern ): ?string {
		global $wpdb;

		if ( null === self::$custom_tables_cache ) {
			self::$custom_tables_cache = [];
		}

		if ( array_key_exists( $pattern, self::$custom_tables_cache ) ) {
			return ! empty( self::$custom_tables_cache[ $pattern ] ) ? self::$custom_tables_cache[ $pattern ] : null;
		}

		$prefix       = ! empty( $wpdb->prefix ) ? $wpdb->prefix : 'wp_';
		$full_pattern = $wpdb->esc_like( $prefix ) . $pattern;
		$found        = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $full_pattern ) );

		self::$custom_tables_cache[ $pattern ] = $found ? (string) $found : '';
		return $found ? (string) $found : null;
	}

	/**
	 * Discover and cache eligible third-party tables and their text columns.
	 *
	 * @return array<string, array{columns: array<string>, label: string, url_template: string, id_col: string, title_col: string}>
	 */
	public static function get_generic_custom_tables(): array {
		global $wpdb;

		if ( null !== self::$generic_custom_tables_cache ) {
			return self::$generic_custom_tables_cache;
		}

		$cached = function_exists( 'get_transient' ) ? get_transient( 'ha_io_audit_tables_schema' ) : false;
		if ( is_array( $cached ) ) {
			self::$generic_custom_tables_cache = $cached;
			return self::$generic_custom_tables_cache;
		}

		$prefix     = ! empty( $wpdb->prefix ) ? $wpdb->prefix : 'wp_';
		$all_tables = (array) $wpdb->get_col( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) );

		$core_tables = [
			$prefix . 'posts',
			$prefix . 'postmeta',
			$prefix . 'comments',
			$prefix . 'commentmeta',
			$prefix . 'terms',
			$prefix . 'termmeta',
			$prefix . 'term_taxonomy',
			$prefix . 'term_relationships',
			$prefix . 'users',
			$prefix . 'usermeta',
			$prefix . 'options',
			$prefix . 'links',
		];

		$default_ignored = [
			'%action_scheduler%',
			'%actionscheduler%',
			'%log%',
			'%cache%',
			'%session%',
			'%analytics%',
			'%stats%',
			'%redirection%',
			'%yoast_indexable%',
			'%rank_math_analytics%',
			'%wf%',
			'%failed_jobs%',
			'%matomo%',
			'%transient%',
			'%ha_io_%',
			'%smush%',
			'%ewww%',
			'%imagify%',
			'%shortpixel%',
			'%optimus%',
			'%webp%',
			'%relevanssi%',
			'%searchwp%',
			'%backup%',
			'%updraft%',
			'%duplicator%',
		];

		$ignored_patterns = function_exists( 'apply_filters' )
			? (array) apply_filters( 'ha_io_audit_ignored_tables', $default_ignored )
			: $default_ignored;

		$user_tables_raw = (string) Settings::get( 'auditor_custom_tables', '' );
		$user_tables     = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $user_tables_raw ) ) ) );

		$discovered = [];

		foreach ( $all_tables as $table ) {
			$table = (string) $table;

			if ( in_array( $table, $core_tables, true ) ) {
				continue;
			}

			$is_user_explicit = in_array( $table, $user_tables, true ) || in_array( str_replace( $prefix, '', $table ), $user_tables, true );

			if ( ! $is_user_explicit ) {
				$skip = false;
				foreach ( $ignored_patterns as $pattern ) {
					$clean_pat = trim( $pattern, '%' );
					if ( ! empty( $clean_pat ) && str_contains( strtolower( $table ), strtolower( $clean_pat ) ) ) {
						$skip = true;
						break;
					}
				}
				if ( $skip ) {
					continue;
				}
			}

			$cols_data = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`" );
			if ( empty( $cols_data ) ) {
				continue;
			}

			$text_columns = [];
			$id_col       = '';
			$title_col    = '';

			foreach ( $cols_data as $cd ) {
				$field = (string) ( $cd->Field ?? '' );
				$type  = strtolower( (string) ( $cd->Type ?? '' ) );

				if ( empty( $id_col ) && in_array( strtolower( $field ), [ 'id', 'id_entry', 'entry_id', 'item_id', 'slide_id', 'form_id' ], true ) ) {
					$id_col = $field;
				}
				if ( empty( $title_col ) && in_array( strtolower( $field ), [ 'title', 'name', 'post_title', 'label' ], true ) ) {
					$title_col = $field;
				}

				if (
					str_contains( $type, 'varchar' ) ||
					str_contains( $type, 'text' ) ||
					str_contains( $type, 'json' ) ||
					str_contains( $type, 'blob' )
				) {
					$text_columns[] = $field;
				}
			}

			if ( empty( $text_columns ) ) {
				continue;
			}

			$clean_name = str_replace( $prefix, '', $table );
			$label      = sprintf( __( 'Tabelle "%s"', 'ha-image-optimizer' ), $clean_name );

			$discovered[ $table ] = [
				'columns'      => $text_columns,
				'id_col'       => $id_col,
				'title_col'    => $title_col,
				'label'        => $label,
				'url_template' => '',
			];
		}

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter custom tables schema for media audit.
			 *
			 * @param array<string, array{columns: array<string>, label: string, url_template: string, id_col: string, title_col: string}> $discovered
			 */
			$discovered = (array) apply_filters( 'ha_io_audit_custom_tables', $discovered );
		}

		$hour = defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600;
		if ( function_exists( 'set_transient' ) ) {
			set_transient( 'ha_io_audit_tables_schema', $discovered, $hour );
		}

		self::$generic_custom_tables_cache = $discovered;
		return self::$generic_custom_tables_cache;
	}

	/**
	 * Calculate total disk bytes occupied by an attachment (master + thumbnails + webp + avif).
	 *
	 * @param int         $attachment_id Attachment ID.
	 * @param string|null $file Main file path.
	 * @return int Total bytes.
	 */
	public static function calculate_disk_bytes( int $attachment_id, ?string $file ): int {
		if ( ! $file || ! file_exists( $file ) ) {
			return 0;
		}

		$bytes    = (int) filesize( $file );
		$base_dir = dirname( $file );

		// Check master WebP and AVIF
		if ( file_exists( $file . '.webp' ) ) {
			$bytes += (int) filesize( $file . '.webp' );
		}
		if ( file_exists( $file . '.avif' ) ) {
			$bytes += (int) filesize( $file . '.avif' );
		}

		// Check thumbnails
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size_info ) {
				if ( empty( $size_info['file'] ) ) {
					continue;
				}

				$sub_path = trailingslashit( $base_dir ) . $size_info['file'];
				if ( file_exists( $sub_path ) ) {
					$bytes += (int) filesize( $sub_path );
				}
				if ( file_exists( $sub_path . '.webp' ) ) {
					$bytes += (int) filesize( $sub_path . '.webp' );
				}
				if ( file_exists( $sub_path . '.avif' ) ) {
					$bytes += (int) filesize( $sub_path . '.avif' );
				}
			}
		}

		return $bytes;
	}

	/**
	 * Move an attachment safely to trash or delete it.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $force_delete If true, permanently deletes files. If false, moves to trash.
	 * @return bool True on success.
	 */
	public static function trash_attachment( int $attachment_id, bool $force_delete = false ): bool {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id <= 0 ) {
			return false;
		}

		// Optional backup before permanent deletion
		if ( $force_delete ) {
			$file = get_attached_file( $attachment_id );
			if ( $file && file_exists( $file ) ) {
				BackupManager::backup( $attachment_id, $file );
			}
		}

		$result = wp_delete_attachment( $attachment_id, $force_delete );

		if ( $result ) {
			// Remove from cached audit results
			self::remove_from_cached_results( $attachment_id );
			return true;
		}

		return false;
	}

	/**
	 * Restore an attachment from trash.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function restore_attachment( int $attachment_id ): bool {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id <= 0 ) {
			return false;
		}

		if ( function_exists( 'wp_untrash_post' ) ) {
			$result = wp_untrash_post( $attachment_id );
			return ! empty( $result );
		}

		return false;
	}

	/**
	 * Get cached audit results from options table.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_cached_results(): array {
		$data = get_option( self::OPTION_RESULTS, [] );
		if ( ! is_array( $data ) ) {
			$data = [];
		}

		$defaults = [
			'last_scan'     => 0,
			'total_scanned' => 0,
			'counts'        => [
				'total'             => 0,
				'used'              => 0,
				'attached_unused'   => 0,
				'unattached_unused' => 0,
			],
			'reclaimable_bytes' => [
				'unattached' => 0,
				'attached'   => 0,
				'total'      => 0,
			],
			'items'         => [],
		];

		return wp_parse_args( $data, $defaults );
	}

	/**
	 * Update cached audit results with a batch of newly audited items.
	 *
	 * @param array<array<string, mixed>> $new_items List of audited item records.
	 * @return array<string, mixed> Updated full results summary.
	 */
	public static function update_cached_results( array $new_items ): array {
		$cache = self::get_cached_results();

		foreach ( $new_items as $item ) {
			$id = absint( $item['id'] ?? 0 );
			if ( $id > 0 ) {
				$cache['items'][ $id ] = $item;
			}
		}

		return self::recalculate_and_save( $cache );
	}

	/**
	 * Remove a single ID from cached audit results.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function remove_from_cached_results( int $attachment_id ): void {
		$cache = self::get_cached_results();
		if ( isset( $cache['items'][ $attachment_id ] ) ) {
			unset( $cache['items'][ $attachment_id ] );
			self::recalculate_and_save( $cache );
		}
	}

	/**
	 * Recalculate summary metrics and save cache to WordPress options.
	 *
	 * @param array<string, mixed> $cache Cache data array.
	 * @return array<string, mixed>
	 */
	private static function recalculate_and_save( array $cache ): array {
		$counts = [
			'total'             => count( $cache['items'] ),
			'used'              => 0,
			'attached_unused'   => 0,
			'unattached_unused' => 0,
		];

		$reclaimable = [
			'unattached' => 0,
			'attached'   => 0,
			'total'      => 0,
		];

		foreach ( $cache['items'] as $item ) {
			$status = $item['status'] ?? self::STATUS_UNATTACHED_UNUSED;
			$bytes  = (int) ( $item['disk_bytes'] ?? 0 );

			if ( self::STATUS_USED === $status ) {
				$counts['used']++;
			} elseif ( self::STATUS_ATTACHED_UNUSED === $status ) {
				$counts['attached_unused']++;
				$reclaimable['attached'] += $bytes;
			} else {
				$counts['unattached_unused']++;
				$reclaimable['unattached'] += $bytes;
			}
		}

		$reclaimable['total'] = $reclaimable['unattached'] + $reclaimable['attached'];

		$cache['last_scan']         = time();
		$cache['total_scanned']     = $counts['total'];
		$cache['counts']            = $counts;
		$cache['reclaimable_bytes'] = $reclaimable;

		update_option( self::OPTION_RESULTS, $cache, false );
		return $cache;
	}

	/**
	 * Clear all cached audit results.
	 *
	 * @return bool
	 */
	public static function clear_results(): bool {
		self::$theme_files_cache          = null;
		self::$custom_tables_cache        = null;
		self::$generic_custom_tables_cache = null;
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( 'ha_io_audit_tables_schema' );
		}
		return delete_option( self::OPTION_RESULTS );
	}
}
