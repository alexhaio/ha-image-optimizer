<?php
/**
 * Media Library Column & Attachment Detail Box.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Admin;

use ha\ImageOptimizer\Media\BackupManager;
use ha\ImageOptimizer\Media\AttachmentProcessor;
use ha\ImageOptimizer\Common\CompressionLevel;
use ha\ImageOptimizer\Engine\SizeGuard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MediaColumn
 */
class MediaColumn {

	/**
	 * Register media library hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'manage_upload_columns', [ __CLASS__, 'add_columns' ] );
		add_action( 'manage_media_custom_column', [ __CLASS__, 'render_column_content' ], 10, 2 );
		add_filter( 'attachment_fields_to_edit', [ __CLASS__, 'add_attachment_fields' ], 10, 2 );
	}

	/**
	 * Add "HA Optimizer" column header.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public static function add_columns( array $columns ): array {
		$columns['ha_io_optimization'] = esc_html__( 'HA Optimizer', 'ha-image-optimizer' );
		return $columns;
	}

	/**
	 * Render custom column cell content.
	 *
	 * @param string $column_name Column key.
	 * @param int    $post_id Attachment ID.
	 * @return void
	 */
	public static function render_column_content( string $column_name, int $post_id ): void {
		if ( 'ha_io_optimization' !== $column_name ) {
			return;
		}

		$mime = get_post_mime_type( $post_id );
		if ( ! in_array( $mime, AttachmentProcessor::SUPPORTED_MIMES, true ) ) {
			echo '<span class="ha-io-na">—</span>';
			return;
		}

		$data         = get_post_meta( $post_id, '_ha_io_data', true );
		$custom_level = (string) get_post_meta( $post_id, '_ha_io_custom_level', true );
		if ( empty( $custom_level ) && ! empty( $data['custom_level'] ) ) {
			$custom_level = (string) $data['custom_level'];
		}
		$level_enum = CompressionLevel::fromString( $custom_level );

		$meta        = wp_get_attachment_metadata( $post_id );
		$dim_w       = ! empty( $meta['width'] ) ? (int) $meta['width'] : 0;
		$dim_h       = ! empty( $meta['height'] ) ? (int) $meta['height'] : 0;
		$sizes_count = ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? count( $meta['sizes'] ) : 0;

		if ( empty( $data ) || ! is_array( $data ) ) {
			$file        = get_attached_file( $post_id );
			$file_size   = ( $file && file_exists( $file ) ) ? (int) filesize( $file ) : 0;
			$total_unopt = $file_size;
			if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) && $file ) {
				$base_dir = trailingslashit( dirname( $file ) );
				foreach ( $meta['sizes'] as $sz ) {
					if ( ! empty( $sz['file'] ) && file_exists( $base_dir . $sz['file'] ) ) {
						$total_unopt += (int) filesize( $base_dir . $sz['file'] );
					}
				}
			}
			?>
			<div class="ha-io-col-unoptimized" data-id="<?php echo esc_attr( $post_id ); ?>">
				<div class="ha-io-status-row">
					<span class="ha-io-badge ha-io-badge-gray"><?php esc_html_e( 'Unoptimized', 'ha-image-optimizer' ); ?></span>
					<?php if ( $total_unopt > 0 ) : ?>
						<?php
						$unopt_title = sprintf(
							/* translators: 1: original file size, 2: number of intermediate sizes, 3: total file size */
							__( 'Original: %1$s, Total incl. %2$d sub-sizes: %3$s', 'ha-image-optimizer' ),
							SizeGuard::format_size( $file_size, 1 ),
							$sizes_count,
							SizeGuard::format_size( $total_unopt, 1 )
						);
						?>
						<span class="ha-io-filesize-tag" title="<?php echo esc_attr( $unopt_title ); ?>">
							<strong><?php echo esc_html( SizeGuard::format_size( $total_unopt, 1 ) ); ?></strong>
						</span>
					<?php endif; ?>
				</div>

				<?php if ( $dim_w && $dim_h ) : ?>
					<div class="ha-io-dimensions-info">
						<span class="ha-io-dim-label"><?php echo esc_html( $dim_w . ' × ' . $dim_h . ' px' ); ?></span>
						<?php if ( $sizes_count > 0 ) : ?>
							<span class="ha-io-subsizes-label">(+<?php echo esc_html( (string) $sizes_count ); ?>)</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="ha-io-level-select-wrap">
					<select class="ha-io-level-select" data-id="<?php echo esc_attr( $post_id ); ?>" title="<?php esc_attr_e( 'Compression level for this image', 'ha-image-optimizer' ); ?>">
						<option value="default"><?php esc_html_e( 'Default (Global)', 'ha-image-optimizer' ); ?></option>
						<option value="ultra"><?php esc_html_e( 'High (Maximum)', 'ha-image-optimizer' ); ?></option>
						<option value="smart"><?php esc_html_e( 'Medium (Balanced)', 'ha-image-optimizer' ); ?></option>
						<option value="lossless"><?php esc_html_e( 'Low (Lossless)', 'ha-image-optimizer' ); ?></option>
					</select>
				</div>

