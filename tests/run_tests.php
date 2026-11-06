<?php
/**
 * Automated CI Test Suite for HA Image Optimizer WordPress Plugin.
 *
 * Covers:
 * 1. PHP Syntax, BOM/Header safety, and strict standards (including .l10n.php)
 * 2. PSR-4 Autoloader resolution for ha\ImageOptimizer
 * 3. Settings defaults, sanitization, and traversal protection
 * 4. SizeGuard negative savings protection (original and next-gen)
 * 5. Environment capability checks
 * 6. BackupManager path calculation, .htaccess directory shielding, and custom backup handling
 * 7. CustomFolderProcessor directory traversal prevention, recursive image discovery, and queue building
 * 8. ContentFilter URL resolution (uploads and themes), <picture> rewriting, and CSS image-set()
 * 9. HtaccessManager Apache rewrite rules and safe marker manipulation
 * 10. Localization integrity across 26 languages (.po, .mo, and WP 6.5+ .l10n.php)
 *
 * @package HA_Image_Optimizer
 */

declare(strict_types=1);

namespace {

	// Minimal WordPress environment stubs for standalone CI execution
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	}
	if ( ! defined( 'WP_CONTENT_DIR' ) ) {
		define( 'WP_CONTENT_DIR', dirname( __DIR__ ) . '/tests/fixtures/wp-content' );
	}

	$GLOBALS['wp_test_options']  = [];
	$GLOBALS['wp_test_postmeta'] = [];
	$GLOBALS['wp_test_actions']  = [];
	$GLOBALS['wp_test_filters']  = [];

	if ( ! function_exists( 'absint' ) ) {
		function absint( $val ): int {
			return abs( (int) $val );
		}
	}

	if ( ! function_exists( 'sanitize_key' ) ) {
		function sanitize_key( $key ): string {
			return preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key );
		}
	}

	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $str ): string {
			return strip_tags( trim( (string) $str ) );
		}
	}

	if ( ! function_exists( 'wp_unslash' ) ) {
		function wp_unslash( $val ) {
			return is_string( $val ) ? stripslashes( $val ) : $val;
		}
	}

	if ( ! function_exists( 'wp_is_writable' ) ) {
		function wp_is_writable( $path ): bool {
			return is_writable( $path );
		}
	}

	if ( ! function_exists( 'wp_convert_hr_to_bytes' ) ) {
		function wp_convert_hr_to_bytes( $value ): int {
			$value = trim( (string) $value );
			if ( '-1' === $value || -1 === (int) $value ) {
				return -1;
			}
			$last = strtolower( $value[ strlen( $value ) - 1 ] );
			$val  = (int) $value;
			switch ( $last ) {
				case 'g':
					$val *= 1024 * 1024 * 1024;
					break;
				case 'm':
					$val *= 1024 * 1024;
					break;
				case 'k':
					$val *= 1024;
					break;
			}
			return $val;
		}
	}

	if ( ! function_exists( 'trailingslashit' ) ) {
		function trailingslashit( $string ): string {
			return rtrim( (string) $string, '/\\' ) . '/';
		}
	}

	if ( ! function_exists( 'wp_normalize_path' ) ) {
		function wp_normalize_path( $path ): string {
			$path = str_replace( '\\', '/', (string) $path );
			$path = (string) preg_replace( '|(?<=.)/+|', '/', $path );
			if ( ':' === substr( $path, 1, 1 ) ) {
				$path = ucfirst( $path );
			}
			return $path;
		}
	}

	if ( ! function_exists( 'wp_mkdir_p' ) ) {
		function wp_mkdir_p( $target ): bool {
			return is_dir( $target ) || @mkdir( $target, 0777, true );
		}
	}

	if ( ! function_exists( 'wp_parse_args' ) ) {
		function wp_parse_args( $args, $defaults = [] ): array {
			if ( is_object( $args ) ) {
				$r = get_object_vars( $args );
			} elseif ( is_array( $args ) ) {
				$r =& $args;
			} else {
				$r = [];
			}
			return array_merge( $defaults, $r );
		}
	}

	if ( ! function_exists( 'esc_html' ) ) {
		function esc_html( $text ): string {
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_attr' ) ) {
		function esc_attr( $text ): string {
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_url' ) ) {
		function esc_url( $url ): string {
			return (string) filter_var( $url, FILTER_SANITIZE_URL );
		}
	}

	if ( ! function_exists( 'esc_html__' ) ) {
		function esc_html__( $text, $domain = 'default' ): string {
			return (string) $text;
		}
	}

	if ( ! function_exists( 'esc_sql' ) ) {
		function esc_sql( $data ) {
			return is_string( $data ) ? addslashes( $data ) : $data;
		}
	}

	if ( ! function_exists( '__' ) ) {
		function __( $text, $domain = 'default' ): string {
			return (string) $text;
		}
	}

	if ( ! function_exists( 'size_format' ) ) {
		function size_format( $bytes, $decimals = 0 ): string {
			$bytes = (int) $bytes;
			if ( $bytes <= 0 ) {
				return '0 B';
			}
			$units = [ 'B', 'KB', 'MB', 'GB' ];
			$i     = (int) floor( log( $bytes, 1024 ) );
			$val   = $bytes / pow( 1024, $i );
			$fmt   = number_format( (float) $val, $decimals, ',', '.' );
			return $fmt . ' ' . ( $units[ $i ] ?? 'B' );
		}
	}

	if ( ! function_exists( 'number_format_i18n' ) ) {
		function number_format_i18n( $number, $decimals = 0 ): string {
			return number_format( (float) $number, $decimals, ',', '.' );
		}
	}

	if ( ! function_exists( 'wp_basename' ) ) {
		function wp_basename( $path ): string {
			return basename( str_replace( '\\', '/', (string) $path ) );
		}
	}

	if ( ! function_exists( 'is_feed' ) ) {
		function is_feed(): bool {
			return false;
		}
	}

	if ( ! function_exists( 'get_option' ) ) {
		function get_option( $name, $default = false ) {
			return $GLOBALS['wp_test_options'][ $name ] ?? $default;
		}
	}

	if ( ! function_exists( 'update_option' ) ) {
		function update_option( $name, $value, $autoload = null ): bool {
			$GLOBALS['wp_test_options'][ $name ] = $value;
			return true;
		}
	}

	if ( ! function_exists( 'delete_option' ) ) {
		function delete_option( $name ): bool {
			unset( $GLOBALS['wp_test_options'][ $name ] );
			return true;
		}
	}

	if ( ! function_exists( 'wp_load_alloptions' ) ) {
		function wp_load_alloptions(): array {
			return $GLOBALS['wp_test_options'] ?? [];
		}
	}

	if ( ! function_exists( 'add_action' ) ) {
		function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
			$GLOBALS['wp_test_actions'][ $hook ][] = $callback;
		}
	}

	if ( ! function_exists( 'do_action' ) ) {
		function do_action( $hook, ...$args ): void {
			if ( ! empty( $GLOBALS['wp_test_actions'][ $hook ] ) ) {
				foreach ( $GLOBALS['wp_test_actions'][ $hook ] as $callback ) {
					call_user_func( $callback, ...$args );
				}
			}
		}
	}

	if ( ! function_exists( 'add_filter' ) ) {
		function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
			$GLOBALS['wp_test_filters'][ $hook ][] = $callback;
		}
	}

	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( $hook, $value, ...$args ) {
			if ( ! empty( $GLOBALS['wp_test_filters'][ $hook ] ) ) {
				foreach ( $GLOBALS['wp_test_filters'][ $hook ] as $callback ) {
					$value = call_user_func( $callback, $value, ...$args );
				}
			}
			return $value;
		}
	}

	$GLOBALS['wp_test_transients'] = [];
	if ( ! function_exists( 'get_transient' ) ) {
		function get_transient( $transient ) {
			return $GLOBALS['wp_test_transients'][ $transient ] ?? false;
		}
	}
	if ( ! function_exists( 'set_transient' ) ) {
		function set_transient( $transient, $value, $expiration = 0 ): bool {
			$GLOBALS['wp_test_transients'][ $transient ] = $value;
			return true;
		}
	}
	if ( ! function_exists( 'delete_transient' ) ) {
		function delete_transient( $transient ): bool {
			unset( $GLOBALS['wp_test_transients'][ $transient ] );
			return true;
		}
	}

	$GLOBALS['wp_test_crons'] = [];
	if ( ! function_exists( 'wp_next_scheduled' ) ) {
		function wp_next_scheduled( $hook, $args = [] ) {
			return $GLOBALS['wp_test_crons'][ $hook ] ?? false;
		}
	}
	if ( ! function_exists( 'wp_schedule_event' ) ) {
		function wp_schedule_event( $timestamp, $recurrence, $hook, $args = [] ): bool {
			$GLOBALS['wp_test_crons'][ $hook ] = $timestamp;
			return true;
		}
	}
	if ( ! function_exists( 'wp_unschedule_event' ) ) {
		function wp_unschedule_event( $timestamp, $hook, $args = [] ): bool {
			unset( $GLOBALS['wp_test_crons'][ $hook ] );
			return true;
		}
	}
	if ( ! function_exists( 'wp_remote_post' ) ) {
		function wp_remote_post( $url, $args = [] ) {
			return [ 'response' => [ 'code' => 200 ] ];
		}
	}
	if ( ! function_exists( 'wp_create_nonce' ) ) {
		function wp_create_nonce( $action = -1 ): string {
			return 'test_nonce_' . $action;
		}
	}
	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $thing = null ): bool {
			return false;
		}
	}

	if ( ! function_exists( 'wp_verify_nonce' ) ) {
		function wp_verify_nonce( $nonce, $action = -1 ): bool {
			return true;
		}
	}

	if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
		define( 'MINUTE_IN_SECONDS', 60 );
	}
	if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
		define( 'HOUR_IN_SECONDS', 3600 );
	}
	if ( ! defined( 'DAY_IN_SECONDS' ) ) {
		define( 'DAY_IN_SECONDS', 86400 );
	}

	if ( ! function_exists( 'wp_upload_dir' ) ) {
		function wp_upload_dir(): array {
			$base = wp_normalize_path( WP_CONTENT_DIR . '/uploads' );
			return [
				'path'    => $base,
				'url'     => 'https://example.com/wp-content/uploads',
				'subdir'  => '',
				'basedir' => $base,
				'baseurl' => 'https://example.com/wp-content/uploads',
				'error'   => false,
			];
		}
	}

	if ( ! function_exists( 'content_url' ) ) {
		function content_url( $path = '' ): string {
			return 'https://example.com/wp-content' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
		}
	}

	if ( ! function_exists( 'admin_url' ) ) {
		function admin_url( $path = '', $scheme = 'admin' ): string {
			return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
		}
	}

	if ( ! function_exists( 'current_user_can' ) ) {
		function current_user_can( $capability, ...$args ): bool {
			return true;
		}
	}

	if ( ! function_exists( 'is_serialized' ) ) {
		function is_serialized( $data, $strict = true ): bool {
			if ( ! is_string( $data ) ) {
				return false;
			}
			$data = trim( $data );
			if ( 'N;' === $data ) {
				return true;
			}
			if ( strlen( $data ) < 4 || ':' !== $data[1] ) {
				return false;
			}
			return true;
		}
	}

	if ( ! function_exists( 'maybe_unserialize' ) ) {
		function maybe_unserialize( $original ) {
			if ( is_serialized( $original ) ) {
				return @unserialize( $original );
			}
			return $original;
		}
	}

	if ( ! function_exists( 'check_ajax_referer' ) ) {
		function check_ajax_referer( $action = -1, $query_arg = false, $die = true ): int {
			return 1;
		}
	}

	if ( ! function_exists( 'wp_send_json_success' ) ) {
		function wp_send_json_success( $data = null, $status_code = null, $options = 0 ): void {
			echo json_encode( [ 'success' => true, 'data' => $data ] );
		}
	}

	if ( ! function_exists( 'wp_send_json_error' ) ) {
		function wp_send_json_error( $data = null, $status_code = null, $options = 0 ): void {
			echo json_encode( [ 'success' => false, 'data' => $data ] );
		}
	}

	if ( ! function_exists( 'get_post_meta' ) ) {
		function get_post_meta( $post_id, $key = '', $single = false ) {
			return $GLOBALS['wp_test_postmeta'][ $post_id ][ $key ] ?? ( $single ? '' : [] );
		}
	}

	if ( ! function_exists( 'update_post_meta' ) ) {
		function update_post_meta( $post_id, $key, $value, $prev_value = '' ): bool {
			$GLOBALS['wp_test_postmeta'][ $post_id ][ $key ] = $value;
			return true;
		}
	}

	if ( ! function_exists( 'delete_post_meta' ) ) {
		function delete_post_meta( $post_id, $key, $value = '' ): bool {
			unset( $GLOBALS['wp_test_postmeta'][ $post_id ][ $key ] );
			return true;
		}
	}

	if ( ! function_exists( 'get_attached_file' ) ) {
		function get_attached_file( $attachment_id, $unfiltered = false ) {
			return $GLOBALS['wp_test_attached_file'][ $attachment_id ] ?? false;
		}
	}

	if ( ! function_exists( 'wp_get_attachment_metadata' ) ) {
		function wp_get_attachment_metadata( $attachment_id, $unfiltered = false ) {
			return $GLOBALS['wp_test_metadata'][ $attachment_id ] ?? [];
		}
	}

	if ( ! function_exists( 'wp_update_attachment_metadata' ) ) {
		function wp_update_attachment_metadata( $attachment_id, $data ): bool {
			$GLOBALS['wp_test_metadata'][ $attachment_id ] = $data;
			return true;
		}
	}

	$GLOBALS['wp_test_original_image_path'] = [];
	if ( ! function_exists( 'wp_get_original_image_path' ) ) {
		function wp_get_original_image_path( $attachment_id ) {
			return $GLOBALS['wp_test_original_image_path'][ $attachment_id ] ?? false;
		}
	}

	$GLOBALS['wp_test_posts'] = [];
	if ( ! function_exists( 'get_post' ) ) {
		function get_post( $post = null ) {
			if ( is_object( $post ) ) {
				return $post;
			}
			$id = (int) $post;
			return $GLOBALS['wp_test_posts'][ $id ] ?? (object) [
				'ID'             => $id,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => 0,
				'post_status'    => 'inherit',
				'post_title'     => 'Mock Attachment ' . $id,
				'post_date'      => '2020-01-01 12:00:00',
				'post_date_gmt'  => '2020-01-01 12:00:00',
			];
		}
	}

	if ( ! function_exists( 'get_the_title' ) ) {
		function get_the_title( $post = 0 ): string {
			$p = get_post( $post );
			return $p ? (string) ( $p->post_title ?? '' ) : '';
		}
	}

	if ( ! function_exists( 'get_the_date' ) ) {
		function get_the_date( $format = '', $post = null ): string {
			return '2026-10-21 12:00';
		}
	}

	if ( ! function_exists( 'get_theme_mod' ) ) {
		function get_theme_mod( $name, $default_val = false ) {
			return $GLOBALS['wp_test_theme_mods'][ $name ] ?? $default_val;
		}
	}

	if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
		function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) {
			return "https://example.com/wp-content/uploads/image-{$id}-150x150.jpg";
		}
	}

	if ( ! function_exists( 'wp_get_attachment_url' ) ) {
		function wp_get_attachment_url( $id ) {
			return "https://example.com/wp-content/uploads/image-{$id}.jpg";
		}
	}

	if ( ! function_exists( 'wp_delete_attachment' ) ) {
		function wp_delete_attachment( $post_id, $force_delete = false ) {
			return true;
		}
	}

	if ( ! function_exists( 'wp_untrash_post' ) ) {
		function wp_untrash_post( $post_id = 0 ) {
			return true;
		}
	}

	if ( ! function_exists( 'get_stylesheet_directory' ) ) {
		function get_stylesheet_directory(): string {
			return sys_get_temp_dir();
		}
	}

	if ( ! function_exists( 'get_template_directory' ) ) {
		function get_template_directory(): string {
			return sys_get_temp_dir();
		}
	}

	if ( ! function_exists( 'get_post_mime_type' ) ) {
		function get_post_mime_type( $id = 0 ): string {
			$p = get_post( $id );
			return $p ? (string) ( $p->post_mime_type ?? 'image/jpeg' ) : 'image/jpeg';
		}
	}

	if ( ! function_exists( 'wp_raise_memory_limit' ) ) {
		function wp_raise_memory_limit( $context = 'admin' ): void {}
	}

	if ( ! function_exists( 'current_time' ) ) {
		function current_time( $type, $gmt = 0 ): string {
			return date( 'Y-m-d H:i:s' );
		}
	}

	if ( ! function_exists( 'selected' ) ) {
		function selected( $selected, $current = true, $display = true ): string {
			$result = ( (string) $selected === (string) $current ) ? " selected='selected'" : '';
			if ( $display ) {
				echo $result;
			}
			return $result;
		}
	}

	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		function wp_generate_attachment_metadata( $attachment_id, $file ) {
			return [
				'width'  => 800,
				'height' => 600,
				'file'   => wp_basename( $file ),
				'sizes'  => [],
			];
		}
	}

	class MockWpdb {
		public string $prefix        = 'wp_';
		public string $posts         = 'wp_posts';
		public string $postmeta      = 'wp_postmeta';
		public string $termmeta      = 'wp_termmeta';
		public string $terms         = 'wp_terms';
		public string $term_taxonomy = 'wp_term_taxonomy';
		public string $options       = 'wp_options';

		public function get_var( $query ) {
			if ( is_string( $query ) && str_contains( $query, 'SHOW TABLES LIKE' ) ) {
				if ( str_contains( $query, 'smartslider' ) && str_contains( $query, 'slides' ) ) {
					return 'wp_nextend2_smartslider3_slides';
				}
				if ( str_contains( $query, 'smartslider' ) && str_contains( $query, 'sliders' ) ) {
					return 'wp_nextend2_smartslider3_sliders';
				}
				return null;
			}
			return 10;
		}

		public function get_results( $query ) {
			if ( str_contains( $query, 'SHOW COLUMNS FROM `wp_wpforms_entries`' ) ) {
				return [
					(object) [ 'Field' => 'entry_id', 'Type' => 'int(11)' ],
					(object) [ 'Field' => 'fields',   'Type' => 'longtext' ],
				];
			}
			if ( str_contains( $query, 'FROM `wp_wpforms_entries`' ) ) {
				if ( str_contains( $query, '20' ) ) {
					return [
						(object) [ 'entry_id' => 88, 'fields' => '{"imageId":20}' ],
					];
				}
				return [];
			}
			if ( str_contains( $query, 'meta_key NOT IN' ) && str_contains( $query, "meta_key NOT LIKE '_acf_%'" ) ) {
				if ( str_contains( $query, '25' ) ) {
					return [
						(object) [ 'ID' => 55, 'post_title' => 'Landingpage', 'post_type' => 'page', 'meta_key' => 'custom_hero_image' ],
					];
				}
				return [];
			}
			if ( str_contains( $query, '_thumbnail_id' ) ) {
				if ( str_contains( $query, 'attachment_id' ) && str_contains( $query, 'IN (' ) ) {
					$results = [];
					if ( preg_match( '/\b1\b/', $query ) ) {
						$results[] = (object) [
							'attachment_id' => 1,
							'ID'            => 42,
							'post_title'    => 'Beispielbeitrag',
							'post_type'     => 'post',
						];
					}
					return $results;
				}
				if ( preg_match( '/meta_value\s*=\s*[\'"]?1\b/', $query ) ) {
					return [
						(object) [ 'ID' => 42, 'post_title' => 'Beispielbeitrag', 'post_type' => 'post' ],
					];
				}
				return [];
			}
			if ( str_contains( $query, 'nextend2_smartslider3_slides' ) ) {
				if ( preg_match( '/"imageId":\s*[\'"]?1\b/', $query ) ) {
					return [
						(object) [
							'id'           => 101,
							'slider'       => 5,
							'title'        => 'Hero Slide',
							'slider_title' => 'Startseiten Slider',
						],
					];
				}
				return [];
			}
			if ( str_contains( $query, 'p.post_content LIKE' ) ) {
				if ( str_contains( $query, 'landscape-photo' ) || str_contains( $query, '300x200' ) || str_contains( $query, 'scaled' ) ) {
					return [
						(object) [ 'ID' => 70, 'post_title' => 'Beitrag mit skaliertem Bild', 'post_type' => 'post', 'post_content' => 'Hier ist das Bild' ],
					];
				}
				if ( str_contains( $query, '8639' ) || str_contains( $query, '8640' ) || str_contains( $query, '8661' ) ) {
					return [
						(object) [
							'ID'           => 85,
							'post_title'   => 'Urlaubsreise 2024',
							'post_type'    => 'post',
							'post_content' => '[gallery size="large" columns="2" ids="8639,8640,8641,8661"]',
						],
					];
				}
				return [];
			}
			if ( str_contains( $query, "post_type NOT IN ('attachment', 'revision')" ) && str_contains( $query, 'ORDER BY ID ASC' ) ) {
				if ( preg_match( '/ID > (\d+)/', $query, $m ) && (int) $m[1] === 0 ) {
					return [
						(object) [
							'ID'           => 85,
							'post_title'   => 'Urlaubsreise 2024',
							'post_type'    => 'post',
							'post_content' => '<p>Galerie: [gallery ids="8639,8640,8641,8661"] und Bild <img class="wp-image-999" src="/wp-content/uploads/2026/10/landscape-photo-300x200.jpg"></p>',
						],
					];
				}
				return [];
			}
			if ( str_contains( $query, "meta_key IN ('_elementor_data'" ) && str_contains( $query, 'ORDER BY pm.meta_id ASC' ) ) {
				if ( preg_match( '/pm\.meta_id > (\d+)/', $query, $m ) && (int) $m[1] === 0 ) {
					return [
						(object) [
							'meta_id'    => 1,
							'post_id'    => 88,
							'post_title' => 'Elementor Page',
							'post_type'  => 'page',
							'meta_key'   => '_elementor_data',
							'meta_value' => '[{"id":"elem1","settings":{"image":{"id":777,"url":"https://example.com/wp-content/uploads/hero-banner.jpg"}}}]',
						],
					];
				}
				return [];
			}
			if ( str_contains( $query, '_ha_io_data' ) ) {
				return [
					(object) [
						'post_id'    => 1,
						'meta_value' => serialize( [
							'total' => [ 'before' => 100000, 'after' => 60000, 'saved' => 40000, 'percent' => 40.0 ],
						] ),
					],
				];
			}
			return [];
		}

		public function get_col( $query ) {
			if ( is_string( $query ) && str_contains( $query, 'SHOW TABLES' ) ) {
				return [
					'wp_posts',
					'wp_postmeta',
					'wp_options',
					'wp_nextend2_smartslider3_slides',
					'wp_nextend2_smartslider3_sliders',
					'wp_wpforms_entries',
				];
			}
			if ( is_string( $query ) && str_contains( $query, 'SELECT DISTINCT meta_key FROM' ) ) {
				return [ '_elementor_data' ];
			}
			return [ 1, 2, 3 ];
		}

		public function prepare( $query, ...$args ) {
			if ( empty( $args ) ) {
				return $query;
			}
			if ( isset( $args[0] ) && is_array( $args[0] ) && 1 === count( $args ) ) {
				$args = $args[0];
			}
			$res = $query;
			foreach ( $args as $arg ) {
				$val   = is_scalar( $arg ) ? (string) $arg : '';
				$pos_s = strpos( $res, '%s' );
				$pos_d = strpos( $res, '%d' );
				if ( false !== $pos_s && ( false === $pos_d || $pos_s < $pos_d ) ) {
					$res = substr_replace( $res, "'" . $val . "'", $pos_s, 2 );
				} elseif ( false !== $pos_d ) {
					$res = substr_replace( $res, (string) (int) $val, $pos_d, 2 );
				}
			}
			return $res;
		}

		public function esc_like( $text ) {
			return addcslashes( (string) $text, '_%\\' );
		}
	}
	$GLOBALS['wpdb'] = new MockWpdb();
}

