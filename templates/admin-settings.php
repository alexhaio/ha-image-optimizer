<?php
/**
 * Template: Admin Settings Page.
 *
 * @package HA_Image_Optimizer
 *
 * @var array<string, mixed> $settings
 * @var array<string, mixed> $caps
 * @var array<string, mixed> $stats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$delete_data_on_uninstall = (bool) get_option( 'ha_io_delete_data_on_uninstall', false );
?>

<div class="wrap ha-io-wrap">
	<!-- Navigation Tabs Header -->
	<nav class="nav-tab-wrapper ha-io-nav-tabs">
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ha-image-optimizer' ) ); ?>" class="nav-tab nav-tab-active">
			⚙️ <?php esc_html_e( 'Settings', 'ha-image-optimizer' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'upload.php?page=ha-image-optimizer-bulk' ) ); ?>" class="nav-tab">
			⚡ <?php esc_html_e( 'Bulk Optimization', 'ha-image-optimizer' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'upload.php?page=ha-image-optimizer-auditor' ) ); ?>" class="nav-tab">
			🔍 <?php esc_html_e( 'Orphaned Media (Auditor)', 'ha-image-optimizer' ); ?>
		</a>
	</nav>

	<h1>⚡ <?php esc_html_e( 'HA Image Optimizer: Settings & System Status', 'ha-image-optimizer' ); ?></h1>

	<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Displays save confirmation notice after wp_safe_redirect(). ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved successfully.', 'ha-image-optimizer' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Quick-Start Banner -->
	<div class="ha-io-quick-start-card">
		<div class="ha-io-quick-start-text">
			<span class="ha-io-pulse-dot"></span>
			<div>
				<strong><?php esc_html_e( 'Bulk optimization ready:', 'ha-image-optimizer' ); ?></strong>
				<span>
					<?php
					printf(
						/* translators: 1: unoptimized images count, 2: total images count */
						esc_html__( '%1$d of %2$d images can still be optimized.', 'ha-image-optimizer' ),
						(int) $stats['unoptimized'],
						(int) $stats['total_images']
					);
					?>
				</span>
			</div>
		</div>
		<a href="<?php echo esc_url( admin_url( 'upload.php?page=ha-image-optimizer-bulk' ) ); ?>" class="button button-primary button-hero ha-io-btn-hero">
			⚡ <?php esc_html_e( 'Start Bulk Optimization Now', 'ha-image-optimizer' ); ?>
		</a>
	</div>

	<!-- 1. System Diagnostics Widget -->
	<div class="ha-io-card ha-io-card-diagnostic">
		<h2>🖥️ <?php esc_html_e( 'Server Capabilities & Diagnostics', 'ha-image-optimizer' ); ?></h2>
		<div class="ha-io-diag-grid">
			<div class="ha-io-diag-item">
				<span class="ha-io-diag-label"><?php esc_html_e( 'PHP Version:', 'ha-image-optimizer' ); ?></span>
				<span class="ha-io-diag-val <?php echo $caps['php']['compatible'] ? 'ha-io-status-green' : 'ha-io-status-red'; ?>">
					<?php echo esc_html( $caps['php']['version'] ); ?>
					<?php echo $caps['php']['compatible'] ? '✅' : '❌ (mind. 8.1 erforderlich)'; ?>
				</span>
			</div>

			<div class="ha-io-diag-item">
				<span class="ha-io-diag-label"><?php esc_html_e( 'Active Driver:', 'ha-image-optimizer' ); ?></span>
				<span class="ha-io-diag-val <?php echo 'none' !== $caps['best_driver'] ? 'ha-io-status-green' : 'ha-io-status-red'; ?>">
					<?php if ( 'none' !== $caps['best_driver'] ) : ?>
						<?php echo esc_html( strtoupper( $caps['best_driver'] ) ); ?> ✅
					<?php else : ?>
						<?php esc_html_e( 'No image driver available (GD / Imagick missing)', 'ha-image-optimizer' ); ?> ❌
					<?php endif; ?>
				</span>
			</div>

			<div class="ha-io-diag-item">
				<span class="ha-io-diag-label"><?php esc_html_e( 'WebP Support:', 'ha-image-optimizer' ); ?></span>
				<span class="ha-io-diag-val <?php echo $caps['supports_webp'] ? 'ha-io-status-green' : 'ha-io-status-red'; ?>">
					<?php echo $caps['supports_webp'] ? esc_html__( 'Available ✅', 'ha-image-optimizer' ) : esc_html__( 'Not available ❌', 'ha-image-optimizer' ); ?>
				</span>
			</div>

			<div class="ha-io-diag-item">
				<span class="ha-io-diag-label"><?php esc_html_e( 'AVIF Support:', 'ha-image-optimizer' ); ?></span>
				<span class="ha-io-diag-val <?php echo $caps['supports_avif'] ? 'ha-io-status-green' : 'ha-io-status-yellow'; ?>">
					<?php echo $caps['supports_avif'] ? esc_html__( 'Available ✅', 'ha-image-optimizer' ) : esc_html__( 'Not available (PHP 8.1+ / libavif)', 'ha-image-optimizer' ); ?>
				</span>
			</div>

			<div class="ha-io-diag-item">
				<span class="ha-io-diag-label"><?php esc_html_e( 'Web Server:', 'ha-image-optimizer' ); ?></span>
				<span class="ha-io-diag-val">
					<?php echo esc_html( $caps['server']['software'] ?: 'Unbekannt' ); ?>
					<?php if ( $caps['server']['supports_htaccess'] ) : ?>
						<span class="ha-io-badge ha-io-badge-green"><?php esc_html_e( '.htaccess supported', 'ha-image-optimizer' ); ?></span>
					<?php else : ?>
						<span class="ha-io-badge ha-io-badge-blue"><?php esc_html_e( 'Nginx / Standalone', 'ha-image-optimizer' ); ?></span>
					<?php endif; ?>
				</span>
			</div>

			<div class="ha-io-diag-item">
				<span class="ha-io-diag-label"><?php esc_html_e( 'Memory Limit:', 'ha-image-optimizer' ); ?></span>
				<span class="ha-io-diag-val <?php echo $caps['memory']['is_sufficient'] ? 'ha-io-status-green' : 'ha-io-status-yellow'; ?>">
					<?php echo esc_html( $caps['memory']['limit_string'] ); ?>
					<?php echo $caps['memory']['is_sufficient'] ? '✅' : '⚠️ (empfohlen: mind. 256MB)'; ?>
				</span>
			</div>
		</div>
	</div>

	<!-- 2. Settings Form -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'ha_io_save_settings_action', 'ha_io_nonce' ); ?>
		<input type="hidden" name="action" value="ha_io_save_settings">

		<div class="ha-io-card">
			<h2>⚙️ <?php esc_html_e( 'Compression Settings', 'ha-image-optimizer' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="compression_original"><?php esc_html_e( 'Original Image Optimization (Master)', 'ha-image-optimizer' ); ?></label>
					</th>
					<td>
						<select name="ha_io_settings[compression_original]" id="compression_original">
							<option value="lossless" <?php selected( $settings['compression_original'], 'lossless' ); ?>>
								<?php esc_html_e( 'Lossless (Default: 100% pixel fidelity, EXIF stripped)', 'ha-image-optimizer' ); ?>
							</option>
							<option value="smart" <?php selected( $settings['compression_original'], 'smart' ); ?>>
								<?php esc_html_e( 'Smart (~82% quality, maximum savings)', 'ha-image-optimizer' ); ?>
							</option>
							<option value="ultra" <?php selected( $settings['compression_original'], 'ultra' ); ?>>
								<?php esc_html_e( 'Ultra (~72% quality, aggressive)', 'ha-image-optimizer' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'We recommend "Lossless" so your master photo retains maximum quality.', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="compression_sizes"><?php esc_html_e( 'Sub-Size Optimization (Thumbnails)', 'ha-image-optimizer' ); ?></label>
					</th>
					<td>
						<select name="ha_io_settings[compression_sizes]" id="compression_sizes">
							<option value="smart" <?php selected( $settings['compression_sizes'], 'smart' ); ?>>
								<?php esc_html_e( 'Smart (Recommended: ~82% quality, visually lossless)', 'ha-image-optimizer' ); ?>
							</option>
							<option value="ultra" <?php selected( $settings['compression_sizes'], 'ultra' ); ?>>
								<?php esc_html_e( 'Ultra (~72% quality, ultra compact)', 'ha-image-optimizer' ); ?>
							</option>
							<option value="lossless" <?php selected( $settings['compression_sizes'], 'lossless' ); ?>>
								<?php esc_html_e( 'Lossless', 'ha-image-optimizer' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Controls compression of all intermediate web image sizes (thumbnail, medium, large, etc.).', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Next-Gen Image Formats', 'ha-image-optimizer' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="ha_io_settings[generate_webp]" value="1" <?php checked( $settings['generate_webp'] ); ?>>
								<strong><?php esc_html_e( 'Automatically generate WebP versions', 'ha-image-optimizer' ); ?></strong>
							</label>
							<br><br>
							<label>
								<input type="checkbox" name="ha_io_settings[generate_avif]" value="1" <?php checked( $settings['generate_avif'] ); ?> <?php disabled( ! $caps['supports_avif'] ); ?>>
								<strong><?php esc_html_e( 'Automatically generate AVIF versions (Next-Gen+)', 'ha-image-optimizer' ); ?></strong>
							</label>
							<?php if ( ! $caps['supports_avif'] ) : ?>
								<p class="description ha-io-text-yellow">
									<?php esc_html_e( 'AVIF is currently not available on your server (requires PHP 8.1+ GD or ImageMagick with AVIF support).', 'ha-image-optimizer' ); ?>
								</p>
							<?php endif; ?>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Delivery Method', 'ha-image-optimizer' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="ha_io_settings[delivery_mode]" value="content_filter" <?php checked( $settings['delivery_mode'], 'content_filter' ); ?>>
								<strong><?php esc_html_e( '<picture> tag replacement (Default & Universal)', 'ha-image-optimizer' ); ?></strong>
								<span class="description"><?php esc_html_e( 'Replaces <img> with <picture> and inline styles with CSS image-set(). Works with all servers and CDNs.', 'ha-image-optimizer' ); ?></span>
							</label>
							<br><br>
							<label>
								<input type="radio" name="ha_io_settings[delivery_mode]" value="htaccess" <?php checked( $settings['delivery_mode'], 'htaccess' ); ?> <?php disabled( ! $caps['server']['supports_htaccess'] ); ?>>
								<strong><?php esc_html_e( '.htaccess Server Rewrite (0 ms PHP overhead)', 'ha-image-optimizer' ); ?></strong>
								<span class="description"><?php esc_html_e( 'Writes rules to wp-content/uploads/.htaccess. Apache serves WebP transparently.', 'ha-image-optimizer' ); ?></span>
							</label>
							<br><br>
							<label>
								<input type="radio" name="ha_io_settings[delivery_mode]" value="off" <?php checked( $settings['delivery_mode'], 'off' ); ?>>
								<strong><?php esc_html_e( 'Disabled', 'ha-image-optimizer' ); ?></strong>
								<span class="description"><?php esc_html_e( 'Generates WebP/AVIF files on disk only without rewriting URLs on the frontend.', 'ha-image-optimizer' ); ?></span>
							</label>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Full-Page Output Buffer', 'ha-image-optimizer' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ha_io_settings[enable_full_buffer]" value="1" <?php checked( $settings['enable_full_buffer'] ); ?>>
							<strong><?php esc_html_e( 'Search full-page output buffer (ob_start)', 'ha-image-optimizer' ); ?></strong>
						</label>
						<p class="description">
							<?php esc_html_e( 'Also catches stubborn page-builder images across the entire HTML output. Recommended when using a caching plugin (WP Rocket, LiteSpeed Cache, etc.).', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Automatic Resizing', 'ha-image-optimizer' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ha_io_settings[resize_large_images]" value="1" <?php checked( $settings['resize_large_images'] ); ?>>
							<?php esc_html_e( 'Automatically downscale oversized uploads to maximum:', 'ha-image-optimizer' ); ?>
						</label>
						<br><br>
						<label>
							<?php esc_html_e( 'Width:', 'ha-image-optimizer' ); ?>
							<input type="number" name="ha_io_settings[max_width]" value="<?php echo esc_attr( $settings['max_width'] ); ?>" min="500" max="10000" step="10"> px
						</label>
						&nbsp;&nbsp;
						<label>
							<?php esc_html_e( 'Height:', 'ha-image-optimizer' ); ?>
							<input type="number" name="ha_io_settings[max_height]" value="<?php echo esc_attr( $settings['max_height'] ); ?>" min="500" max="10000" step="10"> px
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'PNG to JPEG Conversion', 'ha-image-optimizer' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ha_io_settings[convert_non_transparent_png]" value="1" <?php checked( $settings['convert_non_transparent_png'] ); ?>>
							<strong><?php esc_html_e( 'Automatically convert non-transparent PNGs to JPEG', 'ha-image-optimizer' ); ?></strong>
						</label>
						<p class="description">
							<?php esc_html_e( 'Inspects PNG images for real transparency. If no transparent pixels exist and the resulting JPEG file is smaller, the image is converted.', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Backup & Metadata', 'ha-image-optimizer' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="ha_io_settings[backup_originals]" value="1" <?php checked( $settings['backup_originals'] ); ?>>
								<strong><?php esc_html_e( 'Back up unmodified original image in protected backup folder', 'ha-image-optimizer' ); ?></strong>
							</label>
							<br><br>
							<label>
								<input type="checkbox" name="ha_io_settings[keep_exif]" value="1" <?php checked( $settings['keep_exif'] ); ?>>
								<?php esc_html_e( 'Keep EXIF camera data and copyright metadata (increases file size)', 'ha-image-optimizer' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Theme & Custom Folders', 'ha-image-optimizer' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="ha_io_settings[optimize_custom_folders]" value="1" <?php checked( $settings['optimize_custom_folders'] ); ?>>
								<strong><?php esc_html_e( 'Scan and optimize images outside the Media Library', 'ha-image-optimizer' ); ?></strong>
							</label>
							<p class="description">
								<?php esc_html_e( 'Finds static image files in theme folders or custom directories, compresses them, and generates WebP/AVIF files.', 'ha-image-optimizer' ); ?>
							</p>
							<br>
							<label for="ha-io-custom-folders">
								<strong><?php esc_html_e( 'Directories to scan (one path per line, relative to wp-content):', 'ha-image-optimizer' ); ?></strong>
							</label>
							<textarea id="ha-io-custom-folders" name="ha_io_settings[custom_folders]" rows="3" class="large-text code"><?php echo esc_textarea( $settings['custom_folders'] ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Default: themes. Example: themes or uploads/custom-folder', 'ha-image-optimizer' ); ?>
							</p>
						</fieldset>
					</td>
				</tr>
			</table>
		</div>

		<!-- 3. Medien-Auditor: Erweiterter Tiefenscan & Schutzfrist -->
		<div class="ha-io-card" id="ha-io-auditor-settings">
			<h2>🔍 <?php esc_html_e( 'Media Auditor: Universal Deep Scan & Protection Period', 'ha-image-optimizer' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Configure how thoroughly the Media Auditor searches your database for image references and how newly uploaded media are protected.', 'ha-image-optimizer' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Performance Mode (Smart Early-Exit)', 'ha-image-optimizer' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ha_io_settings[auditor_fast_mode]" value="1" <?php checked( $settings['auditor_fast_mode'] ?? true ); ?>>
							<strong><?php esc_html_e( 'Stop deep scan as soon as an image is confirmed as used (Recommended)', 'ha-image-optimizer' ); ?></strong>
						</label>
						<p class="description">
							<?php esc_html_e( 'Reduces database queries by up to 90%. Once an image is found as a featured image, in post content, theme, or widget, subsequent heavy table scans are skipped for that image.', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Reverse Indexing', 'ha-image-optimizer' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ha_io_settings[auditor_reverse_index_enabled]" value="1" <?php checked( $settings['auditor_reverse_index_enabled'] ?? true ); ?>>
							<strong><?php esc_html_e( 'Content pre-indexing for lightning-fast queries (Recommended)', 'ha-image-optimizer' ); ?></strong>
						</label>
						<p class="description">
							<?php esc_html_e( 'Pre-scans posts and page builders once in a structured pass and detects image references in memory within milliseconds. Prevents thousands of separate SQL full-table scans and drastically reduces database load.', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Background Tasks (Async)', 'ha-image-optimizer' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ha_io_settings[auditor_background_enabled]" id="ha-io-auditor-bg-enabled" value="1" <?php checked( $settings['auditor_background_enabled'] ?? false ); ?>>
							<strong><?php esc_html_e( 'Run Media Audit automatically in the background', 'ha-image-optimizer' ); ?></strong>
						</label>
						<p class="description">
							<?php esc_html_e( 'Enables running the scan in gentle background batches on the server (via non-blocking HTTP & WP-Cron). You do not need to keep this browser tab open.', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Night Mode (Off-Peak Window)', 'ha-image-optimizer' ); ?></th>
					<td>
						<label style="display: block; margin-bottom: 8px;">
							<input type="hidden" name="ha_io_settings[auditor_night_mode_enabled]" value="0">
							<input type="checkbox" name="ha_io_settings[auditor_night_mode_enabled]" id="ha-io-auditor-night-enabled" value="1" <?php checked( $settings['auditor_night_mode_enabled'] ?? true ); ?>>
							<strong><?php esc_html_e( 'Run background scan at night only (automatically active for background tasks)', 'ha-image-optimizer' ); ?></strong>
						</label>
						<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
							<span><?php esc_html_e( 'From:', 'ha-image-optimizer' ); ?></span>
							<input type="time" name="ha_io_settings[auditor_night_start]" value="<?php echo esc_attr( $settings['auditor_night_start'] ?? '02:00' ); ?>" style="width: 100px;">
							<span><?php esc_html_e( 'to:', 'ha-image-optimizer' ); ?></span>
							<input type="time" name="ha_io_settings[auditor_night_end]" value="<?php echo esc_attr( $settings['auditor_night_end'] ?? '06:00' ); ?>" style="width: 100px;">
							<span style="color: #666; font-size: 12px;">(<?php
								/* translators: %s: current server time in HH:MM format */
								printf( esc_html__( 'Current server time: %s', 'ha-image-optimizer' ), esc_html( function_exists( 'wp_date' ) ? wp_date( 'H:i' ) : gmdate( 'H:i' ) ) );
							?>)</span>
						</div>
						<p class="description">
							<?php esc_html_e( 'Outside this window, the background scan pauses automatically and waits for off-peak night hours. Protects live visitors 100% from database load.', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Processing Intensity & Pacing', 'ha-image-optimizer' ); ?></th>
					<td>
						<?php $cur_pacing = $settings['auditor_pacing_mode'] ?? 'standard'; ?>
						<select name="ha_io_settings[auditor_pacing_mode]" style="min-width: 280px;">
							<option value="eco" <?php selected( 'eco', $cur_pacing ); ?>>
								🌱 <?php esc_html_e( 'Eco (Gentle: 3 images / step, 1s pause – minimal server load)', 'ha-image-optimizer' ); ?>
							</option>
							<option value="standard" <?php selected( 'standard', $cur_pacing ); ?>>
								⚡ <?php esc_html_e( 'Standard (Balanced: 10 images / step, 300ms pause)', 'ha-image-optimizer' ); ?>
							</option>
							<option value="turbo" <?php selected( 'turbo', $cur_pacing ); ?>>
								🚀 <?php esc_html_e( 'Turbo (Fast: 25 images / step, 50ms pause – for powerful servers)', 'ha-image-optimizer' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Determines how many images are checked per batch and how long the cooldown pause between queries is.', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Server Load Safeguard (Max Load)', 'ha-image-optimizer' ); ?></th>
					<td>
						<label>
							<input type="number" step="0.1" min="0.5" max="50.0" name="ha_io_settings[auditor_max_server_load]" value="<?php echo esc_attr( $settings['auditor_max_server_load'] ?? 2.5 ); ?>" style="width: 80px;">
							<?php esc_html_e( 'Maximum Load Average (1 min)', 'ha-image-optimizer' ); ?>
						</label>
						<?php
						$current_load = \ha\ImageOptimizer\Auditor\MediaAuditor::get_server_load();
						if ( null !== $current_load ) :
						?>
							<span style="color: #666; font-size: 12px; margin-left: 8px;">
								(<?php
									/* translators: %s: current 1-minute server load average */
									printf( esc_html__( 'Current server load: %s', 'ha-image-optimizer' ), esc_html( number_format( $current_load, 2 ) ) );
								?>)
							</span>
						<?php endif; ?>
						<p class="description">
							<?php esc_html_e( 'If server load exceeds this value, the scan automatically pauses for a cooldown period until server load drops.', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Universal Database Scan', 'ha-image-optimizer' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ha_io_settings[auditor_generic_db_scan]" value="1" <?php checked( $settings['auditor_generic_db_scan'] ?? true ); ?>>
							<strong><?php esc_html_e( 'Automatically scan all third-party tables (sliders, forms, page builders, plugins)', 'ha-image-optimizer' ); ?></strong>
						</label>
						<p class="description">
							<?php esc_html_e( 'Searches all tables containing text or JSON columns for image IDs, relative paths, and filenames. Log, cache, and statistics tables are skipped automatically.', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Protection Period for New Uploads', 'ha-image-optimizer' ); ?></th>
					<td>
						<label>
							<input type="number" name="ha_io_settings[auditor_safety_buffer_days]" value="<?php echo esc_attr( $settings['auditor_safety_buffer_days'] ?? 0 ); ?>" min="0" max="365" style="width: 80px;">
							<?php esc_html_e( 'days', 'ha-image-optimizer' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Images uploaded within this period are protected from deletion even if not yet published (0 = disabled, e.g. 14 days recommended for active editorial teams).', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Scan Additional Tables', 'ha-image-optimizer' ); ?></th>
					<td>
						<label for="ha-io-auditor-custom-tables">
							<?php esc_html_e( 'Explicit table names (one table per line, with or without table prefix):', 'ha-image-optimizer' ); ?>
						</label>
						<textarea id="ha-io-auditor-custom-tables" name="ha_io_settings[auditor_custom_tables]" rows="3" class="large-text code"><?php echo esc_textarea( $settings['auditor_custom_tables'] ?? '' ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Optional. Only needed if you want to force specific tables. By default, the auditor automatically detects all relevant tables.', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- 4. HA Standards Data Protection on Uninstall -->
		<div class="ha-io-card ha-io-card-uninstall">
			<h2>🗑️ <?php esc_html_e( 'Uninstall Safeguard (HA Standard)', 'ha-image-optimizer' ); ?></h2>
			<p>
				<?php esc_html_e( 'In accordance with HA development standards, your image backups, WebP files, and metadata are never automatically deleted upon uninstallation to prevent data loss.', 'ha-image-optimizer' ); ?>
			</p>
			<label>
				<input type="checkbox" name="ha_io_delete_data_on_uninstall" value="1" <?php checked( $delete_data_on_uninstall ); ?>>
				<strong class="ha-io-text-red">
					<?php esc_html_e( 'Complete data deletion on uninstall:', 'ha-image-optimizer' ); ?>
				</strong>
				<?php esc_html_e( 'Permanently delete all backups in /ha-backups/, WebP/AVIF files, and database statistics when the plugin is uninstalled.', 'ha-image-optimizer' ); ?>
			</label>
		</div>

		<?php submit_button( esc_html__( 'Save Settings', 'ha-image-optimizer' ) ); ?>
	</form>

	<!-- 4. Nginx Configuration Helper Snippet -->
	<div class="ha-io-card ha-io-card-nginx">
		<h2>🌐 <?php esc_html_e( 'Nginx Configuration Help (Optional for Nginx servers)', 'ha-image-optimizer' ); ?></h2>
		<p>
			<?php esc_html_e( 'If your server runs Nginx and you want transparent WebP serving without modifying HTML, add the following block to your Nginx configuration:', 'ha-image-optimizer' ); ?>
		</p>
		<pre class="ha-io-code-block"><code># Nginx WebP / AVIF Direct Rewrite für HA Image Optimizer
map $http_accept $ha_io_suffix {
    default        "";
    "~*image/avif" ".avif";
    "~*image/webp" ".webp";
}

location ~* ^/wp-content/uploads/(.+)\.(jpe?g|png)$ {
    add_header Vary Accept;
    try_files $uri$ha_io_suffix $uri =404;
}</code></pre>
	</div>
</div>