				<div class="ha-io-actions">
					<button type="button" class="button button-small button-primary ha-io-btn-optimize" data-id="<?php echo esc_attr( $post_id ); ?>">
						<?php esc_html_e( 'Optimize', 'ha-image-optimizer' ); ?>
					</button>
				</div>
			</div>
			<?php
			return;
		}

		$total_before = (int) ( $data['total']['before'] ?? 0 );
		$total_after  = (int) ( $data['total']['after'] ?? 0 );
		$saved_bytes  = (int) ( $data['total']['saved'] ?? 0 );
		$percent      = (float) ( $data['total']['percent'] ?? 0.0 );
		$has_backup   = BackupManager::has_backup( $post_id );
		$has_webp     = ! empty( $data['original']['webp_size'] );
		$has_avif     = ! empty( $data['original']['avif_size'] );
		$level_badge  = $level_enum ? $level_enum->badge_label() : '';

		?>
		<div class="ha-io-col-optimized" data-id="<?php echo esc_attr( $post_id ); ?>">
			<!-- Before -> After Size Flow -->
			<div class="ha-io-size-flow" title="<?php esc_attr_e( 'Total Storage (Before → After)', 'ha-image-optimizer' ); ?>">
				<span class="ha-io-size-before"><?php echo esc_html( SizeGuard::format_size( $total_before, 1 ) ); ?></span>
				<span class="ha-io-size-arrow">→</span>
				<span class="ha-io-size-after"><strong><?php echo esc_html( SizeGuard::format_size( $total_after, 1 ) ); ?></strong></span>
			</div>

			<!-- Savings & Badges -->
			<?php
			$saved_title = sprintf(
				/* translators: 1: saved bytes formatted, 2: saved percentage */
				__( '%1$s saved (-%2$s%%)', 'ha-image-optimizer' ),
				SizeGuard::format_size( $saved_bytes, 1 ),
				number_format_i18n( $percent, 1 )
			);
			?>
			<div class="ha-io-badge-group">
				<span class="ha-io-badge ha-io-badge-green" title="<?php echo esc_attr( $saved_title ); ?>">
					-<?php echo esc_html( number_format_i18n( $percent, 1 ) ); ?>%
					<span class="ha-io-bytes">(<?php echo esc_html( SizeGuard::format_size( $saved_bytes, 1 ) ); ?>)</span>
				</span>
				<?php if ( $level_badge ) : ?>
					<span class="ha-io-badge ha-io-badge-orange" title="<?php echo esc_attr( $level_enum ? $level_enum->selection_label() : '' ); ?>">
						<?php echo esc_html( $level_badge ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $has_webp ) : ?>
					<span class="ha-io-badge ha-io-badge-blue">WebP</span>
				<?php endif; ?>
				<?php if ( $has_avif ) : ?>
					<span class="ha-io-badge ha-io-badge-purple">AVIF</span>
				<?php endif; ?>
			</div>

			<?php if ( $dim_w && $dim_h ) : ?>
				<div class="ha-io-dimensions-info">
					<span class="ha-io-dim-label"><?php echo esc_html( $dim_w . ' × ' . $dim_h . ' px' ); ?></span>
					<?php if ( $sizes_count > 0 ) : ?>
						<span class="ha-io-subsizes-label">(+<?php echo esc_html( (string) $sizes_count ); ?>)</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="ha-io-level-select-wrap">
				<select class="ha-io-level-select" data-id="<?php echo esc_attr( $post_id ); ?>" title="<?php esc_attr_e( 'Compression level for this image', 'ha-image-optimizer' ); ?>">
					<option value="default" <?php selected( empty( $custom_level ) ); ?>>
						<?php esc_html_e( 'Default (Global)', 'ha-image-optimizer' ); ?>
					</option>
					<option value="ultra" <?php selected( $custom_level, 'ultra' ); ?>>
						<?php esc_html_e( 'High (Maximum)', 'ha-image-optimizer' ); ?>
					</option>
					<option value="smart" <?php selected( $custom_level, 'smart' ); ?>>
						<?php esc_html_e( 'Medium (Balanced)', 'ha-image-optimizer' ); ?>
					</option>
					<option value="lossless" <?php selected( $custom_level, 'lossless' ); ?>>
						<?php esc_html_e( 'Low (Lossless)', 'ha-image-optimizer' ); ?>
					</option>
				</select>
			</div>

			<div class="ha-io-actions">
				<a href="#" class="ha-io-btn-optimize" data-id="<?php echo esc_attr( $post_id ); ?>">
					<?php esc_html_e( 'Re-optimize', 'ha-image-optimizer' ); ?>
				</a>
				<?php if ( $has_backup ) : ?>
					| <a href="#" class="ha-io-btn-restore" data-id="<?php echo esc_attr( $post_id ); ?>">
						<?php esc_html_e( 'Restore', 'ha-image-optimizer' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Add Vorher-Nachher detail section to the edit attachment view.
	 *
	 * @param array<string, mixed> $form_fields Form fields.
	 * @param \WP_Post             $post Attachment post object.
	 * @return array<string, mixed>
	 */
	public static function add_attachment_fields( array $form_fields, \WP_Post $post ): array {
		$mime = get_post_mime_type( $post->ID );
		if ( ! in_array( $mime, AttachmentProcessor::SUPPORTED_MIMES, true ) ) {
			return $form_fields;
		}

		$data       = get_post_meta( $post->ID, '_ha_io_data', true );
		$has_backup = BackupManager::has_backup( $post->ID );

		ob_start();
		$template_path = HA_IO_PATH . 'templates/media-details-meta-box.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
		$html = ob_get_clean();

		$form_fields['ha_io_details'] = [
			'label' => esc_html__( 'HA Optimizer Details', 'ha-image-optimizer' ),
			'input' => 'html',
			'html'  => $html,
		];

		return $form_fields;
	}
}