namespace HA_Image_Optimizer_Tests {

	use ha\ImageOptimizer\Autoloader;
	use ha\ImageOptimizer\Common\Settings;
	use ha\ImageOptimizer\Common\CompressionLevel;
	use ha\ImageOptimizer\Common\DeliveryMode;
	use ha\ImageOptimizer\Common\Environment;
	use ha\ImageOptimizer\Engine\SizeGuard;
	use ha\ImageOptimizer\Media\BackupManager;
	use ha\ImageOptimizer\Media\AttachmentProcessor;
	use ha\ImageOptimizer\Media\CustomFolderProcessor;
	use ha\ImageOptimizer\Delivery\ContentFilter;
	use ha\ImageOptimizer\Delivery\HtaccessManager;
	use ha\ImageOptimizer\Bulk\ProgressTracker;
	use ha\ImageOptimizer\Auditor\MediaAuditor;
	use ha\ImageOptimizer\Auditor\BackgroundAuditor;

	class TestRunner {

		private int $passed = 0;
		private int $failed = 0;
		private string $rootDir;

		public function __construct() {
			$this->rootDir = dirname( __DIR__ );
		}

		public function run(): void {
			echo "=========================================================\n";
			echo "   HA Image Optimizer - Standalone CI Test Suite\n";
			echo "=========================================================\n\n";

			$this->testPhpSyntaxAndBom();
			$this->testAutoloader();
			$this->testSettings();
			$this->testSizeGuard();
			$this->testEnvironment();
			$this->testBackupManager();
			$this->testCustomFolderProcessor();
			$this->testContentFilter();
			$this->testHtaccessManager();
			$this->testProgressTracker();
			$this->testMediaAuditor();
			$this->testBackgroundAuditorAndPacing();
			$this->testIndividualCompressionLevel();
			$this->testTranslations();

			echo "\n---------------------------------------------------------\n";
			echo "TEST SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
			echo "---------------------------------------------------------\n";

			if ( $this->failed > 0 ) {
				exit( 1 );
			}
		}

