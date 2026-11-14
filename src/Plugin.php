<?php
/**
 * Main Plugin Orchestrator Singleton.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer;

use ha\ImageOptimizer\Common\Settings;
use ha\ImageOptimizer\Delivery\DeliveryManager;
use ha\ImageOptimizer\Delivery\HtaccessManager;
use ha\ImageOptimizer\Media\AttachmentProcessor;
use ha\ImageOptimizer\Media\BackupManager;
use ha\ImageOptimizer\Admin\AdminPage;
use ha\ImageOptimizer\Admin\MediaColumn;
use ha\ImageOptimizer\Admin\AjaxHandler;
use ha\ImageOptimizer\Cli\OptimizerCliCommand;
use ha\ImageOptimizer\Auditor\MediaAuditor;
use ha\ImageOptimizer\Auditor\BackgroundAuditor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'init', [ $this, 'init' ] );

		// Hook media generation pipeline
		add_filter( 'wp_generate_attachment_metadata', [ AttachmentProcessor::class, 'filter_generate_metadata' ], 10, 2 );

		// Synchronize audit cache and reverse index when content/attachments change
		add_action( 'delete_attachment', [ MediaAuditor::class, 'remove_from_cached_results' ] );
		add_action( 'delete_attachment', [ MediaAuditor::class, 'clear_reverse_index' ] );
		add_action( 'save_post', [ MediaAuditor::class, 'clear_reverse_index' ] );
		add_action( 'deleted_post', [ MediaAuditor::class, 'clear_reverse_index' ] );
		add_action( 'trashed_post', [ MediaAuditor::class, 'clear_reverse_index' ] );
		add_action( 'untrashed_post', [ MediaAuditor::class, 'clear_reverse_index' ] );

		// Register Background Auditor subsystem (async worker & WP-Cron)
		BackgroundAuditor::register();

		// Register WP-CLI commands
		OptimizerCliCommand::register();
	}

	/**
	 * Load plugin translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'ha-image-optimizer',
			false,
			dirname( HA_IO_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize plugin components on init.
	 *
	 * @return void
	 */
	public function init(): void {
		// Initialize delivery subsystem (Picture tags or .htaccess)
		DeliveryManager::init();

		// Initialize Admin components
		if ( is_admin() ) {
			AdminPage::register();
			MediaColumn::register();
			AjaxHandler::register();
		}
	}

	/**
	 * Plugin Activation lifecycle callback.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Secure backup directory
		BackupManager::get_backup_dir();

		// Initialize default settings if not yet present
		if ( false === get_option( Settings::OPTION_NAME ) ) {
			update_option( Settings::OPTION_NAME, Settings::get_defaults() );
		}
	}

	/**
	 * Plugin Deactivation lifecycle callback.
	 * Must remove .htaccess rules immediately to prevent 404/500 errors.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		HtaccessManager::remove_rules();
		BackgroundAuditor::clear_cron();
	}
}
