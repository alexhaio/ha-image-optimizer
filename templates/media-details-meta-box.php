<?php
/**
 * Template: Media Details Vorher-Nachher Meta Box.
 *
 * @package HA_Image_Optimizer
 *
 * @var array<string, mixed>|false $data
 * @var bool                       $has_backup
 * @var \WP_Post                   $post
 */

use ha\ImageOptimizer\Common\CompressionLevel;
use ha\ImageOptimizer\Engine\SizeGuard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$custom_level = (string) get_post_meta( $post->ID, '_ha_io_custom_level', true );
if ( empty( $custom_level ) && ! empty( $data['custom_level'] ) ) {
	$custom_level = (string) $data['custom_level'];
}
$custom_enum = CompressionLevel::fromString( $custom_level );

$meta        = wp_get_attachment_metadata( $post->ID );
$dim_w       = ! empty( $meta['width'] ) ? (int) $meta['width'] : 0;
$dim_h       = ! empty( $meta['height'] ) ? (int) $meta['height'] : 0;
$sizes_count = ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? count( $meta['sizes'] ) : 0;

if ( empty( $data ) || ! is_array( $data ) ) :
	$file        = get_attached_file( $post->ID );
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
	<div class="ha-io-meta-card ha-io-meta-unoptimized" data-id="<?php echo esc_attr( $post->ID ); ?>">
		<p style="margin-top: 0; margin-bottom: 8px;">
			<?php esc_html_e( 'This image has not been optimized yet.', 'ha-image-optimizer' ); ?>
			<?php if ( $total_unopt > 0 ) : ?>
				— <strong><?php echo esc_html( SizeGuard::format_size( $total_unopt, 1 ) ); ?></strong>
			<?php endif; ?>
			<?php if ( $dim_w && $dim_h ) : ?>
				<span class="description">(<?php echo esc_html( $dim_w . ' × ' . $dim_h . ' px' ); ?><?php if ( $sizes_count > 0 ) : ?>, +<?php echo esc_html( (string) $sizes_count ); ?> <?php echo esc_html( _n( 'Größe', 'Größen', $sizes_count, 'ha-image-optimizer' ) ); ?><?php endif; ?>)</span>
			<?php endif; ?>
		</p>
		<div class="ha-io-meta-level-choice" style="margin-bottom: 12px;">
			<label for="ha-io-meta-level-<?php echo esc_attr( $post->ID ); ?>" style="display:block; font-weight: 600; margin-bottom: 6px;">
				<?php esc_html_e( 'Compression for this image:', 'ha-image-optimizer' ); ?>
			</label>
			<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
				<select id="ha-io-meta-level-<?php echo esc_attr( $post->ID ); ?>" class="ha-io-level-select" data-id="<?php echo esc_attr( $post->ID ); ?>" style="max-width: 280px; height: 32px; font-size: 13px;">
					<option value="default"><?php esc_html_e( 'Default (Global setting)', 'ha-image-optimizer' ); ?></option>
					<option value="ultra"><?php esc_html_e( 'High (Maximum - approx. 72% quality)', 'ha-image-optimizer' ); ?></option>
					<option value="smart"><?php esc_html_e( 'Medium (Balanced - approx. 82% quality)', 'ha-image-optimizer' ); ?></option>
					<option value="lossless"><?php esc_html_e( 'Low (Lossless - 100% sharpness)', 'ha-image-optimizer' ); ?></option>
				</select>
				<button type="button" class="button button-primary ha-io-btn-optimize" data-id="<?php echo esc_attr( $post->ID ); ?>">
					⚡ <?php esc_html_e( 'Optimize now', 'ha-image-optimizer' ); ?>
				</button>
			</div>
		</div>
	</div>
	<?php
	return;
endif;

$total_before  = (int) ( $data['total']['before'] ?? 0 );
$total_after   = (int) ( $data['total']['after'] ?? 0 );
$saved_bytes   = (int) ( $data['total']['saved'] ?? 0 );
$saved_percent = (float) ( $data['total']['percent'] ?? 0.0 );
$orig          = $data['original'] ?? [];
$sizes         = $data['sizes'] ?? [];
$engine        = $data['engine'] ?? 'Lokal';
?>

