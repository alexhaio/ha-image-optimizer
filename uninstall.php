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

// a) Löschen aller gespeicherten Attachment-Metadaten (_ha_io_data & _ha_io_custom_level)
delete_metadata( 'post', 0, '_ha_io_data', '', true );
delete_metadata( 'post', 0, '_ha_io_custom_level', '', true );

// b) Optionen und Transients bereinigen
delete_option( 'ha_io_settings' );
delete_option( 'ha_io_delete_data_on_uninstall' );
delete_option( 'ha_io_custom_data' );
delete_option( 'ha_io_audit_results' );
delete_option( 'ha_io_audit_background_state' );
delete_option( 'ha_io_audit_reverse_index' );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ha_io_%' OR option_name LIKE '_transient_timeout_ha_io_%'" );

// c) Bereinigung des Backup-Verzeichnisses (wp-content/uploads/ha-backups/)
$upload_dir = wp_upload_dir();
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
