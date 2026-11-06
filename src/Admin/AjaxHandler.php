<?php
/**
 * AJAX Handler with strict Nonce & Capability Verification.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Admin;

use ha\ImageOptimizer\Media\AttachmentProcessor;
use ha\ImageOptimizer\Media\BackupManager;
use ha\ImageOptimizer\Bulk\BulkProcessor;
use ha\ImageOptimizer\Bulk\ProgressTracker;
use ha\ImageOptimizer\Common\Settings;
use ha\ImageOptimizer\Media\CustomFolderProcessor;
use ha\ImageOptimizer\Auditor\MediaAuditor;
use ha\ImageOptimizer\Auditor\BackgroundAuditor;
use ha\ImageOptimizer\Engine\SizeGuard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AjaxHandler
 */
class AjaxHandler {

	/**
	 * Register secure AJAX actions.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_ajax_ha_io_optimize_single', [ __CLASS__, 'handle_optimize_single' ] );
		add_action( 'wp_ajax_ha_io_restore_single', [ __CLASS__, 'handle_restore_single' ] );
		add_action( 'wp_ajax_ha_io_bulk_batch', [ __CLASS__, 'handle_bulk_batch' ] );
		add_action( 'wp_ajax_ha_io_get_bulk_queue', [ __CLASS__, 'handle_get_bulk_queue' ] );
		add_action( 'wp_ajax_ha_io_get_bulk_stats', [ __CLASS__, 'handle_get_bulk_stats' ] );
		add_action( 'wp_ajax_ha_io_get_custom_queue', [ __CLASS__, 'handle_get_custom_queue' ] );
		add_action( 'wp_ajax_ha_io_bulk_custom_batch', [ __CLASS__, 'handle_bulk_custom_batch' ] );
		add_action( 'wp_ajax_ha_io_restore_custom', [ __CLASS__, 'handle_restore_custom' ] );

		// Media Auditor Actions
		add_action( 'wp_ajax_ha_io_audit_get_queue', [ __CLASS__, 'handle_audit_get_queue' ] );
		add_action( 'wp_ajax_ha_io_audit_batch', [ __CLASS__, 'handle_audit_batch' ] );
		add_action( 'wp_ajax_ha_io_audit_trash_single', [ __CLASS__, 'handle_audit_trash_single' ] );
		add_action( 'wp_ajax_ha_io_audit_restore_single', [ __CLASS__, 'handle_audit_restore_single' ] );
		add_action( 'wp_ajax_ha_io_audit_clear', [ __CLASS__, 'handle_audit_clear' ] );

		// Media Auditor Background Actions
		add_action( 'wp_ajax_ha_io_audit_background_start', [ __CLASS__, 'handle_audit_background_start' ] );
		add_action( 'wp_ajax_ha_io_audit_background_pause', [ __CLASS__, 'handle_audit_background_pause' ] );
		add_action( 'wp_ajax_ha_io_audit_background_resume', [ __CLASS__, 'handle_audit_background_resume' ] );
		add_action( 'wp_ajax_ha_io_audit_background_cancel', [ __CLASS__, 'handle_audit_background_cancel' ] );
		add_action( 'wp_ajax_ha_io_audit_background_status', [ __CLASS__, 'handle_audit_background_status' ] );
	}

	/**
	 * Single image optimization AJAX.
	 *
	 * @return void
	 */
	public static function handle_optimize_single(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine ausreichenden Berechtigungen.', 'ha-image-optimizer' ) ], 403 );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( $attachment_id <= 0 ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Ungültige Bild-ID.', 'ha-image-optimizer' ) ], 400 );
		}

		$level = isset( $_POST['level'] ) ? sanitize_key( $_POST['level'] ) : null;
		if ( '' === $level ) {
			$level = null;
		}

		try {
			AttachmentProcessor::process( $attachment_id, null, true, $level );
			$data = get_post_meta( $attachment_id, '_ha_io_data', true );

			wp_send_json_success( [
				'message' => esc_html__( 'Bild erfolgreich optimiert.', 'ha-image-optimizer' ),
				'data'    => $data,
			] );
		} catch ( \Throwable $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
		}
	}

	/**
	 * Single image restore AJAX.
	 *
	 * @return void
	 */
	public static function handle_restore_single(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine ausreichenden Berechtigungen.', 'ha-image-optimizer' ) ], 403 );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( $attachment_id <= 0 ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Ungültige Bild-ID.', 'ha-image-optimizer' ) ], 400 );
		}

		if ( BackupManager::restore( $attachment_id ) ) {
			wp_send_json_success( [
				'message' => esc_html__( 'Originalbild erfolgreich wiederhergestellt.', 'ha-image-optimizer' ),
			] );
		} else {
			wp_send_json_error( [
				'message' => esc_html__( 'Wiederherstellung fehlgeschlagen (kein Backup vorhanden).', 'ha-image-optimizer' ),
			], 500 );
		}
	}

	/**
	 * Retrieve queue of IDs for the bulk optimizer.
	 *
	 * @return void
	 */
	public static function handle_get_bulk_queue(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		$all   = ! empty( $_POST['all'] );
		$queue = ProgressTracker::get_queue( 999999, $all );

		wp_send_json_success( [
			'queue' => $queue,
			'total' => count( $queue ),
		] );
	}

	/**
	 * Retrieve fresh statistics for bulk optimizer overview cards.
	 *
	 * @return void
	 */
	public static function handle_get_bulk_stats(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		$stats        = ProgressTracker::get_stats();
		$custom_stats = CustomFolderProcessor::get_stats();

		wp_send_json_success( [
			'stats'        => [
				'total_images'     => $stats['total_images'],
				'optimized_images' => $stats['optimized_images'],
				'unoptimized'      => $stats['unoptimized'],
				'saved_bytes'      => $stats['saved_bytes'],
				'saved_human'      => SizeGuard::format_size( $stats['saved_bytes'], 1 ),
				'saved_percent'    => $stats['saved_percent'],
			],
			'custom_stats' => [
				'total_images'     => $custom_stats['total_images'],
				'optimized_images' => $custom_stats['optimized_images'],
				'unoptimized'      => $custom_stats['unoptimized'],
				'saved_bytes'      => $custom_stats['saved_bytes'],
				'saved_human'      => SizeGuard::format_size( $custom_stats['saved_bytes'], 1 ),
				'saved_percent'    => $custom_stats['saved_percent'],
			],
		] );
	}

	/**
	 * Process a single batch chunk from the bulk runner.
	 *
	 * @return void
	 */
	public static function handle_bulk_batch(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		@set_time_limit( 120 );
		wp_raise_memory_limit( 'image' );

		$ids   = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : [];
		$force = ! empty( $_POST['force'] );

		if ( empty( $ids ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine IDs übergeben.', 'ha-image-optimizer' ) ], 400 );
		}

		$result = BulkProcessor::process_batch( $ids, $force );
		wp_send_json_success( $result );
	}

	/**
	 * Retrieve queue of custom folder relative paths for bulk runner.
	 *
	 * @return void
	 */
	public static function handle_get_custom_queue(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		$all   = ! empty( $_POST['all'] );
		$queue = CustomFolderProcessor::get_queue( $all );

		wp_send_json_success( [
			'queue' => $queue,
			'total' => count( $queue ),
		] );
	}

	/**
	 * Process a batch of custom folder images.
	 *
	 * @return void
	 */
	public static function handle_bulk_custom_batch(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		@set_time_limit( 120 );
		wp_raise_memory_limit( 'image' );

		$files = isset( $_POST['files'] ) ? (array) $_POST['files'] : [];
		$force = ! empty( $_POST['force'] );

		if ( empty( $files ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Dateien übergeben.', 'ha-image-optimizer' ) ], 400 );
		}

		$result = BulkProcessor::process_custom_batch( $files, $force );
		wp_send_json_success( $result );
	}

	/**
	 * Single custom file restore AJAX.
	 *
	 * @return void
	 */
	public static function handle_restore_custom(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		$file = isset( $_POST['file'] ) ? sanitize_text_field( (string) $_POST['file'] ) : '';
		if ( empty( $file ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Datei angegeben.', 'ha-image-optimizer' ) ], 400 );
		}

		if ( CustomFolderProcessor::restore_file( $file ) ) {
			wp_send_json_success( [
				'message' => esc_html__( 'Datei erfolgreich aus Backup wiederhergestellt.', 'ha-image-optimizer' ),
			] );
		} else {
			wp_send_json_error( [
				'message' => esc_html__( 'Wiederherstellung fehlgeschlagen (kein Backup vorhanden).', 'ha-image-optimizer' ),
			], 500 );
		}
	}

	/**
	 * Retrieve queue of IDs to audit.
	 *
	 * @return void
	 */
	public static function handle_audit_get_queue(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		$skip_scanned = ! empty( $_POST['skip_scanned'] );
		$reset_cache  = ! empty( $_POST['reset_cache'] );

		if ( $reset_cache || ( ! $skip_scanned && isset( $_POST['reset_cache'] ) && '1' === (string) $_POST['reset_cache'] ) ) {
			MediaAuditor::clear_results();
		}

		$queue           = MediaAuditor::get_audit_queue( $skip_scanned );
		$all_ids         = MediaAuditor::get_audit_queue( false );
		$total_all       = count( $all_ids );
		$cached          = MediaAuditor::get_cached_results();
		$already_scanned = ! empty( $cached['items'] ) ? count( $cached['items'] ) : 0;

		wp_send_json_success( [
			'queue'           => $queue,
			'total'           => count( $queue ),
			'total_all'       => $total_all,
			'already_scanned' => $already_scanned,
			'skip_scanned'    => $skip_scanned,
		] );
	}

	/**
	 * Audit a single batch of attachment IDs.
	 *
	 * @return void
	 */
	public static function handle_audit_batch(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		@set_time_limit( 120 );
		wp_raise_memory_limit( 'image' );

		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : [];
		if ( empty( $ids ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine IDs übergeben.', 'ha-image-optimizer' ) ], 400 );
		}

		$prefetched = MediaAuditor::prefetch_batch_data( $ids );
		$early_exit = (bool) Settings::get( 'auditor_fast_mode', true );

		$audited = [];
		foreach ( $ids as $id ) {
			if ( $id > 0 ) {
				$audited[] = MediaAuditor::audit_attachment( $id, $prefetched, $early_exit );
			}
		}

		$summary = MediaAuditor::update_cached_results( $audited );

		wp_send_json_success( [
			'items'   => $audited,
			'summary' => $summary,
		] );
	}

	/**
	 * Move an attachment to trash or permanently delete it.
	 *
	 * @return void
	 */
	public static function handle_audit_trash_single(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		$id    = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$force = ! empty( $_POST['force_delete'] );

		if ( $id <= 0 ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Ungültige Bild-ID.', 'ha-image-optimizer' ) ], 400 );
		}

		if ( MediaAuditor::trash_attachment( $id, $force ) ) {
			wp_send_json_success( [
				'message' => $force
					? esc_html__( 'Bild dauerhaft gelöscht.', 'ha-image-optimizer' )
					: esc_html__( 'Bild in den Papierkorb verschoben.', 'ha-image-optimizer' ),
				'id'      => $id,
			] );
		} else {
			wp_send_json_error( [ 'message' => esc_html__( 'Konnte Bild nicht löschen.', 'ha-image-optimizer' ) ], 500 );
		}
	}

	/**
	 * Restore an attachment from trash.
	 *
	 * @return void
	 */
	public static function handle_audit_restore_single(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		$id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( $id <= 0 ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Ungültige Bild-ID.', 'ha-image-optimizer' ) ], 400 );
		}

		if ( MediaAuditor::restore_attachment( $id ) ) {
			wp_send_json_success( [
				'message' => esc_html__( 'Bild erfolgreich aus dem Papierkorb wiederhergestellt.', 'ha-image-optimizer' ),
				'id'      => $id,
			] );
		} else {
			wp_send_json_error( [ 'message' => esc_html__( 'Wiederherstellung fehlgeschlagen.', 'ha-image-optimizer' ) ], 500 );
		}
	}

	/**
	 * Clear cached audit results.
	 *
	 * @return void
	 */
	public static function handle_audit_clear(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		MediaAuditor::clear_results();
		wp_send_json_success( [ 'message' => esc_html__( 'Audit-Cache erfolgreich geleert.', 'ha-image-optimizer' ) ] );
	}

	/**
	 * Start background audit.
	 *
	 * @return void
	 */
	public static function handle_audit_background_start(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		$skip_scanned = ! empty( $_POST['skip_scanned'] );
		$reset_cache  = ! empty( $_POST['reset_cache'] );

		$state = BackgroundAuditor::start( $skip_scanned, $reset_cache );
		wp_send_json_success( [ 'state' => $state ] );
	}

	/**
	 * Pause background audit.
	 *
	 * @return void
	 */
	public static function handle_audit_background_pause(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		$state = BackgroundAuditor::pause();
		wp_send_json_success( [ 'state' => $state ] );
	}

	/**
	 * Resume background audit.
	 *
	 * @return void
	 */
	public static function handle_audit_background_resume(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		$state = BackgroundAuditor::resume();
		wp_send_json_success( [ 'state' => $state ] );
	}

	/**
	 * Cancel background audit.
	 *
	 * @return void
	 */
	public static function handle_audit_background_cancel(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		$state = BackgroundAuditor::cancel();
		wp_send_json_success( [ 'state' => $state ] );
	}

	/**
	 * Get current background audit status.
	 *
	 * @return void
	 */
	public static function handle_audit_background_status(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		$state   = BackgroundAuditor::get_state();
		$cached  = MediaAuditor::get_cached_results();
		$summary = [
			'counts'            => $cached['counts'] ?? [],
			'reclaimable_bytes' => $cached['reclaimable_bytes'] ?? [],
			'total_items'       => count( $cached['items'] ?? [] ),
		];

		wp_send_json_success( [
			'state'   => $state,
			'summary' => $summary,
		] );
	}
}

