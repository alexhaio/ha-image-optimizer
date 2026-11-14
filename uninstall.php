<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package HA_Image_Optimizer
 */

// If uninstall not called from WordPress, exit immediately.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// -----------------------------------------------------------------------------
// 1. SICHERHEITSPRÜFUNG: Datenlöschung standardmäßig VERBOTEN (HA-Standard)
// -----------------------------------------------------------------------------
$delete_data = (bool) get_option( 'ha_io_delete_data_on_uninstall', false );

if ( ! $delete_data ) {
	// Standardfall: Daten, Backups und Optionen für eine künftige Reaktivierung unberührt lassen.
	return;
}

// -----------------------------------------------------------------------------
// 2. DATENBEREINIGUNG (Nur wenn explizit vom Admin aktiviert)
// -----------------------------------------------------------------------------
global $wpdb;

// a) Löschen aller gespeicherten Attachment-Metadaten (_ha_io_data, _ha_io_custom_level, _ha_io_backup_origin)
delete_metadata( 'post', 0, '_ha_io_data', '', true );
delete_metadata( 'post', 0, '_ha_io_custom_level', '', true );
delete_metadata( 'post', 0, '_ha_io_backup_origin', '', true );

// b) Generierte WebP/AVIF-Companion-Dateien in Uploads und konfigurierten Custom-Ordnern löschen
// (Nur eindeutige Begleitdateien wie foto.jpg.webp / foto.png.avif – niemals eigenständige Original-.webp-Uploads)
$settings_raw = get_option( 'ha_io_settings', [] );
$upload_dir   = wp_upload_dir();
$scan_roots   = [];

if ( ! empty( $upload_dir['basedir'] ) && is_dir( $upload_dir['basedir'] ) ) {
	$scan_roots[] = $upload_dir['basedir'];
}

if ( is_array( $settings_raw ) && ! empty( $settings_raw['custom_folders'] ) ) {
	$content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
	$lines       = explode( "\n", str_replace( "\r", '', (string) $settings_raw['custom_folders'] ) );
	foreach ( $lines as $line ) {
		$rel = trim( str_replace( '\\', '/', $line ), " \t\n\r\0\x0B/" );
		if ( '' !== $rel && ! str_contains( $rel, '..' ) ) {
			$dir = rtrim( $content_dir, '/\\' ) . '/' . $rel;
			if ( is_dir( $dir ) ) {
				$scan_roots[] = $dir;
			}
		}
	}
}

foreach ( array_unique( $scan_roots ) as $root_dir ) {
	try {
		$ng_iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ( $ng_iter as $ng_item ) {
			if ( ! $ng_item->isFile() ) {
				continue;
			}
			$path = $ng_item->getPathname();
			if ( preg_match( '/\.(?:jpe?g|png|gif|webp)\.(?:webp|avif)$/i', $path ) ) {
				@unlink( $path );
			}
		}
	} catch ( Throwable ) {
		continue;
	}
}

// c) Optionen und Transients bereinigen
delete_option( 'ha_io_settings' );
delete_option( 'ha_io_delete_data_on_uninstall' );
delete_option( 'ha_io_custom_data' );
delete_option( 'ha_io_audit_results' );
delete_option( 'ha_io_audit_background_state' );
delete_option( 'ha_io_audit_reverse_index' );
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE %s
		OR option_name LIKE %s
		OR option_name LIKE %s
		OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_ha_io_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_ha_io_' ) . '%',
		$wpdb->esc_like( '_site_transient_ha_io_' ) . '%',
		$wpdb->esc_like( '_site_transient_timeout_ha_io_' ) . '%'
	)
);

// d) Bereinigung des Backup-Verzeichnisses (wp-content/uploads/ha-backups/)
$backup_dir = trailingslashit( $upload_dir['basedir'] ) . 'ha-backups';

if ( is_dir( $backup_dir ) ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $backup_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $item ) {
		if ( $item->isDir() ) {
			@rmdir( $item->getRealPath() );
		} else {
			@unlink( $item->getRealPath() );
		}
	}
	@rmdir( $backup_dir );
}

// d) Entfernen von generierten .htaccess-Regeln in wp-content/uploads/.htaccess
$htaccess_file = trailingslashit( $upload_dir['basedir'] ) . '.htaccess';
if ( file_exists( $htaccess_file ) && is_writable( $htaccess_file ) ) {
	$content = file_get_contents( $htaccess_file );
	if ( false !== $content && str_contains( $content, '# BEGIN HA_IMAGE_OPTIMIZER' ) ) {
		$clean_content = preg_replace( '/# BEGIN HA_IMAGE_OPTIMIZER.*?# END HA_IMAGE_OPTIMIZER\s*/s', '', $content );
		if ( null !== $clean_content ) {
			if ( trim( $clean_content ) === '' ) {
				@unlink( $htaccess_file );
			} else {
				file_put_contents( $htaccess_file, $clean_content );
			}
		}
	}
}