<div class="ha-io-meta-card" data-id="<?php echo esc_attr( $post->ID ); ?>">
	<div class="ha-io-meta-header">
		<div class="ha-io-meta-title">
			<strong>⚡ HA Image Optimizer</strong>
			<span class="ha-io-badge ha-io-badge-engine"><?php echo esc_html( $engine ); ?></span>
			<?php if ( $custom_enum ) : ?>
				<span class="ha-io-badge ha-io-badge-orange" title="<?php echo esc_attr( $custom_enum->selection_label() ); ?>">
					<?php echo esc_html( $custom_enum->badge_label() ); ?>
				</span>
			<?php else : ?>
				<span class="ha-io-badge ha-io-badge-gray" title="<?php esc_attr_e( 'Uses global default settings', 'ha-image-optimizer' ); ?>">
					<?php esc_html_e( 'Default Level', 'ha-image-optimizer' ); ?>
				</span>
			<?php endif; ?>
		</div>
		<div class="ha-io-meta-summary-badge">
			<?php echo esc_html( SizeGuard::format_size( $total_before, 1 ) ); ?> → <?php echo esc_html( SizeGuard::format_size( $total_after, 1 ) ); ?>
			<span class="ha-io-bytes" style="color: #1a7f37; margin-left: 6px;">
				(-<?php echo esc_html( number_format_i18n( $saved_percent, 1 ) ); ?>% / <?php echo esc_html( SizeGuard::format_size( $saved_bytes, 1 ) ); ?> gespart)
			</span>
		</div>
	</div>

	<table class="ha-io-stats-table widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Image Size & Resolution', 'ha-image-optimizer' ); ?></th>
				<th><?php esc_html_e( 'Before', 'ha-image-optimizer' ); ?></th>
				<th><?php esc_html_e( 'After', 'ha-image-optimizer' ); ?></th>
				<th><?php esc_html_e( 'Savings', 'ha-image-optimizer' ); ?></th>
				<th><?php esc_html_e( 'NextGen', 'ha-image-optimizer' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<strong><?php esc_html_e( 'Original Image (Master)', 'ha-image-optimizer' ); ?></strong>
					<?php if ( $dim_w && $dim_h ) : ?>
						<span class="description">(<?php echo esc_html( $dim_w . ' × ' . $dim_h . ' px' ); ?>)</span>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( SizeGuard::format_size( (int) ( $orig['before'] ?? 0 ), 1 ) ); ?></td>
				<td><?php echo esc_html( SizeGuard::format_size( (int) ( $orig['after'] ?? 0 ), 1 ) ); ?></td>
				<td>
					<span class="ha-io-text-green">
						-<?php echo esc_html( number_format_i18n( (float) ( $orig['percent'] ?? 0 ), 1 ) ); ?>%
						<span class="description" style="font-size: 11px;">(-<?php echo esc_html( SizeGuard::format_size( (int) ( $orig['saved'] ?? 0 ), 1 ) ); ?>)</span>
					</span>
				</td>
				<td>
					<?php if ( ! empty( $orig['webp_size'] ) ) : ?>
						<span class="ha-io-pill">WebP: <?php echo esc_html( SizeGuard::format_size( $orig['webp_size'], 1 ) ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $orig['avif_size'] ) ) : ?>
						<span class="ha-io-pill">AVIF: <?php echo esc_html( SizeGuard::format_size( $orig['avif_size'], 1 ) ); ?></span>
					<?php endif; ?>
				</td>
			</tr>

			<?php foreach ( $sizes as $size_name => $s ) : ?>
				<tr>
					<td>
						<code><?php echo esc_html( $size_name ); ?></code>
						<?php if ( ! empty( $meta['sizes'][ $size_name ]['width'] ) && ! empty( $meta['sizes'][ $size_name ]['height'] ) ) : ?>
							<span class="description">(<?php echo esc_html( $meta['sizes'][ $size_name ]['width'] . ' × ' . $meta['sizes'][ $size_name ]['height'] . ' px' ); ?>)</span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( SizeGuard::format_size( (int) ( $s['before'] ?? 0 ), 1 ) ); ?></td>
					<td><?php echo esc_html( SizeGuard::format_size( (int) ( $s['after'] ?? 0 ), 1 ) ); ?></td>
					<td>
						<span class="ha-io-text-green">
							-<?php echo esc_html( number_format_i18n( (float) ( $s['percent'] ?? 0 ), 1 ) ); ?>%
							<span class="description" style="font-size: 11px;">(-<?php echo esc_html( SizeGuard::format_size( (int) ( $s['saved'] ?? 0 ), 1 ) ); ?>)</span>
						</span>
					</td>
					<td>
						<?php if ( ! empty( $s['webp_size'] ) ) : ?>
							<span class="ha-io-pill">WebP: <?php echo esc_html( SizeGuard::format_size( $s['webp_size'], 1 ) ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $s['avif_size'] ) ) : ?>
							<span class="ha-io-pill">AVIF: <?php echo esc_html( SizeGuard::format_size( $s['avif_size'], 1 ) ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<div class="ha-io-meta-level-choice" style="margin: 14px 0; padding: 12px; background: #fff; border: 1px solid #dcdcde; border-radius: 6px;">
		<label for="ha-io-meta-level-<?php echo esc_attr( $post->ID ); ?>" style="display:block; font-weight: 600; margin-bottom: 6px;">
			<?php esc_html_e( 'Change individual compression level:', 'ha-image-optimizer' ); ?>
		</label>
		<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
			<select id="ha-io-meta-level-<?php echo esc_attr( $post->ID ); ?>" class="ha-io-level-select" data-id="<?php echo esc_attr( $post->ID ); ?>" style="max-width: 280px; height: 32px; font-size: 13px;">
				<option value="default" <?php selected( empty( $custom_level ) ); ?>><?php esc_html_e( 'Default (Global setting)', 'ha-image-optimizer' ); ?></option>
				<option value="ultra" <?php selected( $custom_level, 'ultra' ); ?>><?php esc_html_e( 'High (Maximum - approx. 72% quality)', 'ha-image-optimizer' ); ?></option>
				<option value="smart" <?php selected( $custom_level, 'smart' ); ?>><?php esc_html_e( 'Medium (Balanced - approx. 82% quality)', 'ha-image-optimizer' ); ?></option>
				<option value="lossless" <?php selected( $custom_level, 'lossless' ); ?>><?php esc_html_e( 'Low (Lossless - 100% sharpness)', 'ha-image-optimizer' ); ?></option>
			</select>
			<button type="button" class="button button-primary ha-io-btn-optimize" data-id="<?php echo esc_attr( $post->ID ); ?>">
				🔄 <?php esc_html_e( 'Re-optimize', 'ha-image-optimizer' ); ?>
			</button>
		</div>
		<p class="description" style="margin-top: 6px; margin-bottom: 0;">
			<?php
			if ( $custom_enum ) {
				printf(
					/* translators: %s: Level label */
					esc_html__( 'Currently active: %s. When re-optimizing, the image is recalculated losslessly from the original backup using this level.', 'ha-image-optimizer' ),
					'<strong>' . esc_html( $custom_enum->selection_label() ) . '</strong>'
				);
			} else {
				esc_html_e( 'Currently using the global default setting. Choose an individual level (High, Medium, or Low) to compress this image differently.', 'ha-image-optimizer' );
			}
			?>
		</p>
	</div>

	<div class="ha-io-meta-actions">
		<?php if ( $has_backup ) : ?>
			<button type="button" class="button ha-io-btn-restore" data-id="<?php echo esc_attr( $post->ID ); ?>">
				↩️ <?php esc_html_e( 'Restore original', 'ha-image-optimizer' ); ?>
			</button>
			<span class="description ha-io-backup-note">
				🔒 <?php esc_html_e( 'Original image is safely stored in backup.', 'ha-image-optimizer' ); ?>
			</span>
		<?php else : ?>
			<span class="description ha-io-backup-none">
				⚠️ <?php esc_html_e( 'No backup available.', 'ha-image-optimizer' ); ?>
			</span>
		<?php endif; ?>
	</div>
</div>
