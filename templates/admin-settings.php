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
			⚙️ <?php esc_html_e( 'Einstellungen', 'ha-image-optimizer' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'upload.php?page=ha-image-optimizer-bulk' ) ); ?>" class="nav-tab">
			⚡ <?php esc_html_e( 'Massenoptimierung', 'ha-image-optimizer' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'upload.php?page=ha-image-optimizer-auditor' ) ); ?>" class="nav-tab">
			🔍 <?php esc_html_e( 'Verwaiste Medien (Auditor)', 'ha-image-optimizer' ); ?>
		</a>
	</nav>

	<h1>⚡ <?php esc_html_e( 'HA Image Optimizer: Einstellungen & System-Status', 'ha-image-optimizer' ); ?></h1>

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Einstellungen wurden erfolgreich gespeichert.', 'ha-image-optimizer' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Quick-Start Banner -->
	<div class="ha-io-quick-start-card">
		<div class="ha-io-quick-start-text">
			<span class="ha-io-pulse-dot"></span>
			<div>
				<strong><?php esc_html_e( 'Massenoptimierung bereit:', 'ha-image-optimizer' ); ?></strong>
				<span>
					<?php
					printf(
						/* translators: 1: unoptimized images count, 2: total images count */
						esc_html__( '%1$d von %2$d Bildern können noch optimiert werden.', 'ha-image-optimizer' ),
						(int) $stats['unoptimized'],
						(int) $stats['total_images']
					);
					?>
				</span>
			</div>
		</div>
		<a href="<?php echo esc_url( admin_url( 'upload.php?page=ha-image-optimizer-bulk' ) ); ?>" class="button button-primary button-hero ha-io-btn-hero">
			⚡ <?php esc_html_e( 'Massenoptimierung jetzt starten', 'ha-image-optimizer' ); ?>
		</a>
	</div>

	<!-- 1. System Diagnostics Widget -->
	<div class="ha-io-card ha-io-card-diagnostic">
		<h2>🖥️ <?php esc_html_e( 'Server-Fähigkeiten & Diagnose', 'ha-image-optimizer' ); ?></h2>
		<div class="ha-io-diag-grid">
			<div class="ha-io-diag-item">
				<span class="ha-io-diag-label"><?php esc_html_e( 'PHP Version:', 'ha-image-optimizer' ); ?></span>
				<span class="ha-io-diag-val <?php echo $caps['php']['compatible'] ? 'ha-io-status-green' : 'ha-io-status-red'; ?>">
					<?php echo esc_html( $caps['php']['version'] ); ?>
					<?php echo $caps['php']['compatible'] ? '✅' : '❌ (mind. 8.1 erforderlich)'; ?>
				</span>
			</div>

			<div class="ha-io-diag-item">
				<span class="ha-io-diag-label"><?php esc_html_e( 'Aktiver Treiber:', 'ha-image-optimizer' ); ?></span>
				<span class="ha-io-diag-val ha-io-status-green">
					<?php echo esc_html( strtoupper( $caps['best_driver'] ) ); ?> ✅
				</span>
			</div>

			<div class="ha-io-diag-item">
				<span class="ha-io-diag-label"><?php esc_html_e( 'WebP Unterstützung:', 'ha-image-optimizer' ); ?></span>
				<span class="ha-io-diag-val <?php echo $caps['supports_webp'] ? 'ha-io-status-green' : 'ha-io-status-red'; ?>">
					<?php echo $caps['supports_webp'] ? esc_html__( 'Verfügbar ✅', 'ha-image-optimizer' ) : esc_html__( 'Nicht verfügbar ❌', 'ha-image-optimizer' ); ?>
				</span>
			</div>

			<div class="ha-io-diag-item">
				<span class="ha-io-diag-label"><?php esc_html_e( 'AVIF Unterstützung:', 'ha-image-optimizer' ); ?></span>
				<span class="ha-io-diag-val <?php echo $caps['supports_avif'] ? 'ha-io-status-green' : 'ha-io-status-yellow'; ?>">
					<?php echo $caps['supports_avif'] ? esc_html__( 'Verfügbar ✅', 'ha-image-optimizer' ) : esc_html__( 'Nicht verfügbar (PHP 8.1+ / libavif)', 'ha-image-optimizer' ); ?>
				</span>
			</div>

			<div class="ha-io-diag-item">
				<span class="ha-io-diag-label"><?php esc_html_e( 'Webserver:', 'ha-image-optimizer' ); ?></span>
				<span class="ha-io-diag-val">
					<?php echo esc_html( $caps['server']['software'] ?: 'Unbekannt' ); ?>
					<?php if ( $caps['server']['supports_htaccess'] ) : ?>
						<span class="ha-io-badge ha-io-badge-green"><?php esc_html_e( '.htaccess unterstützt', 'ha-image-optimizer' ); ?></span>
					<?php else : ?>
						<span class="ha-io-badge ha-io-badge-blue"><?php esc_html_e( 'Nginx / Standalone', 'ha-image-optimizer' ); ?></span>
					<?php endif; ?>
				</span>
			</div>

			<div class="ha-io-diag-item">
				<span class="ha-io-diag-label"><?php esc_html_e( 'Speicherlimit:', 'ha-image-optimizer' ); ?></span>
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
			<h2>⚙️ <?php esc_html_e( 'Kompressions-Einstellungen', 'ha-image-optimizer' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="compression_original"><?php esc_html_e( 'Originalbild-Optimierung (Master)', 'ha-image-optimizer' ); ?></label>
					</th>
					<td>
						<select name="ha_io_settings[compression_original]" id="compression_original">
							<option value="lossless" <?php selected( $settings['compression_original'], 'lossless' ); ?>>
								<?php esc_html_e( 'Verlustfrei (Standard: 100% Pixeltreue, EXIF gestrippt)', 'ha-image-optimizer' ); ?>
							</option>
							<option value="smart" <?php selected( $settings['compression_original'], 'smart' ); ?>>
								<?php esc_html_e( 'Smart (~82% Qualität, maximale Ersparnis)', 'ha-image-optimizer' ); ?>
							</option>
							<option value="ultra" <?php selected( $settings['compression_original'], 'ultra' ); ?>>
								<?php esc_html_e( 'Ultra (~72% Qualität, aggressiv)', 'ha-image-optimizer' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Wir empfehlen "Verlustfrei", damit dein Master-Foto in höchster Qualität erhalten bleibt.', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="compression_sizes"><?php esc_html_e( 'Zwischengrößen-Optimierung (Thumbnails)', 'ha-image-optimizer' ); ?></label>
					</th>
					<td>
						<select name="ha_io_settings[compression_sizes]" id="compression_sizes">
							<option value="smart" <?php selected( $settings['compression_sizes'], 'smart' ); ?>>
								<?php esc_html_e( 'Smart (Empfohlen: ~82% Qualität, visuell perfekt)', 'ha-image-optimizer' ); ?>
							</option>
							<option value="ultra" <?php selected( $settings['compression_sizes'], 'ultra' ); ?>>
								<?php esc_html_e( 'Ultra (~72% Qualität, extrem klein)', 'ha-image-optimizer' ); ?>
							</option>
							<option value="lossless" <?php selected( $settings['compression_sizes'], 'lossless' ); ?>>
								<?php esc_html_e( 'Verlustfrei (Lossless)', 'ha-image-optimizer' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Steuert die Kompression aller Web-Zwischengrößen (thumbnail, medium, large etc.).', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Next-Gen Bildformate', 'ha-image-optimizer' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="ha_io_settings[generate_webp]" value="1" <?php checked( $settings['generate_webp'] ); ?>>
								<strong><?php esc_html_e( 'WebP-Versionen automatisch generieren', 'ha-image-optimizer' ); ?></strong>
							</label>
							<br><br>
							<label>
								<input type="checkbox" name="ha_io_settings[generate_avif]" value="1" <?php checked( $settings['generate_avif'] ); ?> <?php disabled( ! $caps['supports_avif'] ); ?>>
								<strong><?php esc_html_e( 'AVIF-Versionen automatisch generieren (Next-Gen+)', 'ha-image-optimizer' ); ?></strong>
							</label>
							<?php if ( ! $caps['supports_avif'] ) : ?>
								<p class="description ha-io-text-yellow">
									<?php esc_html_e( 'AVIF ist auf deinem Server aktuell nicht verfügbar (erfordert PHP 8.1+ GD oder ImageMagick mit AVIF-Support).', 'ha-image-optimizer' ); ?>
								</p>
							<?php endif; ?>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Auslieferungsmethode (Delivery)', 'ha-image-optimizer' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="ha_io_settings[delivery_mode]" value="content_filter" <?php checked( $settings['delivery_mode'], 'content_filter' ); ?>>
								<strong><?php esc_html_e( '<picture>-Tag Ersetzung (Standard & Universell)', 'ha-image-optimizer' ); ?></strong>
								<span class="description"><?php esc_html_e( 'Ersetzt <img> durch <picture> und Inline-Styles durch CSS image-set(). Funktioniert mit allen Servern und CDNs.', 'ha-image-optimizer' ); ?></span>
							</label>
							<br><br>
							<label>
								<input type="radio" name="ha_io_settings[delivery_mode]" value="htaccess" <?php checked( $settings['delivery_mode'], 'htaccess' ); ?> <?php disabled( ! $caps['server']['supports_htaccess'] ); ?>>
								<strong><?php esc_html_e( '.htaccess Server-Rewrite (0 ms PHP-Overhead)', 'ha-image-optimizer' ); ?></strong>
								<span class="description"><?php esc_html_e( 'Schreibt Regeln in wp-content/uploads/.htaccess. Apache liefert WebP transparent aus.', 'ha-image-optimizer' ); ?></span>
							</label>
							<br><br>
							<label>
								<input type="radio" name="ha_io_settings[delivery_mode]" value="off" <?php checked( $settings['delivery_mode'], 'off' ); ?>>
								<strong><?php esc_html_e( 'Deaktiviert', 'ha-image-optimizer' ); ?></strong>
								<span class="description"><?php esc_html_e( 'Erzeugt WebP/AVIF nur auf der Festplatte, ersetzt aber keine URLs im Frontend.', 'ha-image-optimizer' ); ?></span>
							</label>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Full-Page Output Buffer', 'ha-image-optimizer' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ha_io_settings[enable_full_buffer]" value="1" <?php checked( $settings['enable_full_buffer'] ); ?>>
							<strong><?php esc_html_e( 'Vollständigen Seitenpuffer durchsuchen (ob_start)', 'ha-image-optimizer' ); ?></strong>
						</label>
						<p class="description">
							<?php esc_html_e( 'Findet auch hartnäckige Page-Builder-Bilder im gesamten HTML. Empfohlen in Kombination mit einem Caching-Plugin (WP Rocket, LiteSpeed Cache etc.).', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Automatische Größenbeschränkung (Resizing)', 'ha-image-optimizer' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ha_io_settings[resize_large_images]" value="1" <?php checked( $settings['resize_large_images'] ); ?>>
							<?php esc_html_e( 'Übergroße Uploads automatisch herunterskalieren auf maximal:', 'ha-image-optimizer' ); ?>
						</label>
						<br><br>
						<label>
							<?php esc_html_e( 'Breite:', 'ha-image-optimizer' ); ?>
							<input type="number" name="ha_io_settings[max_width]" value="<?php echo esc_attr( $settings['max_width'] ); ?>" min="500" max="10000" step="10"> px
						</label>
						&nbsp;&nbsp;
						<label>
							<?php esc_html_e( 'Höhe:', 'ha-image-optimizer' ); ?>
							<input type="number" name="ha_io_settings[max_height]" value="<?php echo esc_attr( $settings['max_height'] ); ?>" min="500" max="10000" step="10"> px
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Backup & Metadaten', 'ha-image-optimizer' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="ha_io_settings[backup_originals]" value="1" <?php checked( $settings['backup_originals'] ); ?>>
								<strong><?php esc_html_e( 'Unverändertes Originalbild im geschützten Backup-Ordner sichern', 'ha-image-optimizer' ); ?></strong>
							</label>
							<br><br>
							<label>
								<input type="checkbox" name="ha_io_settings[keep_exif]" value="1" <?php checked( $settings['keep_exif'] ); ?>>
								<?php esc_html_e( 'EXIF-Kameradaten und Urheberrechte beibehalten (erhöht Dateigröße)', 'ha-image-optimizer' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Theme- & Benutzerdefinierte Ordner', 'ha-image-optimizer' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="ha_io_settings[optimize_custom_folders]" value="1" <?php checked( $settings['optimize_custom_folders'] ); ?>>
								<strong><?php esc_html_e( 'Bilder außerhalb der Mediathek scannen und optimieren', 'ha-image-optimizer' ); ?></strong>
							</label>
							<p class="description">
								<?php esc_html_e( 'Findet statische Bilddateien in Theme-Ordnern oder benutzerdefinierten Verzeichnissen, komprimiert diese und generiert WebP/AVIF-Dateien.', 'ha-image-optimizer' ); ?>
							</p>
							<br>
							<label for="ha-io-custom-folders">
								<strong><?php esc_html_e( 'Zu durchsuchende Verzeichnisse (eine Zeile pro Pfad, relativ zu wp-content):', 'ha-image-optimizer' ); ?></strong>
							</label>
							<textarea id="ha-io-custom-folders" name="ha_io_settings[custom_folders]" rows="3" class="large-text code"><?php echo esc_textarea( $settings['custom_folders'] ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Standard: themes. Beispiel: themes oder uploads/custom-folder', 'ha-image-optimizer' ); ?>
							</p>
						</fieldset>
					</td>
				</tr>
			</table>
		</div>

		<!-- 3. Medien-Auditor: Erweiterter Tiefenscan & Schutzfrist -->
		<div class="ha-io-card" id="ha-io-auditor-settings">
			<h2>🔍 <?php esc_html_e( 'Medien-Auditor: Universeller Tiefenscan & Schutzfrist', 'ha-image-optimizer' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Konfiguriere, wie gründlich der Medien-Auditor deine Datenbank nach Bildreferenzen durchsucht und wie neu hochgeladene Medien geschützt werden.', 'ha-image-optimizer' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Performance-Modus (Smart Early-Exit)', 'ha-image-optimizer' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ha_io_settings[auditor_fast_mode]" value="1" <?php checked( $settings['auditor_fast_mode'] ?? true ); ?>>
							<strong><?php esc_html_e( 'Tiefenscan abbrechen, sobald ein Bild nachweislich verwendet wird (Empfohlen)', 'ha-image-optimizer' ); ?></strong>
						</label>
						<p class="description">
							<?php esc_html_e( 'Reduziert Datenbankabfragen um bis zu 90 %. Sobald ein Bild als Beitragsbild, im Seiteninhalt, im Theme oder Widget gefunden wurde, werden nachfolgende schwere Tabellenscans für dieses Bild übersprungen.', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Universeller Datenbank-Scan', 'ha-image-optimizer' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ha_io_settings[auditor_generic_db_scan]" value="1" <?php checked( $settings['auditor_generic_db_scan'] ?? true ); ?>>
							<strong><?php esc_html_e( 'Alle Drittanbieter-Tabellen automatisch scannen (Slider, Formulare, Page-Builder, Plugins)', 'ha-image-optimizer' ); ?></strong>
						</label>
						<p class="description">
							<?php esc_html_e( 'Durchsucht alle Tabellen mit Text- und JSON-Spalten nach Bild-IDs, relativen Pfaden und Dateinamen. Log-, Cache- und Statistik-Tabellen werden automatisch übersprungen.', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Schutzfrist für neue Uploads', 'ha-image-optimizer' ); ?></th>
					<td>
						<label>
							<input type="number" name="ha_io_settings[auditor_safety_buffer_days]" value="<?php echo esc_attr( $settings['auditor_safety_buffer_days'] ?? 0 ); ?>" min="0" max="365" style="width: 80px;">
							<?php esc_html_e( 'Tage', 'ha-image-optimizer' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Bilder, die innerhalb dieses Zeitraums hochgeladen wurden, werden vor dem Löschen geschützt, auch wenn sie noch nicht veröffentlicht wurden (0 = deaktiviert, z. B. 14 Tage empfohlen für aktive Redaktionen).', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Zusätzliche Tabellen scannen', 'ha-image-optimizer' ); ?></th>
					<td>
						<label for="ha-io-auditor-custom-tables">
							<?php esc_html_e( 'Explizite Tabellennamen (eine Tabelle pro Zeile, ohne oder mit Tabellen-Präfix):', 'ha-image-optimizer' ); ?>
						</label>
						<textarea id="ha-io-auditor-custom-tables" name="ha_io_settings[auditor_custom_tables]" rows="3" class="large-text code"><?php echo esc_textarea( $settings['auditor_custom_tables'] ?? '' ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Optional. Wird nur benötigt, falls du bestimmte Tabellen gezielt erzwingen möchtest. Standardmäßig erkennt der Auditor alle relevanten Tabellen automatisch.', 'ha-image-optimizer' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- 4. HA Standards Data Protection on Uninstall -->
		<div class="ha-io-card ha-io-card-uninstall">
			<h2>🗑️ <?php esc_html_e( 'Deinstallations-Schutzwall (HA-Standard)', 'ha-image-optimizer' ); ?></h2>
			<p>
				<?php esc_html_e( 'Gemäß den HA-Entwicklungsstandards werden deine Bild-Backups, WebP-Dateien und Metadaten bei einer Deinstallation niemals automatisch gelöscht, um Datenverlust zu verhindern.', 'ha-image-optimizer' ); ?>
			</p>
			<label>
				<input type="checkbox" name="ha_io_delete_data_on_uninstall" value="1" <?php checked( $delete_data_on_uninstall ); ?>>
				<strong class="ha-io-text-red">
					<?php esc_html_e( 'Vollständige Datenlöschung bei Deinstallation:', 'ha-image-optimizer' ); ?>
				</strong>
				<?php esc_html_e( 'Alle Backups in /ha-backups/, WebP/AVIF-Dateien und Datenbank-Statistiken unwiderruflich löschen, wenn das Plugin deinstalliert wird.', 'ha-image-optimizer' ); ?>
			</label>
		</div>

		<?php submit_button( esc_html__( 'Einstellungen speichern', 'ha-image-optimizer' ) ); ?>
	</form>

	<!-- 4. Nginx Configuration Helper Snippet -->
	<div class="ha-io-card ha-io-card-nginx">
		<h2>🌐 <?php esc_html_e( 'Nginx Konfigurations-Hilfe (Optional für Nginx-Server)', 'ha-image-optimizer' ); ?></h2>
		<p>
			<?php esc_html_e( 'Falls dein Server auf Nginx läuft und du transparentes WebP-Serving ohne HTML-Änderung wünschst, füge folgenden Block in deine Nginx-Konfiguration ein:', 'ha-image-optimizer' ); ?>
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