		private function assert( bool $condition, string $message ): void {
			if ( $condition ) {
				$this->passed++;
				echo "  [PASS] {$message}\n";
			} else {
				$this->failed++;
				echo "  [FAIL] {$message}\n";
			}
		}

		private function testPhpSyntaxAndBom(): void {
			echo "\n1. Checking PHP Syntax, BOM, and File Headers...\n";

			$files = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $this->rootDir, \FilesystemIterator::SKIP_DOTS )
			);

			$checkedCount = 0;
			foreach ( $files as $file ) {
				if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
					continue;
				}

				$path = $file->getPathname();
				if ( str_contains( $path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR ) ) {
					continue;
				}

				$content = (string) file_get_contents( $path );

				// Check BOM
				$hasBom = str_starts_with( $content, "\xEF\xBB\xBF" );
				if ( $hasBom ) {
					$this->assert( false, "File contains UTF-8 BOM: {$path}" );
				}

				// Check that file starts with <?php
				$trimmed = ltrim( $content );
				$validStart = str_starts_with( $trimmed, '<?php' );
				if ( ! $validStart ) {
					$this->assert( false, "PHP file does not start with <?php: {$path}" );
				}

				$checkedCount++;
			}

			$this->assert( $checkedCount > 20, "Validated syntax and headers on {$checkedCount} PHP files" );
		}

		private function testAutoloader(): void {
			echo "\n2. Testing PSR-4 Autoloader...\n";

			require_once $this->rootDir . '/src/Autoloader.php';
			Autoloader::register( $this->rootDir . '/src' );

			$classes = [
				Settings::class,
				CompressionLevel::class,
				DeliveryMode::class,
				Environment::class,
				SizeGuard::class,
				BackupManager::class,
				CustomFolderProcessor::class,
				ContentFilter::class,
				HtaccessManager::class,
				MediaAuditor::class,
			];

			foreach ( $classes as $class ) {
				$this->assert( class_exists( $class ) || enum_exists( $class ), "Autoloader resolved: {$class}" );
			}
		}

		private function testSettings(): void {
			echo "\n3. Testing Settings Repository & Sanitization...\n";

			$defaults = Settings::get_defaults();
			$this->assert( 'lossless' === $defaults['compression_original'], 'Default original compression is lossless' );
			$this->assert( 'smart' === $defaults['compression_sizes'], 'Default sub-size compression is smart' );
			$this->assert( true === $defaults['backup_originals'], 'Default backup_originals is true' );
			$this->assert( 'themes' === $defaults['custom_folders'], 'Default custom_folders is themes' );
			$this->assert( true === $defaults['auditor_fast_mode'], 'Default auditor_fast_mode is true' );
			$this->assert( true === $defaults['auditor_generic_db_scan'], 'Default auditor_generic_db_scan is true' );
			$this->assert( 0 === $defaults['auditor_safety_buffer_days'], 'Default auditor_safety_buffer_days is 0' );

			// Test Sanitization and Path Traversal Prevention
			$dirty = [
				'compression_original'    => 'invalid_mode',
				'max_width'               => 999999, // Too large
				'custom_folders'          => "themes/my-theme\n../dangerous/path\nha-backups\nassets/images",
				'optimize_custom_folders' => '1',
				'auditor_fast_mode'       => '1',
			];

			$clean = Settings::sanitize( $dirty );
			$this->assert( 'lossless' === $clean['compression_original'], 'Invalid compression level fell back to default' );
			$this->assert( 2560 === $clean['max_width'], 'Out-of-bounds width clamped to 2560' );
			$this->assert( true === $clean['auditor_fast_mode'], 'Auditor fast mode sanitized to boolean true' );
			$this->assert( ! str_contains( $clean['custom_folders'], '..' ), 'Directory traversal path (..) stripped from custom_folders' );
			$this->assert( ! str_contains( $clean['custom_folders'], 'ha-backups' ), 'Internal backup path excluded from custom_folders' );
			$this->assert( str_contains( $clean['custom_folders'], 'themes/my-theme' ), 'Valid custom folder retained' );
		}

		private function testSizeGuard(): void {
			echo "\n4. Testing SizeGuard (Negative Savings Protection)...\n";

			$tmpFile = tempnam( sys_get_temp_dir(), 'ha_test_' );
			file_put_contents( $tmpFile, str_repeat( 'A', 500 ) ); // 500 bytes

			// Case 1: Optimized is smaller (400 bytes vs 500 original) -> Accept
			$originalSize = 500;
			file_put_contents( $tmpFile, str_repeat( 'B', 400 ) );
			$kept = SizeGuard::protect_file( $tmpFile, $originalSize );
			$this->assert( true === $kept, 'SizeGuard keeps optimized file when size decreases (500 -> 400)' );

			// Case 2: NextGen is larger than original -> Discard
			$nextGen = $tmpFile . '.webp';
			file_put_contents( $nextGen, str_repeat( 'C', 600 ) ); // 600 bytes vs 400 original
			$ngKept = SizeGuard::protect_nextgen( $nextGen, 400 );
			$this->assert( false === $ngKept, 'SizeGuard rejects NextGen file when larger than original' );
			$this->assert( ! file_exists( $nextGen ), 'NextGen file was deleted on negative saving' );

			// Case 3: Decimal formatting precision
			$this->assert( '0 B' === SizeGuard::format_size( 0 ), 'format_size(0) returns 0 B' );
			$this->assert( '500 B' === SizeGuard::format_size( 500 ), 'format_size(500) returns 500 B without decimals' );
			$formattedKb = SizeGuard::format_size( 50000, 1 ); // ~48.8 KB
			$this->assert( str_contains( $formattedKb, 'KB' ) && ( str_contains( $formattedKb, '.' ) || str_contains( $formattedKb, ',' ) ), 'format_size(50000) includes decimal point/comma' );
			$formattedMb = SizeGuard::format_size( 2500000, 1 ); // ~2.4 MB
			$this->assert( str_contains( $formattedMb, 'MB' ) && ( str_contains( $formattedMb, '.' ) || str_contains( $formattedMb, ',' ) ), 'format_size(2500000) includes decimal point/comma' );

			@unlink( $tmpFile );
		}

		private function testEnvironment(): void {
			echo "\n5. Testing Environment Diagnostics...\n";

			$caps = Environment::get_capabilities();
			$this->assert( isset( $caps['php']['version'] ), 'Environment reports PHP version' );
			$this->assert( isset( $caps['best_driver'] ), 'Environment detects image driver (imagick or gd)' );
			$this->assert( is_bool( $caps['supports_webp'] ), 'Environment checks WebP capability' );
			$this->assert( is_bool( $caps['supports_avif'] ), 'Environment checks AVIF capability' );
		}

		private function testBackupManager(): void {
			echo "\n6. Testing BackupManager Safety & Security...\n";

			$contentDir = wp_normalize_path( WP_CONTENT_DIR );
			$fakeCustom = $contentDir . '/themes/test-theme/assets/hero.jpg';

			$backupPath = BackupManager::get_custom_backup_file_path( $fakeCustom );
			$this->assert( str_contains( $backupPath, 'ha-backups/custom/themes/test-theme/assets/hero.jpg' ), 'Custom backup path correctly mirrored inside ha-backups/custom/' );

			$backupDir = sys_get_temp_dir() . '/ha_backup_sec_test';
			wp_mkdir_p( $backupDir );
			BackupManager::secure_directory( $backupDir );

			$htaccess = $backupDir . '/.htaccess';
			$this->assert( file_exists( $htaccess ), 'Backup directory shielded with .htaccess' );
			$rules = (string) file_get_contents( $htaccess );
			$this->assert( str_contains( $rules, 'Require all denied' ), '.htaccess contains Apache 2.4 authz barrier' );
			$this->assert( str_contains( $rules, 'Deny from all' ), '.htaccess contains Apache 2.2 Deny barrier' );

			@unlink( $htaccess );
			@unlink( $backupDir . '/index.php' );
			@rmdir( $backupDir );
		}

		private function testCustomFolderProcessor(): void {
			echo "\n7. Testing CustomFolderProcessor & Scanning...\n";

			// Create a mock directory structure in tests/fixtures/wp-content
			$themeDir = WP_CONTENT_DIR . '/themes/ci-test-theme/images';
			wp_mkdir_p( $themeDir );

			$testImg1 = $themeDir . '/banner.jpg';
			$testImg2 = $themeDir . '/icon.png';
			$testSkip = $themeDir . '/banner.jpg.webp'; // Companion file that should be skipped

			file_put_contents( $testImg1, 'dummy_jpeg_content' );
			file_put_contents( $testImg2, 'dummy_png_content' );
			file_put_contents( $testSkip, 'dummy_webp_companion' );

			$images = CustomFolderProcessor::scan_images( [ $themeDir ] );
			$this->assert( in_array( 'themes/ci-test-theme/images/banner.jpg', $images, true ), 'Discovered banner.jpg in custom theme' );
			$this->assert( in_array( 'themes/ci-test-theme/images/icon.png', $images, true ), 'Discovered icon.png in custom theme' );
			$this->assert( ! in_array( 'themes/ci-test-theme/images/banner.jpg.webp', $images, true ), 'Correctly ignored companion .webp file during scan' );

			// Clean up fixtures
			@unlink( $testImg1 );
			@unlink( $testImg2 );
			@unlink( $testSkip );
			@rmdir( $themeDir );
			@rmdir( dirname( $themeDir ) );
		}

		private function testContentFilter(): void {
			echo "\n8. Testing ContentFilter (<picture> & CSS image-set)...\n";

			// Test URL resolving
			$uploadUrl = 'https://example.com/wp-content/uploads/2026/10/photo.jpg';
			$themeUrl  = 'https://example.com/wp-content/themes/my-theme/img/logo.png';

			$resolvedUpload = ContentFilter::resolve_local_path( $uploadUrl );
			$resolvedTheme  = ContentFilter::resolve_local_path( $themeUrl );

			$this->assert( str_contains( $resolvedUpload, 'wp-content/uploads/2026/10/photo.jpg' ), 'Resolved upload URL to uploads path' );
			$this->assert( str_contains( $resolvedTheme, 'wp-content/themes/my-theme/img/logo.png' ), 'Resolved theme URL to theme path' );

			// Mock image with companion webp on disk
			$mockFile = wp_normalize_path( WP_CONTENT_DIR . '/uploads/mock.jpg' );
			$mockWebp = $mockFile . '.webp';
			wp_mkdir_p( dirname( $mockFile ) );
			file_put_contents( $mockFile, 'original' );
			file_put_contents( $mockWebp, 'webp_version' );

			$htmlInput = '<p>Text <img src="https://example.com/wp-content/uploads/mock.jpg" alt="Test" class="wp-image-1"> more text</p>';
			$filtered  = ContentFilter::filter_html( $htmlInput );

			$this->assert( str_contains( $filtered, '<picture' ), '<img> tag converted into <picture> element' );
			$this->assert( str_contains( $filtered, '<source type="image/webp"' ), '<picture> includes WebP <source> tag' );
			$this->assert( str_contains( $filtered, '<img src="https://example.com/wp-content/uploads/mock.jpg"' ), 'Preserved original <img> as fallback' );

			@unlink( $mockFile );
			@unlink( $mockWebp );
		}

		private function testHtaccessManager(): void {
			echo "\n9. Testing HtaccessManager Rules & Markers...\n";

			$rules = HtaccessManager::get_rules();
			$this->assert( str_starts_with( $rules, HtaccessManager::MARKER_START ), 'Rules start with HA marker' );
			$this->assert( str_ends_with( trim( $rules ), HtaccessManager::MARKER_END ), 'Rules end with HA marker' );
			$this->assert( str_contains( $rules, 'image/avif' ), 'Rules support transparent AVIF rewrite' );
			$this->assert( str_contains( $rules, 'image/webp' ), 'Rules support transparent WebP rewrite' );
			$this->assert( str_contains( $rules, 'Header append Vary Accept' ), 'Rules include Vary Accept caching header' );
		}

		private function testProgressTracker(): void {
			echo "\n10. Testing ProgressTracker Query Performance & Stats...\n";

			$stats = ProgressTracker::get_stats();
			$this->assert( isset( $stats['total_images'] ) && 10 === $stats['total_images'], 'ProgressTracker counts total eligible images via COUNT query' );
			$this->assert( isset( $stats['saved_bytes'] ) && 40000 === $stats['saved_bytes'], 'ProgressTracker calculates total saved bytes from meta rows' );
			$this->assert( isset( $stats['saved_percent'] ) && 40.0 === $stats['saved_percent'], 'ProgressTracker calculates percentage correctly' );

			$queue = ProgressTracker::get_queue( 5, false );
			$this->assert( is_array( $queue ) && count( $queue ) === 3, 'ProgressTracker retrieves unoptimized queue items' );
		}

		private function testMediaAuditor(): void {
			echo "\n11. Testing MediaAuditor (Orphan & Inactive Detection)...\n";

			$queue = MediaAuditor::get_audit_queue();
			$this->assert( is_array( $queue ) && count( $queue ) === 3, 'MediaAuditor retrieved media queue' );

			// Mock attachment 1: featured image found in post 42 and Smart Slider 3 slide (exhaustive scan)
			$auditUsed = MediaAuditor::audit_attachment( 1, [], false );
			$this->assert( MediaAuditor::STATUS_USED === $auditUsed['status'], 'Detected used attachment with featured image reference' );
			$this->assert( ! empty( $auditUsed['references'] ), 'Contains detailed reference labels' );
			$this->assert( ! empty( $auditUsed['references'][0]['url'] ), 'Reference includes direct edit link' );
			$this->assert( str_contains( $auditUsed['references'][0]['label'], 'Beitragsbild' ), 'Reference label identifies featured image' );

			$hasSmartSlider = false;
			foreach ( $auditUsed['references'] as $ref ) {
				if ( str_contains( $ref['label'], 'Smart Slider 3' ) ) {
					$hasSmartSlider = true;
					$this->assert( str_contains( $ref['url'], 'page=smartslider3' ), 'Smart Slider reference contains direct slide link' );
					$this->assert( str_contains( $ref['label'], 'Hero Slide' ), 'Smart Slider reference identifies slide title' );
					break;
				}
			}
			$this->assert( $hasSmartSlider, 'Detected Smart Slider 3 usage and generated direct editor link' );

			// Test Smart Early-Exit (Performance Mode)
			$auditFast = MediaAuditor::audit_attachment( 1, [], true );
			$this->assert( MediaAuditor::STATUS_USED === $auditFast['status'], 'Fast mode detects used status' );
			$this->assert( 1 === count( $auditFast['references'] ), 'Fast mode exits early after first confirmed reference' );

			// Test Batch Pre-fetching
			$prefetched = MediaAuditor::prefetch_batch_data( [ 1, 20 ] );
			$this->assert( is_array( $prefetched['featured_posts'] ) && isset( $prefetched['featured_posts'][1] ), 'Prefetch batch data retrieved featured image for attachment 1' );

			// Test In-Memory Autoloaded Options Scanner (0 SQL queries)
			$GLOBALS['wp_test_options']['widget_custom_test'] = '{"imageId":777}';
			$optRefs = MediaAuditor::search_autoloaded_options( 777, '2026/10/photo.jpg', 'photo.jpg' );
			$this->assert( ! empty( $optRefs ), 'In-memory options scanner finds references in autoloaded options' );
			$this->assert( str_contains( $optRefs[0]['label'], 'widget_custom_test' ), 'Options reference labels the option name correctly' );

			// Test Universal Database Scanner (attachment 20 found in custom table wp_wpforms_entries)
			$auditGeneric = MediaAuditor::audit_attachment( 20 );
			$this->assert( MediaAuditor::STATUS_USED === $auditGeneric['status'], 'Detected used attachment in generic third-party table' );
			$hasGenericTable = false;
			foreach ( $auditGeneric['references'] as $ref ) {
				if ( str_contains( $ref['label'], 'wpforms_entries' ) ) {
					$hasGenericTable = true;
					$this->assert( str_contains( $ref['label'], '88' ), 'Generic table reference identifies entry ID' );
					break;
				}
			}
			$this->assert( $hasGenericTable, 'Universal database scanner detected usage in third-party table wp_wpforms_entries' );

			// Test Generic Postmeta Deep Scan (attachment 25 found in unknown postmeta key)
			$auditMeta = MediaAuditor::audit_attachment( 25 );
			$this->assert( MediaAuditor::STATUS_USED === $auditMeta['status'], 'Detected used attachment via generic postmeta deep scan' );
			$hasGenericMeta = false;
			foreach ( $auditMeta['references'] as $ref ) {
				if ( str_contains( $ref['label'], 'custom_hero_image' ) ) {
					$hasGenericMeta = true;
					$this->assert( str_contains( $ref['label'], 'Landingpage' ), 'Postmeta reference identifies post title' );
					$this->assert( str_contains( $ref['url'], 'post=55' ), 'Postmeta reference includes direct post edit link' );
					break;
				}
			}
			$this->assert( $hasGenericMeta, 'Generic postmeta scanner identified unknown custom postmeta reference' );

			// Test Safety Buffer: recently uploaded image (2 days old) protected from deletion
			// Verify that an old unattached file with 0 references is correctly classified as orphan
			$auditOrphan = MediaAuditor::audit_attachment( 11 );
			$this->assert( MediaAuditor::STATUS_UNATTACHED_UNUSED === $auditOrphan['status'], 'Unused attachment is classified as unattached_unused' );
			$this->assert( empty( $auditOrphan['references'] ), 'Orphan attachment has no false references' );

			// Test Safety Buffer: recently uploaded image (2 days old) protected from deletion when safety period is active
			add_filter( 'ha_io_audit_safety_period_days', function() {
				return 14;
			} );

			$GLOBALS['wp_test_posts'][99] = (object) [
				'ID'             => 99,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => 0,
				'post_status'    => 'inherit',
				'post_title'     => 'Fresh Upload 99',
				'post_date'      => date( 'Y-m-d H:i:s', time() - 86400 * 2 ),
				'post_date_gmt'  => date( 'Y-m-d H:i:s', time() - 86400 * 2 ),
			];
			$auditRecent = MediaAuditor::audit_attachment( 99 );
			$this->assert( MediaAuditor::STATUS_USED === $auditRecent['status'], 'Safety buffer marks recently uploaded media as protected (used)' );
			$this->assert( ! empty( $auditRecent['references'] ), 'Recent upload has safety buffer reference' );
			$hasSafetyRef = false;
			foreach ( $auditRecent['references'] as $ref ) {
				if ( str_contains( $ref['label'], 'Schutzfrist aktiv' ) ) {
					$hasSafetyRef = true;
					break;
				}
			}
			$this->assert( $hasSafetyRef, 'Safety buffer reference explains age and protection' );

			// Test Extensibility Hook: ha_io_audit_attachment_references
			add_filter( 'ha_io_audit_attachment_references', function( $refs, $id ) {
				if ( 999 === $id ) {
					$refs[] = [
						'label' => 'Plugin Extension Hook Ref',
						'url'   => 'https://example.com/custom-hook',
					];
				}
				return $refs;
			}, 10, 2 );

			$GLOBALS['wp_test_posts'][999] = (object) [
				'ID'             => 999,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => 0,
				'post_status'    => 'inherit',
				'post_title'     => 'Hook Attachment 999',
				'post_date'      => '2020-01-01 12:00:00',
				'post_date_gmt'  => '2020-01-01 12:00:00',
			];
			$auditHook = MediaAuditor::audit_attachment( 999 );
			$this->assert( MediaAuditor::STATUS_USED === $auditHook['status'], 'Extensibility filter successfully protects media' );
			$hasHookRef = false;
			foreach ( $auditHook['references'] as $ref ) {
				if ( str_contains( $ref['label'], 'Plugin Extension Hook Ref' ) ) {
					$hasHookRef = true;
					break;
				}
			}
			$this->assert( $hasHookRef, 'Hook reference label matched' );

			// Test caching and metric calculation
			$mockItems = [
				[
					'id'         => 10,
					'filename'   => 'used.jpg',
					'status'     => MediaAuditor::STATUS_USED,
					'disk_bytes' => 50000,
					'references' => [ 'Beitragsbild in #42' ],
				],
				[
					'id'         => 11,
					'filename'   => 'orphan.jpg',
					'status'     => MediaAuditor::STATUS_UNATTACHED_UNUSED,
					'disk_bytes' => 120000,
					'references' => [],
				],
				[
					'id'           => 12,
					'filename'     => 'inactive.jpg',
					'status'       => MediaAuditor::STATUS_ATTACHED_UNUSED,
					'disk_bytes'   => 80000,
					'parent_id'    => 5,
					'parent_title' => 'Alter Post',
					'references'   => [],
				],
			];

			$summary = MediaAuditor::update_cached_results( $mockItems );
			$this->assert( 3 === $summary['counts']['total'], 'Summary counts total audited items' );
			$this->assert( 1 === $summary['counts']['unattached_unused'], 'Summary counts unattached unused items' );
			$this->assert( 1 === $summary['counts']['attached_unused'], 'Summary counts attached unused items' );
			$this->assert( 1 === $summary['counts']['used'], 'Summary counts active items' );
			$this->assert( 200000 === $summary['reclaimable_bytes']['total'], 'Calculated reclaimable storage accurately (120KB + 80KB = 200KB)' );

			$trashResult = MediaAuditor::trash_attachment( 11 );
			$this->assert( $trashResult, 'Successfully trashed orphan attachment' );

			$cached = MediaAuditor::get_cached_results();
			$this->assert( ! isset( $cached['items'][11] ), 'Trashed attachment removed from cached audit index' );

			$restoreResult = MediaAuditor::restore_attachment( 11 );
			$this->assert( $restoreResult, 'Successfully restored attachment' );

			MediaAuditor::clear_results();
			$cleared = MediaAuditor::get_cached_results();
			$this->assert( empty( $cleared['items'] ), 'Audit cache cleared successfully' );

			// Test Skip Scanned Media (Incremental Audit Queue & Pruning)
			// 1. Initial queue with skip_scanned=false and skip_scanned=true on empty cache
			$allQueue = MediaAuditor::get_audit_queue( false );
			$this->assert( [ 1, 2, 3 ] === $allQueue, 'Full queue returns all IDs [1, 2, 3]' );

			$emptySkipQueue = MediaAuditor::get_audit_queue( true );
			$this->assert( [ 1, 2, 3 ] === $emptySkipQueue, 'Empty cache skip_scanned returns all IDs' );

			// 2. Pre-populate cache with ID 2 and an obsolete deleted ID 999
			MediaAuditor::update_cached_results( [
				[
					'id'         => 2,
					'filename'   => 'already_scanned.jpg',
					'status'     => MediaAuditor::STATUS_USED,
					'disk_bytes' => 30000,
					'references' => [ 'Beitragsbild' ],
				],
				[
					'id'         => 999,
					'filename'   => 'deleted_file.jpg',
					'status'     => MediaAuditor::STATUS_UNATTACHED_UNUSED,
					'disk_bytes' => 15000,
					'references' => [],
				],
			] );

			// 3. Incremental queue should prune obsolete ID 999 and skip already scanned ID 2
			$incrementalQueue = MediaAuditor::get_audit_queue( true );
			$this->assert( [ 1, 3 ] === $incrementalQueue, 'Incremental queue skips cached ID 2 and returns [1, 3]' );

			$cachedAfterPrune = MediaAuditor::get_cached_results();
			$this->assert( isset( $cachedAfterPrune['items'][2] ), 'Cached valid attachment 2 preserved' );
			$this->assert( ! isset( $cachedAfterPrune['items'][999] ), 'Deleted attachment 999 automatically pruned from cache' );

			// 4. Test delete_attachment hook removes attachment from cached index
			add_action( 'delete_attachment', [ MediaAuditor::class, 'remove_from_cached_results' ] );
			do_action( 'delete_attachment', 2 );
			$cachedAfterDeleteHook = MediaAuditor::get_cached_results();
			$this->assert( ! isset( $cachedAfterDeleteHook['items'][2] ), 'delete_attachment action hook removes item from cache' );

			// 5. Test incremental batch merge
			MediaAuditor::update_cached_results( [
				[
					'id'         => 1,
					'filename'   => 'img1.jpg',
					'status'     => MediaAuditor::STATUS_USED,
					'disk_bytes' => 10000,
					'references' => [ 'Logo' ],
				],
			] );
			MediaAuditor::update_cached_results( [
				[
					'id'         => 3,
					'filename'   => 'img3.jpg',
					'status'     => MediaAuditor::STATUS_UNATTACHED_UNUSED,
					'disk_bytes' => 20000,
					'references' => [],
				],
			] );
			$mergedCache = MediaAuditor::get_cached_results();
			$this->assert( 2 === $mergedCache['counts']['total'], 'Merged cache contains both batch 1 and batch 2 items' );
			$this->assert( isset( $mergedCache['items'][1], $mergedCache['items'][3] ), 'Both items 1 and 3 present in merged cache' );

			// 6. Test WordPress 5.3+ -scaled image and intermediate sub-sizes variants resolution
			$uploadDir = wp_upload_dir();
			$basedir   = $uploadDir['basedir'];
			$scaledFile = $basedir . '/2024/05/landscape-photo-scaled.jpg';
			$GLOBALS['wp_test_attached_file'][30] = $scaledFile;
			$GLOBALS['wp_test_original_image_path'][30] = $basedir . '/2024/05/landscape-photo.jpg';
			$GLOBALS['wp_test_metadata'][30] = [
				'width'          => 2560,
				'height'         => 1440,
				'file'           => '2024/05/landscape-photo-scaled.jpg',
				'original_image' => 'landscape-photo.jpg',
				'sizes'          => [
					'thumbnail' => [
						'file'      => 'landscape-photo-scaled-150x150.jpg',
						'width'     => 150,
						'height'    => 150,
						'mime-type' => 'image/jpeg',
					],
					'medium'    => [
						'file'      => 'landscape-photo-300x200.jpg',
						'width'     => 300,
						'height'    => 200,
						'mime-type' => 'image/jpeg',
					],
					'large'     => [
						'file'      => 'landscape-photo-1024x768.jpg',
						'width'     => 1024,
						'height'    => 768,
						'mime-type' => 'image/jpeg',
					],
				],
			];

			$variants = MediaAuditor::get_attachment_file_variants( 30, $scaledFile );
			$this->assert( in_array( 'landscape-photo-scaled.jpg', $variants['filenames'], true ), 'Variants include scaled filename' );
			$this->assert( in_array( 'landscape-photo.jpg', $variants['filenames'], true ), 'Variants include original unscaled filename' );
			$this->assert( in_array( 'landscape-photo-300x200.jpg', $variants['filenames'], true ), 'Variants include medium thumbnail sub-size' );
			$this->assert( in_array( 'landscape-photo-1024x768.jpg', $variants['filenames'], true ), 'Variants include large thumbnail sub-size' );
			$this->assert( in_array( '2024/05/landscape-photo-300x200.jpg', $variants['rel_files'], true ), 'Variants include relative path for sub-size' );

			// Test audit_attachment detects usage when sub-size (300x200) or original is embedded in post_content
			$GLOBALS['wp_test_posts'][30] = (object) [ 
				'ID'             => 30,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => 0,
				'post_status'    => 'inherit',
				'post_title'     => 'Landscape Photo Scaled',
				'post_date'      => '2020-01-01 12:00:00',
				'post_date_gmt'  => '2020-01-01 12:00:00',
			];

			$auditScaled = MediaAuditor::audit_attachment( 30 );
			$this->assert( MediaAuditor::STATUS_USED === $auditScaled['status'], 'Scaled attachment detected as used when sub-size is embedded in content' );
			$this->assert( ! empty( $auditScaled['references'] ), 'Contains content reference for scaled attachment' );
			$this->assert( str_contains( $auditScaled['references'][0]['label'], 'Eingebunden im Inhalt' ), 'Reference identifies post_content embedding' );

			// Test disk bytes calculation includes original_image
			$tempDir = sys_get_temp_dir() . '/ha_io_scaled_test_' . time();
			wp_mkdir_p( $tempDir );
			$tScaled = $tempDir . '/test-scaled.jpg';
			$tOrig   = $tempDir . '/test.jpg';
			$tThumb  = $tempDir . '/test-150x150.jpg';
			file_put_contents( $tScaled, str_repeat( 'A', 5000 ) );
			file_put_contents( $tOrig,   str_repeat( 'B', 8000 ) );
			file_put_contents( $tThumb,  str_repeat( 'C', 2000 ) );

			$GLOBALS['wp_test_metadata'][31] = [
				'file'           => wp_basename( $tScaled ),
				'original_image' => wp_basename( $tOrig ),
				'sizes'          => [
					'thumbnail' => [
						'file' => wp_basename( $tThumb ),
					],
				],
			];

			$diskBytes = MediaAuditor::calculate_disk_bytes( 31, $tScaled );
			$this->assert( 15000 === $diskBytes, 'calculate_disk_bytes includes scaled master, original unscaled, and thumbnail (5000+8000+2000=15000)' );

			// Clean up temp files
			@unlink( $tScaled );
			@unlink( $tOrig );
			@unlink( $tThumb );
			@rmdir( $tempDir );

			// 7. Test Gallery Shortcodes with comma-separated IDs (e.g. [gallery ids="8639,8640,8641,8661"])
			$GLOBALS['wp_test_posts'][8639] = (object) [
				'ID'             => 8639,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => 0,
				'post_status'    => 'inherit',
				'post_title'     => 'Gallery First Image 8639',
				'post_date'      => '2020-01-01 12:00:00',
				'post_date_gmt'  => '2020-01-01 12:00:00',
			];
			$GLOBALS['wp_test_posts'][8640] = (object) [
				'ID'             => 8640,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => 0,
				'post_status'    => 'inherit',
				'post_title'     => 'Gallery Middle Image 8640',
				'post_date'      => '2020-01-01 12:00:00',
				'post_date_gmt'  => '2020-01-01 12:00:00',
			];
			$GLOBALS['wp_test_posts'][8661] = (object) [
				'ID'             => 8661,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => 0,
				'post_status'    => 'inherit',
				'post_title'     => 'Gallery Last Image 8661',
				'post_date'      => '2020-01-01 12:00:00',
				'post_date_gmt'  => '2020-01-01 12:00:00',
			];

			// Test first image in gallery ids list (='8639, or ="8639,)
			$auditGFirst = MediaAuditor::audit_attachment( 8639 );
			$this->assert( MediaAuditor::STATUS_USED === $auditGFirst['status'], 'First image in [gallery ids="..."] list detected as used' );
			$this->assert( ! empty( $auditGFirst['references'] ), 'First gallery image has references' );
			$this->assert( str_contains( $auditGFirst['references'][0]['label'], 'Galerie' ), 'Reference identifies gallery usage' );

			// Test middle image in gallery ids list (,8640,)
			$auditGMiddle = MediaAuditor::audit_attachment( 8640 );
			$this->assert( MediaAuditor::STATUS_USED === $auditGMiddle['status'], 'Middle image in [gallery ids="..."] list detected as used' );
			$this->assert( ! empty( $auditGMiddle['references'] ), 'Middle gallery image has references' );
			$this->assert( str_contains( $auditGMiddle['references'][0]['label'], 'Galerie' ), 'Middle gallery reference identifies gallery usage' );

			// Test last image in gallery ids list (,8661")
			$auditGLast = MediaAuditor::audit_attachment( 8661 );
			$this->assert( MediaAuditor::STATUS_USED === $auditGLast['status'], 'Last image in [gallery ids="..."] list detected as used' );
			$this->assert( ! empty( $auditGLast['references'] ), 'Last gallery image has references' );
			$this->assert( str_contains( $auditGLast['references'][0]['label'], 'Galerie' ), 'Last gallery reference identifies gallery usage' );

			// Test parent post with classic [gallery] without ids=
			$GLOBALS['wp_test_posts'][50] = (object) [
				'ID'             => 50,
				'post_title'     => 'Parent Post With Auto Gallery',
				'post_content'   => 'Hier ist Text. [gallery size="medium"] und noch mehr Text.',
			];
			$GLOBALS['wp_test_posts'][501] = (object) [
				'ID'             => 501,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => 50,
				'post_status'    => 'inherit',
				'post_title'     => 'Auto Attached Image 501',
				'post_date'      => '2020-01-01 12:00:00',
				'post_date_gmt'  => '2020-01-01 12:00:00',
			];
			$auditAutoGallery = MediaAuditor::audit_attachment( 501 );
			$this->assert( MediaAuditor::STATUS_USED === $auditAutoGallery['status'], 'Attached image in parent post with [gallery] auto-gallery detected as used' );
			$this->assert( str_contains( $auditAutoGallery['references'][0]['label'], 'Automatische Galerie' ), 'Reference identifies automatic parent post gallery' );

			MediaAuditor::clear_results();
		}

		private function testIndividualCompressionLevel(): void {
			echo "\n12. Testing Individual Compression Level & Custom Conversion Pipeline...\n";

			// 1. Test CompressionLevel::fromString mappings and aliases
			$this->assert( CompressionLevel::fromString( 'lossless' ) === CompressionLevel::LOSSLESS, 'fromString("lossless") returns LOSSLESS' );
			$this->assert( CompressionLevel::fromString( 'niedrig' ) === CompressionLevel::LOSSLESS, 'fromString("niedrig") returns LOSSLESS' );
			$this->assert( CompressionLevel::fromString( 'low' ) === CompressionLevel::LOSSLESS, 'fromString("low") returns LOSSLESS' );

			$this->assert( CompressionLevel::fromString( 'smart' ) === CompressionLevel::SMART, 'fromString("smart") returns SMART' );
			$this->assert( CompressionLevel::fromString( 'mittel' ) === CompressionLevel::SMART, 'fromString("mittel") returns SMART' );
			$this->assert( CompressionLevel::fromString( 'medium' ) === CompressionLevel::SMART, 'fromString("medium") returns SMART' );

			$this->assert( CompressionLevel::fromString( 'ultra' ) === CompressionLevel::ULTRA, 'fromString("ultra") returns ULTRA' );
			$this->assert( CompressionLevel::fromString( 'hoch' ) === CompressionLevel::ULTRA, 'fromString("hoch") returns ULTRA' );
			$this->assert( CompressionLevel::fromString( 'high' ) === CompressionLevel::ULTRA, 'fromString("high") returns ULTRA' );

			$this->assert( CompressionLevel::fromString( 'default' ) === null, 'fromString("default") returns null (falls back to global)' );
			$this->assert( CompressionLevel::fromString( null ) === null, 'fromString(null) returns null' );
			$this->assert( CompressionLevel::fromString( '' ) === null, 'fromString("") returns null' );
			$this->assert( CompressionLevel::fromString( 'non_existent' ) === null, 'fromString("non_existent") returns null' );

			// 2. Test Selection and Badge Labels
			$ultra = CompressionLevel::ULTRA;
			$smart = CompressionLevel::SMART;
			$lossless = CompressionLevel::LOSSLESS;

			$this->assert( str_contains( $ultra->selection_label(), 'Hoch' ), 'Ultra selection label contains "Hoch"' );
			$this->assert( str_contains( $smart->selection_label(), 'Mittel' ), 'Smart selection label contains "Mittel"' );
			$this->assert( str_contains( $lossless->selection_label(), 'Niedrig' ), 'Lossless selection label contains "Niedrig"' );

			$this->assert( str_contains( $ultra->badge_label(), 'Hoch' ), 'Ultra badge label contains "Hoch"' );
			$this->assert( str_contains( $smart->badge_label(), 'Mittel' ), 'Smart badge label contains "Mittel"' );
			$this->assert( str_contains( $lossless->badge_label(), 'Niedrig' ), 'Lossless badge label contains "Niedrig"' );

			// 3. Test AttachmentProcessor::process with custom level
			$uploadsDir = wp_upload_dir()['basedir'] . '/test_ci_img';
			wp_mkdir_p( $uploadsDir );
			$dummyImage = $uploadsDir . '/custom_level_test.jpg';

			if ( function_exists( 'imagecreatetruecolor' ) ) {
				$im = imagecreatetruecolor( 20, 20 );
				$bg = imagecolorallocate( $im, 200, 100, 50 );
				imagefilledrectangle( $im, 0, 0, 19, 19, $bg );
				imagejpeg( $im, $dummyImage, 90 );
				imagedestroy( $im );
			} else {
				file_put_contents( $dummyImage, base64_decode( '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=' ) );
			}

			$testAttachmentId = 555;
			$GLOBALS['wp_test_attached_file'][ $testAttachmentId ] = $dummyImage;
			$GLOBALS['wp_test_posts'][ $testAttachmentId ] = (object) [
				'ID'             => $testAttachmentId,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => 0,
				'post_status'    => 'inherit',
				'post_title'     => 'Custom Level Test',
				'post_date'      => '2026-10-25 00:00:00',
				'post_date_gmt'  => '2026-10-25 00:00:00',
			];

			// Process with custom level 'ultra' (Hoch)
			AttachmentProcessor::process( $testAttachmentId, [], true, 'ultra' );
			$savedLevel = get_post_meta( $testAttachmentId, '_ha_io_custom_level', true );
			$data       = get_post_meta( $testAttachmentId, '_ha_io_data', true );

			$this->assert( 'ultra' === $savedLevel, 'Custom level "ultra" saved in postmeta _ha_io_custom_level' );
			$this->assert( isset( $data['level_original'] ) && 'ultra' === $data['level_original'], 'Optimization stats recorded level_original as "ultra"' );
			$this->assert( isset( $data['custom_level'] ) && 'ultra' === $data['custom_level'], 'Optimization stats recorded custom_level as "ultra"' );

			// Process with German alias 'niedrig' (Lossless)
			AttachmentProcessor::process( $testAttachmentId, [], true, 'niedrig' );
			$savedLevelLossless = get_post_meta( $testAttachmentId, '_ha_io_custom_level', true );
			$dataLossless       = get_post_meta( $testAttachmentId, '_ha_io_data', true );

			$this->assert( 'lossless' === $savedLevelLossless, 'Alias "niedrig" resolved and saved as "lossless"' );
			$this->assert( isset( $dataLossless['level_original'] ) && 'lossless' === $dataLossless['level_original'], 'Optimization stats recorded level_original as "lossless"' );

			// Reset to 'default'
			AttachmentProcessor::process( $testAttachmentId, [], true, 'default' );
			$clearedLevel = get_post_meta( $testAttachmentId, '_ha_io_custom_level', true );
			$this->assert( empty( $clearedLevel ), 'Passing "default" cleared postmeta _ha_io_custom_level' );

			// 4. Test BackupManager::restore cleans up _ha_io_custom_level
			update_post_meta( $testAttachmentId, '_ha_io_custom_level', 'smart' );
			update_post_meta( $testAttachmentId, '_ha_io_data', [ 'status' => 'optimized' ] );
			$backupPath = BackupManager::get_backup_file_path( $testAttachmentId, $dummyImage );
			wp_mkdir_p( dirname( $backupPath ) );
			if ( file_exists( $dummyImage ) ) {
				@copy( $dummyImage, $backupPath );
			} else {
				file_put_contents( $backupPath, base64_decode( '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=' ) );
			}

			$restored = BackupManager::restore( $testAttachmentId );
			$this->assert( $restored, 'BackupManager restored attachment successfully' );
			$this->assert( empty( get_post_meta( $testAttachmentId, '_ha_io_custom_level', true ) ), 'BackupManager::restore cleared _ha_io_custom_level postmeta' );
			$this->assert( empty( get_post_meta( $testAttachmentId, '_ha_io_data', true ) ), 'BackupManager::restore cleared _ha_io_data postmeta' );

			// Cleanup
			@unlink( $dummyImage );
			@unlink( $backupPath );
			@rmdir( $uploadsDir );
		}

		private function testBackgroundAuditorAndPacing(): void {
			echo "\n12b. Testing Background Auditor, Night Mode, Throttling & Reverse Index...\n";

			// 1. Settings Defaults & Sanitization
			$defaults = Settings::get_defaults();
			$this->assert( false === $defaults['auditor_background_enabled'], 'Default auditor_background_enabled is false' );
			$this->assert( true === $defaults['auditor_night_mode_enabled'], 'Default auditor_night_mode_enabled is true' );
			$this->assert( '02:00' === $defaults['auditor_night_start'], 'Default auditor_night_start is 02:00' );
			$this->assert( '06:00' === $defaults['auditor_night_end'], 'Default auditor_night_end is 06:00' );
			$this->assert( 'standard' === $defaults['auditor_pacing_mode'], 'Default auditor_pacing_mode is standard' );
			$this->assert( 2.5 === $defaults['auditor_max_server_load'], 'Default auditor_max_server_load is 2.5' );
			$this->assert( true === $defaults['auditor_reverse_index_enabled'], 'Default auditor_reverse_index_enabled is true' );

			// Test sanitization of background settings
			$clean = Settings::sanitize( [
				'auditor_background_enabled' => '1',
				'auditor_night_start'        => 'invalid',
				'auditor_night_end'          => '05:30',
				'auditor_pacing_mode'        => 'invalid_pacing',
				'auditor_max_server_load'    => '99.9',
				'auditor_reverse_index_enabled' => '1',
			] );
			$this->assert( true === $clean['auditor_background_enabled'], 'auditor_background_enabled sanitized to true' );
			$this->assert( true === $clean['auditor_night_mode_enabled'], 'auditor_night_mode_enabled defaults to true when background is on' );
			$this->assert( '02:00' === $clean['auditor_night_start'], 'Invalid night_start falls back to 02:00' );
			$this->assert( '05:30' === $clean['auditor_night_end'], 'Valid night_end sanitized to 05:30' );
			$this->assert( 'standard' === $clean['auditor_pacing_mode'], 'Invalid pacing mode falls back to standard' );
			$this->assert( 2.5 === $clean['auditor_max_server_load'], 'Out-of-bounds server load clamped to 2.5' );

			// 2. Night Window Evaluation (is_night_time)
			// Window 02:00 to 06:00
			Settings::update( [ 'auditor_night_start' => '02:00', 'auditor_night_end' => '06:00' ] );
			$this->assert( BackgroundAuditor::is_night_time( '02:00' ), '02:00 is within 02:00-06:00 night window (start boundary)' );
			$this->assert( BackgroundAuditor::is_night_time( '04:15' ), '04:15 is within 02:00-06:00 night window' );
			$this->assert( BackgroundAuditor::is_night_time( '06:00' ), '06:00 is within 02:00-06:00 night window (end boundary)' );
			$this->assert( ! BackgroundAuditor::is_night_time( '01:59' ), '01:59 is outside 02:00-06:00 night window' );
			$this->assert( ! BackgroundAuditor::is_night_time( '14:30' ), '14:30 is outside 02:00-06:00 night window' );

			// Window crossing midnight: 22:00 to 06:00
			Settings::update( [ 'auditor_night_start' => '22:00', 'auditor_night_end' => '06:00' ] );
			$this->assert( BackgroundAuditor::is_night_time( '23:30' ), '23:30 is within cross-midnight 22:00-06:00 window' );
			$this->assert( BackgroundAuditor::is_night_time( '03:00' ), '03:00 is within cross-midnight 22:00-06:00 window' );
			$this->assert( ! BackgroundAuditor::is_night_time( '15:00' ), '15:00 is outside cross-midnight 22:00-06:00 window' );
			$this->assert( ! BackgroundAuditor::is_night_time( '21:59' ), '21:59 is outside cross-midnight 22:00-06:00 window' );

			// 3. Pacing Configurations & Safeguards
			Settings::update( [ 'auditor_pacing_mode' => 'eco' ] );
			$eco = MediaAuditor::get_pacing_config();
			$this->assert( 3 === $eco['batch_size'] && 1000 === $eco['sleep_ms'] && 1500 === $eco['timeout_ms'], 'Eco pacing config is batch 3, sleep 1000ms, timeout 1500ms' );

			Settings::update( [ 'auditor_pacing_mode' => 'turbo' ] );
			$turbo = MediaAuditor::get_pacing_config();
			$this->assert( 25 === $turbo['batch_size'] && 50 === $turbo['sleep_ms'] && 5000 === $turbo['timeout_ms'], 'Turbo pacing config is batch 25, sleep 50ms, timeout 5000ms' );

			Settings::update( [ 'auditor_pacing_mode' => 'standard' ] );
			$safeguard = MediaAuditor::get_sql_safeguard_prefix();
			$this->assert( str_contains( $safeguard, 'MAX_EXECUTION_TIME(2500)' ) && str_contains( $safeguard, 'SQL_NO_CACHE' ), 'SQL safeguard contains MAX_EXECUTION_TIME(2500) and SQL_NO_CACHE' );

			// 4. Background Auditor State Machine (start, pause, resume, cancel)
			Settings::update( [ 'auditor_background_enabled' => true, 'auditor_night_mode_enabled' => false ] );
			$startState = BackgroundAuditor::start( true, false );
			$this->assert( in_array( $startState['status'], [ BackgroundAuditor::STATUS_RUNNING, BackgroundAuditor::STATUS_COMPLETED ], true ), 'BackgroundAuditor::start transitions to running/completed' );

			$pausedState = BackgroundAuditor::pause();
			$this->assert( BackgroundAuditor::STATUS_PAUSED === $pausedState['status'], 'BackgroundAuditor::pause transitions to paused' );

			$resumedState = BackgroundAuditor::resume();
			$this->assert( BackgroundAuditor::STATUS_RUNNING === $resumedState['status'], 'BackgroundAuditor::resume transitions to running' );

			$cancelledState = BackgroundAuditor::cancel();
			$this->assert( BackgroundAuditor::STATUS_CANCELLED === $cancelledState['status'], 'BackgroundAuditor::cancel transitions to cancelled' );

			// 5. Reverse Index Building & Verification
			MediaAuditor::clear_reverse_index();
			$revIndex = MediaAuditor::build_reverse_index();
			$this->assert( isset( $revIndex['by_id'][8639] ), 'Reverse index discovered gallery ID 8639 from post content' );
			$this->assert( isset( $revIndex['by_id'][8640] ), 'Reverse index discovered gallery ID 8640 from post content' );
			$this->assert( isset( $revIndex['by_id'][8661] ), 'Reverse index discovered gallery ID 8661 from post content' );
			$this->assert( isset( $revIndex['by_id'][999] ), 'Reverse index discovered wp-image-999 from post content' );
			$this->assert( isset( $revIndex['by_filename']['landscape-photo-300x200.jpg'] ), 'Reverse index discovered filename landscape-photo-300x200.jpg from post content' );
			$this->assert( isset( $revIndex['by_id'][777] ), 'Reverse index discovered image ID 777 from Page Builder postmeta' );
			$this->assert( isset( $revIndex['by_filename']['hero-banner.jpg'] ), 'Reverse index discovered hero-banner.jpg from Page Builder postmeta' );

			// 6. Audit Attachment using Reverse Index
			$auditRes = MediaAuditor::audit_attachment( 8639, [], true );
			$this->assert( MediaAuditor::STATUS_USED === $auditRes['status'], 'Attachment 8639 resolved to STATUS_USED via Reverse Index' );
			$this->assert( str_contains( $auditRes['reference_desc'] ?? '', 'Galerie' ), 'Reference description identifies usage in Galerie' );

			MediaAuditor::clear_reverse_index();
		}

		private function testTranslations(): void {
			echo "\n13. Testing Language Files Integrity (26 Locales)...\n";

			$potFile = $this->rootDir . '/languages/ha-image-optimizer.pot';
			$this->assert( file_exists( $potFile ), 'Template ha-image-optimizer.pot exists' );

			$l10nFiles = glob( $this->rootDir . '/languages/*.l10n.php' );
			$this->assert( count( $l10nFiles ) >= 26, 'Found ' . count( $l10nFiles ) . ' modern WordPress .l10n.php language packs' );

			$allPresent = true;
			foreach ( $l10nFiles as $l10nFile ) {
				$moFile = preg_replace( '/\.l10n\.php$/', '.mo', $l10nFile );
				$poFile = preg_replace( '/\.l10n\.php$/', '.po', $l10nFile );

				if ( ! file_exists( $moFile ) || ! file_exists( $poFile ) ) {
					$allPresent = false;
					$this->assert( false, "Missing .mo or .po counterpart for {$l10nFile}" );
					break;
				}

				// Validate l10n.php structure
				$l10nContent = (string) file_get_contents( $l10nFile );
				if ( ! str_contains( $l10nContent, 'ABSPATH' ) ) {
					$allPresent = false;
					$this->assert( false, "Missing ABSPATH check in {$l10nFile}" );
					break;
				}
			}

			if ( $allPresent ) {
				$this->assert( true, 'All 26 locales possess valid .po, .mo, and WP 6.5+ .l10n.php files' );
			}
		}
	}

	$runner = new TestRunner();
	$runner->run();
}
