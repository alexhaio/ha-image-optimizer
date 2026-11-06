<?php
/**
 * Template: Media Auditor Page (Orphan & Unused Media).
 *
 * @package HA_Image_Optimizer
 *
 * @var array<string, mixed> $audit_data
 */

use ha\ImageOptimizer\Engine\SizeGuard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$counts      = $audit_data['counts'] ?? [ 'total' => 0, 'used' => 0, 'attached_unused' => 0, 'unattached_unused' => 0 ];
$reclaimable = $audit_data['reclaimable_bytes'] ?? [ 'unattached' => 0, 'attached' => 0, 'total' => 0 ];
$items       = $audit_data['items'] ?? [];
$last_scan   = ! empty( $audit_data['last_scan'] ) ? date_i18n( 'd.m.Y H:i', (int) $audit_data['last_scan'] ) : __( 'Noch nie ausgeführt', 'ha-image-optimizer' );
?>

<div class="wrap ha-io-wrap">
	<!-- Navigation Tabs Header -->
	<nav class="nav-tab-wrapper ha-io-nav-tabs">
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ha-image-optimizer' ) ); ?>" class="nav-tab">
			⚙️ <?php esc_html_e( 'Einstellungen', 'ha-image-optimizer' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'upload.php?page=ha-image-optimizer-bulk' ) ); ?>" class="nav-tab">
			⚡ <?php esc_html_e( 'Massenoptimierung', 'ha-image-optimizer' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'upload.php?page=ha-image-optimizer-auditor' ) ); ?>" class="nav-tab nav-tab-active">
			🔍 <?php esc_html_e( 'Verwaiste Medien (Auditor)', 'ha-image-optimizer' ); ?>
		</a>
	</nav>

	<h1>🔍 <?php esc_html_e( 'Medien-Auditor: Verwaiste & ungenutzte Dateien aufspüren', 'ha-image-optimizer' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Der Medien-Auditor scannt deine gesamte WordPress-Mediathek und prüft, ob Bilder tatsächlich auf deiner Website verwendet werden. Geprüft werden Beitragsbilder, WooCommerce-Produktgalerien, Inhalte aller Seiten/Beiträge (inkl. Gutenberg-Blöcke & <img>-Tags), Page-Builder, Website-Logos, Favicons und Taxonomien.', 'ha-image-optimizer' ); ?>
	</p>

	<div style="margin: 12px 0 20px 0; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; font-size: 12px;">
		<span style="background: #e7f3ff; color: #0969da; padding: 4px 10px; border-radius: 12px; font-weight: 500;">
			🌐 <?php esc_html_e( 'Universeller DB-Scan: Aktiv', 'ha-image-optimizer' ); ?>
		</span>
		<span style="background: #dafbe1; color: #1a7f37; padding: 4px 10px; border-radius: 12px; font-weight: 500;">
			⚡ <?php esc_html_e( 'Umkehr-Suche: Aktiv', 'ha-image-optimizer' ); ?>
		</span>
		<?php $is_bg = (bool) \ha\ImageOptimizer\Common\Settings::get( 'auditor_background_enabled', false ); ?>
		<?php if ( $is_bg ) : ?>
			<span style="background: #fbefff; color: #8250df; padding: 4px 10px; border-radius: 12px; font-weight: 500;">
				🌙 <?php esc_html_e( 'Hintergrund-Tasks: Aktiv', 'ha-image-optimizer' ); ?>
			</span>
		<?php endif; ?>
		<?php $cur_safety = (int) \ha\ImageOptimizer\Common\Settings::get( 'auditor_safety_buffer_days', 0 ); ?>
		<?php if ( $cur_safety > 0 ) : ?>
			<span style="background: #dafbe1; color: #1a7f37; padding: 4px 10px; border-radius: 12px; font-weight: 500;">
				🛡️ <?php printf( esc_html__( 'Schutzfrist für neue Uploads: %d Tage', 'ha-image-optimizer' ), $cur_safety ); ?>
			</span>
		<?php endif; ?>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ha-image-optimizer#ha-io-auditor-settings' ) ); ?>" style="text-decoration: underline; color: #666;">
			⚙️ <?php esc_html_e( 'Auditor-Einstellungen anpassen', 'ha-image-optimizer' ); ?>
		</a>
	</div>

	<!-- Metric Cards Overview -->
	<div class="ha-io-stats-overview">
		<div class="ha-io-stat-box">
			<span class="ha-io-stat-number" id="ha-io-audit-stat-total"><?php echo esc_html( $counts['total'] ); ?></span>
			<span class="ha-io-stat-label"><?php esc_html_e( 'Gescannte Medien', 'ha-image-optimizer' ); ?></span>
			<small style="color: #666; font-size: 11px;"><?php printf( esc_html__( 'Letzter Scan: %s', 'ha-image-optimizer' ), esc_html( $last_scan ) ); ?></small>
		</div>

		<div class="ha-io-stat-box ha-io-stat-box-red">
			<span class="ha-io-stat-number" id="ha-io-audit-stat-unattached"><?php echo esc_html( $counts['unattached_unused'] ); ?></span>
			<span class="ha-io-stat-label"><?php esc_html_e( 'Vollständig verwaist', 'ha-image-optimizer' ); ?></span>
			<strong style="color: #cf222e; font-size: 12px;" id="ha-io-audit-stat-unattached-bytes"><?php echo esc_html( SizeGuard::format_size( $reclaimable['unattached'], 1 ) ); ?></strong>
		</div>

		<div class="ha-io-stat-box ha-io-stat-box-yellow">
			<span class="ha-io-stat-number" id="ha-io-audit-stat-attached"><?php echo esc_html( $counts['attached_unused'] ); ?></span>
			<span class="ha-io-stat-label"><?php esc_html_e( 'Zugeordnet, aber inaktiv', 'ha-image-optimizer' ); ?></span>
			<strong style="color: #9a6700; font-size: 12px;" id="ha-io-audit-stat-attached-bytes"><?php echo esc_html( SizeGuard::format_size( $reclaimable['attached'], 1 ) ); ?></strong>
		</div>

		<div class="ha-io-stat-box ha-io-stat-box-green">
			<span class="ha-io-stat-number" id="ha-io-audit-stat-used"><?php echo esc_html( $counts['used'] ); ?></span>
			<span class="ha-io-stat-label"><?php esc_html_e( 'Aktiv verwendet', 'ha-image-optimizer' ); ?></span>
			<small style="color: #1a7f37; font-size: 11px;"><?php esc_html_e( 'Sicher auf der Website eingebunden', 'ha-image-optimizer' ); ?></small>
		</div>

		<div class="ha-io-stat-box ha-io-stat-box-blue">
			<span class="ha-io-stat-number" id="ha-io-audit-stat-reclaimable"><?php echo esc_html( SizeGuard::format_size( $reclaimable['total'], 1 ) ); ?></span>
			<span class="ha-io-stat-label"><?php esc_html_e( 'Freigebbarer Speicher', 'ha-image-optimizer' ); ?></span>
			<small style="color: #0969da; font-size: 11px;"><?php esc_html_e( 'Inkl. Thumbnails & WebP/AVIF', 'ha-image-optimizer' ); ?></small>
		</div>
	</div>

	<!-- Scan Control Card -->
	<div class="ha-io-card">
		<?php
		$bg_status     = $bg_state['status'] ?? 'idle';
		$bg_is_active  = in_array( $bg_status, [ 'running', 'waiting_night', 'cooldown' ], true );
		$bg_is_paused  = ( 'paused' === $bg_status );
		$bg_processed  = (int) ( $bg_state['processed'] ?? 0 );
		$bg_total      = (int) ( $bg_state['total'] ?? 0 );
		$bg_percent    = $bg_total > 0 ? min( 100, round( ( $bg_processed / $bg_total ) * 100 ) ) : ( 'completed' === $bg_status ? 100 : 0 );
		$night_enabled = (bool) \ha\ImageOptimizer\Common\Settings::get( 'auditor_night_mode_enabled', true );
		$night_start   = (string) \ha\ImageOptimizer\Common\Settings::get( 'auditor_night_start', '02:00' );
		$night_end     = (string) \ha\ImageOptimizer\Common\Settings::get( 'auditor_night_end', '06:00' );
		?>

		<?php if ( $bg_enabled ) : ?>
			<!-- Background Tasks Control Panel -->
			<div id="ha-io-bg-control-panel" style="margin-bottom: 20px; padding-bottom: 18px; border-bottom: 1px solid #e5e5e5;">
				<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 12px;">
					<div>
						<h2 style="margin: 0 0 4px 0; display: flex; align-items: center; gap: 8px;">
							🌙 <?php esc_html_e( 'Automatischer Hintergrund-Scan', 'ha-image-optimizer' ); ?>
							<span id="ha-io-bg-badge" style="font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: normal; background: #eee;">
								<?php echo esc_html( ucfirst( $bg_status ) ); ?>
							</span>
						</h2>
						<p style="margin: 0; color: #555; font-size: 13px;">
							<?php
							if ( $night_enabled ) {
								printf(
									esc_html__( 'Läuft schonend in Teilschritten im Server-Hintergrund – bevorzugt im Nachtfenster (%1$s – %2$s Uhr). Du kannst dieses Browserfenster jederzeit schließen.', 'ha-image-optimizer' ),
									esc_html( $night_start ),
									esc_html( $night_end )
								);
							} else {
								esc_html_e( 'Läuft schonend in Teilschritten asynchron im Server-Hintergrund. Du kannst dieses Browserfenster jederzeit schließen.', 'ha-image-optimizer' );
							}
							?>
						</p>
					</div>

					<!-- Background Action Buttons -->
					<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
						<button type="button" class="button button-primary" id="ha-io-btn-bg-start" <?php disabled( $bg_is_active ); ?>>
							▶️ <span id="ha-io-btn-bg-start-text"><?php echo $bg_is_paused ? esc_html__( 'Hintergrund-Scan fortsetzen', 'ha-image-optimizer' ) : esc_html__( 'Hintergrund-Scan starten', 'ha-image-optimizer' ); ?></span>
						</button>
						<button type="button" class="button button-secondary" id="ha-io-btn-bg-pause" <?php disabled( ! $bg_is_active ); ?>>
							⏸️ <?php esc_html_e( 'Pausieren', 'ha-image-optimizer' ); ?>
						</button>
						<button type="button" class="button button-secondary" id="ha-io-btn-bg-cancel" style="color: #cf222e;" <?php disabled( 'idle' === $bg_status || 'completed' === $bg_status || 'cancelled' === $bg_status ); ?>>
							⏹️ <?php esc_html_e( 'Abbrechen', 'ha-image-optimizer' ); ?>
						</button>
					</div>
				</div>

				<!-- Background Progress Bar -->
				<div id="ha-io-bg-progress-wrapper" style="<?php echo ( 'idle' === $bg_status ) ? 'display: none;' : ''; ?> margin-top: 10px;">
					<div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 12px; font-weight: 600;">
						<span id="ha-io-bg-status-msg"><?php echo esc_html( $bg_state['current_status'] ?? '' ); ?></span>
						<span id="ha-io-bg-progress-counts"><?php echo esc_html( $bg_processed ); ?> / <?php echo esc_html( $bg_total ); ?> (<?php echo esc_html( $bg_percent ); ?>%)</span>
					</div>
					<div class="ha-io-progress" style="height: 10px;">
						<div class="ha-io-progress-bar <?php echo $bg_is_active ? 'ha-io-progress-bar-striped active' : ''; ?>" id="ha-io-bg-progress-bar" style="width: <?php echo esc_attr( $bg_percent ); ?>%;"></div>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
			<div>
				<h2 style="margin: 0 0 6px 0;">🔍 <?php esc_html_e( 'Manueller Sofort-Scan im Browser', 'ha-image-optimizer' ); ?></h2>
				<p style="margin: 0; color: #555;">
					<?php esc_html_e( 'Prüft Bilder live im geöffneten Browserfenster per AJAX mit Vorindexierung und Zero-Downtime-Schutz.', 'ha-image-optimizer' ); ?>
				</p>
			</div>
			<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
				<?php
				$has_cached = ! empty( $counts['total'] );
				$btn_label  = $has_cached
					? esc_html__( 'Scan fortsetzen / Ungescannte prüfen', 'ha-image-optimizer' )
					: esc_html__( 'Scan jetzt starten', 'ha-image-optimizer' );
				$btn_icon   = $has_cached ? '▶️' : '🔍';
				?>
				<button type="button" class="button button-primary button-hero" id="ha-io-btn-start-audit">
					<span id="ha-io-btn-audit-icon"><?php echo $btn_icon; ?></span>
					<span id="ha-io-btn-audit-text"><?php echo esc_html( $btn_label ); ?></span>
				</button>
				<?php if ( ! empty( $counts['total'] ) ) : ?>
					<button type="button" class="button button-secondary" id="ha-io-btn-clear-audit">
						🗑️ <?php esc_html_e( 'Cache leeren', 'ha-image-optimizer' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>

		<!-- Skip Scanned Media Option (Incremental Scan) -->
		<div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid #eee; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
			<label for="ha-io-audit-skip-scanned" style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; font-weight: 500; user-select: none;">
				<input type="checkbox" id="ha-io-audit-skip-scanned" name="ha_io_audit_skip_scanned" value="1" <?php checked( $has_cached ); ?>>
				<span>
					⏩ <strong><?php esc_html_e( 'Bereits geprüfte Medien überspringen', 'ha-image-optimizer' ); ?></strong>
					<span style="color: #666; font-weight: normal; margin-left: 4px;">
						(<?php esc_html_e( 'Inkrementeller Scan / Fortsetzen – spart Zeit bei großen Mediatheken', 'ha-image-optimizer' ); ?>)
					</span>
				</span>
			</label>
			<span id="ha-io-audit-cached-badge" style="font-size: 12px; color: #0969da; background: #e7f3ff; padding: 4px 10px; border-radius: 12px; font-weight: 500; <?php echo empty( $counts['total'] ) ? 'display: none;' : ''; ?>">
				ℹ️ <span id="ha-io-audit-cached-count"><?php echo (int) $counts['total']; ?></span> <?php esc_html_e( 'Medien aktuell im Cache', 'ha-image-optimizer' ); ?>
			</span>
		</div>

		<!-- Live Progress Bar (Hidden by default) -->
		<div id="ha-io-audit-progress-container" style="display: none; margin-top: 20px;">
			<div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-weight: 600;">
				<span id="ha-io-audit-progress-status"><?php esc_html_e( 'Analysiere Mediathek...', 'ha-image-optimizer' ); ?></span>
				<span id="ha-io-audit-progress-counts">0 / 0 (0%)</span>
			</div>
			<div class="ha-io-progress">
				<div class="ha-io-progress-bar ha-io-progress-bar-striped active" id="ha-io-audit-progress-bar" style="width: 0%;"></div>
			</div>
			<div style="margin-top: 8px; font-size: 12px; color: #666;">
				<span class="ha-io-pulse-dot"></span>
				<span id="ha-io-audit-current-item"><?php esc_html_e( 'Bereite Queue vor...', 'ha-image-optimizer' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Results Card -->
	<div class="ha-io-card" id="ha-io-audit-results-card">
		<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
			<!-- Filter Tabs -->
			<div class="ha-io-filter-pills" id="ha-io-audit-filters">
				<button type="button" class="ha-io-filter-btn active" data-filter="all">
					<?php esc_html_e( 'Alle', 'ha-image-optimizer' ); ?> (<span class="count-all"><?php echo count( $items ); ?></span>)
				</button>
				<button type="button" class="ha-io-filter-btn filter-red" data-filter="unattached_unused">
					🔴 <?php esc_html_e( 'Vollständig verwaist', 'ha-image-optimizer' ); ?> (<span class="count-unattached"><?php echo (int) $counts['unattached_unused']; ?></span>)
				</button>
				<button type="button" class="ha-io-filter-btn filter-yellow" data-filter="attached_unused">
					🟡 <?php esc_html_e( 'Zugeordnet, aber inaktiv', 'ha-image-optimizer' ); ?> (<span class="count-attached"><?php echo (int) $counts['attached_unused']; ?></span>)
				</button>
				<button type="button" class="ha-io-filter-btn filter-green" data-filter="used">
					🟢 <?php esc_html_e( 'Aktiv verwendet', 'ha-image-optimizer' ); ?> (<span class="count-used"><?php echo (int) $counts['used']; ?></span>)
				</button>
			</div>

			<!-- Search & Bulk Actions -->
			<div style="display: flex; gap: 8px; align-items: center;">
				<input type="search" id="ha-io-audit-search" placeholder="<?php esc_attr_e( 'Nach Datei oder Beitrag suchen...', 'ha-image-optimizer' ); ?>" style="min-width: 240px;">
				<button type="button" class="button button-secondary" id="ha-io-btn-bulk-trash" style="color: #cf222e;" disabled>
					🗑️ <?php esc_html_e( 'Ausgewählte in Papierkorb', 'ha-image-optimizer' ); ?>
				</button>
			</div>
		</div>

		<!-- Table -->
		<table class="wp-list-table widefat fixed striped ha-io-table" id="ha-io-audit-table">
			<thead>
				<tr>
					<th scope="col" class="manage-column column-cb check-column" style="width: 32px;">
						<input type="checkbox" id="ha-io-check-all">
					</th>
					<th scope="col" style="width: 60px;"><?php esc_html_e( 'Vorschau', 'ha-image-optimizer' ); ?></th>
					<th scope="col" style="width: 220px;"><?php esc_html_e( 'Datei & Datum', 'ha-image-optimizer' ); ?></th>
					<th scope="col" style="width: 140px;"><?php esc_html_e( 'Belegter Speicher', 'ha-image-optimizer' ); ?></th>
					<th scope="col" style="width: 160px;"><?php esc_html_e( 'Verwendungs-Status', 'ha-image-optimizer' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Fundorte & Verwendungs-Nachweise', 'ha-image-optimizer' ); ?></th>
					<th scope="col" style="width: 130px; text-align: right;"><?php esc_html_e( 'Aktionen', 'ha-image-optimizer' ); ?></th>
				</tr>
			</thead>
			<tbody id="ha-io-audit-tbody">
				<?php if ( empty( $items ) ) : ?>
					<tr id="ha-io-audit-empty-row">
						<td colspan="7" style="text-align: center; padding: 40px; color: #666;">
							<div style="font-size: 32px; margin-bottom: 8px;">🔍</div>
							<strong><?php esc_html_e( 'Noch kein Scan durchgeführt.', 'ha-image-optimizer' ); ?></strong><br>
							<?php esc_html_e( 'Klicke oben auf "Scan jetzt starten", um deine Mediathek auf ungenutzte Bilder zu prüfen.', 'ha-image-optimizer' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $items as $item ) : ?>
						<?php
						$status = $item['status'] ?? 'unattached_unused';
						$badge_class = 'ha-io-badge-gray';
						$badge_text  = __( 'Unbekannt', 'ha-image-optimizer' );

						if ( 'used' === $status ) {
							$badge_class = 'ha-io-badge-green';
							$badge_text  = '🟢 ' . __( 'Aktiv verwendet', 'ha-image-optimizer' );
						} elseif ( 'attached_unused' === $status ) {
							$badge_class = 'ha-io-badge-yellow';
							$badge_text  = '🟡 ' . __( 'Zugeordnet, inaktiv', 'ha-image-optimizer' );
						} elseif ( 'unattached_unused' === $status ) {
							$badge_class = 'ha-io-badge-red';
							$badge_text  = '🔴 ' . __( 'Vollständig verwaist', 'ha-image-optimizer' );
						}
						?>
						<tr data-id="<?php echo esc_attr( $item['id'] ); ?>" data-status="<?php echo esc_attr( $status ); ?>">
							<th scope="row" class="check-column">
								<?php if ( 'used' !== $status ) : ?>
									<input type="checkbox" class="ha-io-audit-cb" value="<?php echo esc_attr( $item['id'] ); ?>">
								<?php endif; ?>
							</th>
							<td>
								<?php if ( ! empty( $item['thumb_url'] ) ) : ?>
									<img src="<?php echo esc_url( $item['thumb_url'] ); ?>" alt="" style="width: 48px; height: 48px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">
								<?php else : ?>
									<div style="width: 48px; height: 48px; background: #eee; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999;">—</div>
								<?php endif; ?>
							</td>
							<td>
								<strong>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $item['id'] . '&action=edit' ) ); ?>" target="_blank" title="<?php esc_attr_e( 'In Mediathek bearbeiten', 'ha-image-optimizer' ); ?>">
										<?php echo esc_html( $item['filename'] ); ?>
									</a>
								</strong>
								<div style="font-size: 11px; color: #666; margin-top: 3px;">
									ID #<?php echo esc_html( $item['id'] ); ?> &bull; <?php echo esc_html( $item['date'] ?? '' ); ?>
								</div>
							</td>
							<td>
								<strong><?php echo esc_html( $item['disk_human'] ?? '0 B' ); ?></strong>
								<div style="font-size: 11px; color: #666;">
									<?php esc_html_e( 'Inkl. WebP/AVIF', 'ha-image-optimizer' ); ?>
								</div>
							</td>
							<td>
								<span class="ha-io-badge <?php echo esc_attr( $badge_class ); ?>">
									<?php echo esc_html( $badge_text ); ?>
								</span>
							</td>
							<td>
								<?php if ( ! empty( $item['references'] ) && is_array( $item['references'] ) ) : ?>
									<ul style="margin: 0; padding-left: 18px; font-size: 12px; color: #1a7f37; line-height: 1.6;">
										<?php foreach ( $item['references'] as $ref ) : ?>
											<?php
											$ref_label = is_array( $ref ) ? ( $ref['label'] ?? '' ) : (string) $ref;
											$ref_url   = is_array( $ref ) ? ( $ref['url'] ?? '' ) : '';
											?>
											<li>
												<?php if ( ! empty( $ref_url ) ) : ?>
													<a href="<?php echo esc_url( $ref_url ); ?>" target="_blank" style="color: #0969da; text-decoration: underline; font-weight: 500;">
														<?php echo esc_html( $ref_label ); ?> ↗
													</a>
												<?php else : ?>
													<?php echo esc_html( $ref_label ); ?>
												<?php endif; ?>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php elseif ( ! empty( $item['parent_title'] ) ) : ?>
									<span style="font-size: 12px; color: #9a6700; line-height: 1.5; display: inline-block;">
										<?php
										$p_url = ! empty( $item['parent_url'] ) ? $item['parent_url'] : admin_url( 'post.php?post=' . (int) $item['parent_id'] . '&action=edit' );
										printf(
											/* translators: 1: edit URL, 2: parent post title, 3: parent post ID */
											wp_kses(
												__( 'Zugeordnet zu: <a href="%1$s" target="_blank" style="color: #0969da; text-decoration: underline; font-weight: 500;">"%2$s" (#%3$d) ↗</a>, aber im Text oder Beitragsbild nicht auffindbar.', 'ha-image-optimizer' ),
												[ 'a' => [ 'href' => [], 'target' => [], 'style' => [] ] ]
											),
											esc_url( $p_url ),
											esc_html( $item['parent_title'] ),
											(int) $item['parent_id']
										);
										?>
									</span>
								<?php else : ?>
									<span style="font-size: 12px; color: #cf222e;">
										<em><?php esc_html_e( 'Keine Verwendung oder Zuordnung gefunden. Kann sicher bereinigt werden.', 'ha-image-optimizer' ); ?></em>
									</span>
								<?php endif; ?>
							</td>
							<td style="text-align: right;">
								<?php if ( 'used' !== $status ) : ?>
									<button type="button" class="button button-small button-link-delete ha-io-btn-trash-single" data-id="<?php echo esc_attr( $item['id'] ); ?>">
										🗑️ <?php esc_html_e( 'Papierkorb', 'ha-image-optimizer' ); ?>
									</button>
								<?php else : ?>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $item['id'] . '&action=edit' ) ); ?>" class="button button-small" target="_blank">
										<?php esc_html_e( 'Ansehen', 'ha-image-optimizer' ); ?>
									</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
