<?php
/**
 * Admin Pages Controller (Settings & Bulk Dashboard).
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Admin;

use ha\ImageOptimizer\Common\Settings;
use ha\ImageOptimizer\Common\Environment;
use ha\ImageOptimizer\Bulk\ProgressTracker;
use ha\ImageOptimizer\Media\CustomFolderProcessor;
use ha\ImageOptimizer\Auditor\MediaAuditor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdminPage
 */
class AdminPage {

	/**
	 * Register menu hooks and asset loaders.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu_pages' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'admin_post_ha_io_save_settings', [ __CLASS__, 'handle_save_settings' ] );
		add_filter( 'plugin_action_links_' . HA_IO_BASENAME, [ __CLASS__, 'add_plugin_action_links' ] );
	}

	/**
	 * Add custom action links on wp-admin/plugins.php.
	 *
	 * @param array<string, string> $links Default action links.
	 * @return array<string, string>
	 */
	public static function add_plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=ha-image-optimizer' ) ),
			esc_html__( 'Einstellungen', 'ha-image-optimizer' )
		);

		$bulk_link = sprintf(
			'<a href="%s" style="font-weight: 600; color: #1a7f37;">%s</a>',
			esc_url( admin_url( 'upload.php?page=ha-image-optimizer-bulk' ) ),
			esc_html__( '⚡ Massenoptimierung', 'ha-image-optimizer' )
		);

		$auditor_link = sprintf(
			'<a href="%s" style="color: #8250df;">%s</a>',
			esc_url( admin_url( 'upload.php?page=ha-image-optimizer-auditor' ) ),
			esc_html__( '🔍 Verwaiste Medien', 'ha-image-optimizer' )
		);

		array_unshift( $links, $auditor_link );
		array_unshift( $links, $bulk_link );
		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Add admin menu entries.
	 *
	 * @return void
	 */
	public static function add_menu_pages(): void {
		// Settings Page under Settings
		add_options_page(
			esc_html__( 'HA Image Optimizer', 'ha-image-optimizer' ),
			esc_html__( 'HA Image Optimizer', 'ha-image-optimizer' ),
			'manage_options',
			'ha-image-optimizer',
			[ __CLASS__, 'render_settings_page' ]
		);

		// Bulk Optimizer under Media
		add_media_page(
			esc_html__( 'Massenoptimierung', 'ha-image-optimizer' ),
			esc_html__( 'Massenoptimierung', 'ha-image-optimizer' ),
			'manage_options',
			'ha-image-optimizer-bulk',
			[ __CLASS__, 'render_bulk_page' ]
		);

		// Media Auditor under Media
		add_media_page(
			esc_html__( 'Verwaiste Medien', 'ha-image-optimizer' ),
			esc_html__( 'Verwaiste Medien', 'ha-image-optimizer' ),
			'manage_options',
			'ha-image-optimizer-auditor',
			[ __CLASS__, 'render_auditor_page' ]
		);
	}

	/**
	 * Enqueue styles and scripts in admin.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( string $hook ): void {
		// Only load on our pages or media library
		$relevant_hooks = [
			'settings_page_ha-image-optimizer',
			'media_page_ha-image-optimizer-bulk',
			'media_page_ha-image-optimizer-auditor',
			'upload.php',
			'post.php',
		];

		if ( ! in_array( $hook, $relevant_hooks, true ) ) {
			return;
		}

		$version = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? (string) time() : HA_IO_VERSION;

		wp_enqueue_style(
			'ha-io-admin-style',
			HA_IO_URL . 'assets/css/admin.css',
			[],
			$version
		);

		wp_enqueue_script(
			'ha-io-bulk-script',
			HA_IO_URL . 'assets/js/bulk-optimizer.js',
			[ 'jquery' ],
			$version,
			true
		);

		wp_localize_script( 'ha-io-bulk-script', 'ha_io_vars', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'ha_io_admin_nonce' ),
			'i18n'     => [
				'optimizing'   => esc_html__( 'Optimiere...', 'ha-image-optimizer' ),
				'restoring'    => esc_html__( 'Stelle wieder her...', 'ha-image-optimizer' ),
				'completed'    => esc_html__( 'Optimierung abgeschlossen!', 'ha-image-optimizer' ),
				'error'        => esc_html__( 'Ein Fehler ist aufgetreten.', 'ha-image-optimizer' ),
				'confirm_rest' => esc_html__( 'Möchtest du das Originalbild wirklich wiederherstellen?', 'ha-image-optimizer' ),
				'pause'        => esc_html__( 'Pausieren', 'ha-image-optimizer' ),
				'resume'       => esc_html__( 'Fortsetzen', 'ha-image-optimizer' ),
				'saved'        => esc_html__( 'gespart', 'ha-image-optimizer' ),
			],
		] );

		if ( 'media_page_ha-image-optimizer-auditor' === $hook ) {
			wp_enqueue_script(
				'ha-io-auditor-script',
				HA_IO_URL . 'assets/js/media-auditor.js',
				[ 'jquery' ],
				$version,
				true
			);

			wp_localize_script( 'ha-io-auditor-script', 'ha_io_audit_vars', [
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ha_io_admin_nonce' ),
				'i18n'     => [
					'scanning'      => esc_html__( 'Scanne Mediathek...', 'ha-image-optimizer' ),
					'completed'     => esc_html__( 'Scan abgeschlossen!', 'ha-image-optimizer' ),
					'error'         => esc_html__( 'Ein Fehler ist aufgetreten.', 'ha-image-optimizer' ),
					'confirm_trash' => esc_html__( 'Dieses Bild wirklich in den Papierkorb verschieben?', 'ha-image-optimizer' ),
					'confirm_bulk'  => esc_html__( 'Alle ausgewählten Bilder wirklich in den Papierkorb verschieben?', 'ha-image-optimizer' ),
					'trashed'       => esc_html__( 'In Papierkorb verschoben', 'ha-image-optimizer' ),
					'resume_scan'   => esc_html__( 'Scan fortsetzen / Ungescannte prüfen', 'ha-image-optimizer' ),
					'full_scan'     => esc_html__( 'Vollständigen Scan starten', 'ha-image-optimizer' ),
					'start_scan'    => esc_html__( 'Scan jetzt starten', 'ha-image-optimizer' ),
					'all_scanned'   => esc_html__( 'Alle Medien sind bereits geprüft! Keine ungescannten Dateien vorhanden.', 'ha-image-optimizer' ),
				],
			] );
		}

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'ha-io-bulk-script', 'ha-image-optimizer', HA_IO_PATH . 'languages' );
		}
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) );
		}

		$settings     = Settings::get_all();
		$caps         = Environment::get_capabilities();
		$stats        = ProgressTracker::get_stats();
		$custom_stats = CustomFolderProcessor::get_stats();

		$template_path = HA_IO_PATH . 'templates/admin-settings.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * Render bulk optimizer page.
	 *
	 * @return void
	 */
	public static function render_bulk_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) );
		}

		$stats        = ProgressTracker::get_stats();
		$custom_stats = CustomFolderProcessor::get_stats();

		$template_path = HA_IO_PATH . 'templates/bulk-optimizer-page.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * Render media auditor page.
	 *
	 * @return void
	 */
	public static function render_auditor_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) );
		}

		$audit_data    = MediaAuditor::get_cached_results();
		$template_path = HA_IO_PATH . 'templates/media-auditor-page.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * Handle settings POST save.
	 *
	 * @return void
	 */
	public static function handle_save_settings(): void {
		check_admin_referer( 'ha_io_save_settings_action', 'ha_io_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ), 403 );
		}

		$raw = isset( $_POST['ha_io_settings'] ) ? (array) $_POST['ha_io_settings'] : [];
		Settings::update( $raw );

		// Update uninstall data protection setting as per HA standards
		$delete_data = ! empty( $_POST['ha_io_delete_data_on_uninstall'] );
		update_option( 'ha_io_delete_data_on_uninstall', $delete_data );

		wp_safe_redirect( add_query_arg( [ 'page' => 'ha-image-optimizer', 'updated' => '1' ], admin_url( 'options-general.php' ) ) );
		exit;
	}
}
