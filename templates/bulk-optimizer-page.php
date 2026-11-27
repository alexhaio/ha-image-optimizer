<?php
/**
 * Template: Bulk Optimizer Page.
 *
 * @package HA_Image_Optimizer
 *
 * @var array<string, mixed> $stats
 */

use ha\ImageOptimizer\Engine\SizeGuard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap ha-io-wrap">
	<!-- Navigation Tabs Header -->
	<nav class="nav-tab-wrapper ha-io-nav-tabs">
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ha-image-optimizer' ) ); ?>" class="nav-tab">
			⚙️ <?php esc_html_e( 'Settings', 'ha-image-optimizer' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'upload.php?page=ha-image-optimizer-bulk' ) ); ?>" class="nav-tab nav-tab-active">
			⚡ <?php esc_html_e( 'Bulk Optimization', 'ha-image-optimizer' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'upload.php?page=ha-image-optimizer-auditor' ) ); ?>" class="nav-tab">
			🔍 <?php esc_html_e( 'Orphaned Media (Auditor)', 'ha-image-optimizer' ); ?>
		</a>
	</nav>

	<h1>⚡ <?php esc_html_e( 'HA Image Optimizer: Bulk Optimization', 'ha-image-optimizer' ); ?></h1>

	<div class="ha-io-stats-overview">
		<div class="ha-io-stat-box">
			<span class="ha-io-stat-number" id="ha-io-stat-total"><?php echo esc_html( $stats['total_images'] ); ?></span>
			<span class="ha-io-stat-label"><?php esc_html_e( 'Total Media Library', 'ha-image-optimizer' ); ?></span>
		</div>
		<div class="ha-io-stat-box ha-io-stat-box-green">
			<span class="ha-io-stat-number" id="ha-io-stat-optimized"><?php echo esc_html( $stats['optimized_images'] ); ?></span>
			<span class="ha-io-stat-label"><?php esc_html_e( 'Already Optimized', 'ha-image-optimizer' ); ?></span>
		</div>
		<div class="ha-io-stat-box ha-io-stat-box-yellow">
			<span class="ha-io-stat-number" id="ha-io-stat-unoptimized"><?php echo esc_html( $stats['unoptimized'] ); ?></span>
			<span class="ha-io-stat-label"><?php esc_html_e( 'Still Unoptimized', 'ha-image-optimizer' ); ?></span>
		</div>
		<div class="ha-io-stat-box ha-io-stat-box-blue">
			<span class="ha-io-stat-number" id="ha-io-stat-saved"><?php echo esc_html( SizeGuard::format_size( $stats['saved_bytes'], 1 ) ); ?></span>
			<span class="ha-io-stat-label">
				<?php esc_html_e( 'Saved Storage', 'ha-image-optimizer' ); ?>
				(-<span id="ha-io-stat-percent"><?php echo esc_html( $stats['saved_percent'] ); ?></span>%)
			</span>
		</div>
	</div>

	<?php if ( ! empty( $custom_stats['total_images'] ) ) : ?>
		<div class="ha-io-custom-stats-bar">
			<span class="ha-io-badge ha-io-badge-purple">🎨 <?php esc_html_e( 'Theme & Custom Folders:', 'ha-image-optimizer' ); ?></span>
			<strong><?php echo esc_html( $custom_stats['total_images'] ); ?></strong> <?php esc_html_e( 'Files found', 'ha-image-optimizer' ); ?> &bull;
			<strong><?php echo esc_html( $custom_stats['optimized_images'] ); ?></strong> <?php esc_html_e( 'optimized', 'ha-image-optimizer' ); ?> &bull;
			<strong><?php echo esc_html( $custom_stats['unoptimized'] ); ?></strong> <?php esc_html_e( 'remaining', 'ha-image-optimizer' ); ?> &bull;
			<strong><?php echo esc_html( SizeGuard::format_size( $custom_stats['saved_bytes'], 1 ) ); ?></strong> <?php esc_html_e( 'saved', 'ha-image-optimizer' ); ?>
		</div>
	<?php endif; ?>

	<!-- Main Execution Card -->
	<div class="ha-io-card ha-io-bulk-card">
		<h2>🚀 <?php esc_html_e( 'Start Bulk Processing', 'ha-image-optimizer' ); ?></h2>
		<p>
			<?php esc_html_e( 'Bulk optimization processes your entire Media Library or static theme images locally in the background. For each image, the original is optimized losslessly, all intermediate sizes are compressed, and modern WebP/AVIF versions are generated.', 'ha-image-optimizer' ); ?>
		</p>

		<!-- Visual Stepper Bar -->
		<div class="ha-io-stepper" id="ha-io-stepper">
			<div class="ha-io-step" id="ha-io-step-1">
				<span class="ha-io-step-num">1</span>
				<span class="ha-io-step-label"><?php esc_html_e( 'Scan files', 'ha-image-optimizer' ); ?></span>
			</div>
			<div class="ha-io-step-connector"></div>
			<div class="ha-io-step" id="ha-io-step-2">
				<span class="ha-io-step-num">2</span>
				<span class="ha-io-step-label"><?php esc_html_e( 'Compress original', 'ha-image-optimizer' ); ?></span>
			</div>
			<div class="ha-io-step-connector"></div>
			<div class="ha-io-step" id="ha-io-step-3">
				<span class="ha-io-step-num">3</span>
				<span class="ha-io-step-label"><?php esc_html_e( 'Generate WebP & AVIF', 'ha-image-optimizer' ); ?></span>
			</div>
			<div class="ha-io-step-connector"></div>
			<div class="ha-io-step" id="ha-io-step-4">
				<span class="ha-io-step-num">4</span>
				<span class="ha-io-step-label"><?php esc_html_e( 'Completed', 'ha-image-optimizer' ); ?></span>
			</div>
		</div>

		<!-- Action Buttons -->
		<div class="ha-io-bulk-actions">
			<button type="button" class="button button-primary ha-io-bulk-btn" id="ha-io-btn-start-bulk" <?php disabled( 0 === $stats['unoptimized'] ); ?>>
				⚡ <?php esc_html_e( 'Optimize Media Library', 'ha-image-optimizer' ); ?>
			</button>

			<?php if ( ! empty( $custom_stats['total_images'] ) ) : ?>
				<button type="button" class="button button-primary ha-io-bulk-btn" id="ha-io-btn-start-custom" <?php disabled( 0 === $custom_stats['unoptimized'] ); ?>>
					🎨 <?php esc_html_e( 'Optimize Theme Folders', 'ha-image-optimizer' ); ?> (<?php echo esc_html( $custom_stats['unoptimized'] ); ?>)
				</button>
			<?php endif; ?>

			<button type="button" class="button button-secondary ha-io-bulk-btn" id="ha-io-btn-start-bulk-all">
				🔄 <?php esc_html_e( 'Re-optimize all images (Force)', 'ha-image-optimizer' ); ?>
			</button>

			<button type="button" class="button button-secondary ha-io-bulk-btn" id="ha-io-btn-pause-bulk" style="display: none;">
				⏸️ <?php esc_html_e( 'Pause', 'ha-image-optimizer' ); ?>
			</button>
		</div>

		<!-- Live Current Action Display -->
		<div class="ha-io-live-action-box" id="ha-io-live-action-box" style="display: none;">
			<div class="ha-io-live-action-header">
				<span class="ha-io-pulse-indicator"></span>
				<div class="ha-io-live-action-text-group">
					<strong class="ha-io-live-title" id="ha-io-current-action-title">
						<?php esc_html_e( 'Preparing queue...', 'ha-image-optimizer' ); ?>
					</strong>
					<span class="ha-io-live-desc" id="ha-io-current-action-desc">
						<?php esc_html_e( 'Searching database for images...', 'ha-image-optimizer' ); ?>
					</span>
				</div>
			</div>
			<div class="ha-io-current-file-badge" id="ha-io-current-file-badge" style="display: none;">
				<span class="ha-io-cf-label"><?php esc_html_e( 'Current file:', 'ha-image-optimizer' ); ?></span>
				<code id="ha-io-current-filename">—</code>
			</div>
		</div>

		<!-- Animated Progress Bar -->
		<div id="ha-io-progress-container" style="display: none;">
			<div class="ha-io-progress-bar-bg">
				<div class="ha-io-progress-bar-fill" id="ha-io-progress-bar" style="width: 0%;"></div>
			</div>
			<div class="ha-io-progress-info">
				<span id="ha-io-progress-status"><?php esc_html_e( 'Processing images...', 'ha-image-optimizer' ); ?></span>
				<span id="ha-io-progress-counts">0 / 0 (0%)</span>
			</div>
		</div>

		<!-- Collapsible History Dropdown of processed files -->
		<details class="ha-io-history-dropdown" id="ha-io-history-dropdown" open style="display: none;">
			<summary class="ha-io-history-summary">
				<div class="ha-io-history-summary-left">
					<span class="ha-io-history-icon">📋</span>
					<strong><?php esc_html_e( 'Images already processed in this run', 'ha-image-optimizer' ); ?></strong>
					<span class="ha-io-badge ha-io-badge-green" id="ha-io-history-count">0</span>
				</div>
				<span class="ha-io-history-arrow">▾</span>
			</summary>

			<div class="ha-io-history-content">
				<table class="ha-io-history-table widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Filename', 'ha-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Before', 'ha-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'After', 'ha-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Savings', 'ha-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Next-Gen', 'ha-image-optimizer' ); ?></th>
						</tr>
					</thead>
					<tbody id="ha-io-history-tbody">
						<!-- Dynamically appended by JavaScript -->
					</tbody>
				</table>
			</div>
		</details>

		<!-- Live Technical Log -->
		<details class="ha-io-log-details" id="ha-io-log-container" style="display: none;">
			<summary>
				<strong><?php esc_html_e( 'Show detailed system log', 'ha-image-optimizer' ); ?></strong>
			</summary>
			<ul class="ha-io-log-list" id="ha-io-log-list"></ul>
		</details>
	</div>
</div>
