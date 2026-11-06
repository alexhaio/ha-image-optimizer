<?php
/**
 * Background Auditor - Manages asynchronous, throttled, and night-window background audit execution.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Auditor;

use ha\ImageOptimizer\Common\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BackgroundAuditor
 */
class BackgroundAuditor {

	/**
	 * Option key storing the background audit state.
	 */
	public const OPTION_STATE = 'ha_io_audit_background_state';

	/**
	 * AJAX action for the non-blocking background worker.
	 */
	public const ACTION_WORKER = 'ha_io_background_audit_worker';

	/**
	 * Cron action hook for scheduled background processing.
	 */
	public const CRON_HOOK = 'ha_io_background_audit_cron_step';

	/**
	 * State constants.
	 */
	public const STATUS_IDLE          = 'idle';
	public const STATUS_RUNNING       = 'running';
	public const STATUS_PAUSED        = 'paused';
	public const STATUS_WAITING_NIGHT = 'waiting_night';
	public const STATUS_COOLDOWN      = 'cooldown';
	public const STATUS_COMPLETED     = 'completed';
	public const STATUS_CANCELLED     = 'cancelled';

	/**
	 * Register hooks for background processing.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( self::CRON_HOOK, [ __CLASS__, 'cron_step' ] );
		add_action( 'wp_ajax_' . self::ACTION_WORKER, [ __CLASS__, 'handle_async_worker' ] );
		add_action( 'wp_ajax_nopriv_' . self::ACTION_WORKER, [ __CLASS__, 'handle_async_worker' ] );

		// Custom cron schedule interval if not already present
		add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_intervals' ] );
	}

	/**
	 * Add custom 5-minute cron interval.
	 *
	 * @param array<string, array<string, mixed>> $schedules Cron schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public static function add_cron_intervals( array $schedules ): array {
		if ( ! isset( $schedules['ha_io_five_minutes'] ) ) {
			$schedules['ha_io_five_minutes'] = [
				'interval' => 300,
				'display'  => __( 'Alle 5 Minuten (HA Image Optimizer)', 'ha-image-optimizer' ),
			];
		}
		return $schedules;
	}

	/**
	 * Get the current background audit state.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_state(): array {
		$defaults = [
			'status'          => self::STATUS_IDLE,
			'mode'            => Settings::get( 'auditor_pacing_mode', 'standard' ),
			'queue'           => [],
			'total'           => 0,
			'processed'       => 0,
			'already_scanned' => 0,
			'skip_scanned'    => true,
			'started_at'      => 0,
			'updated_at'      => 0,
			'completed_at'    => 0,
			'last_error'      => '',
			'current_status'  => __( 'Kein Hintergrund-Scan aktiv.', 'ha-image-optimizer' ),
		];

		$saved = get_option( self::OPTION_STATE, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Update background audit state.
	 *
	 * @param array<string, mixed> $updates State fields to update.
	 * @return array<string, mixed> Full updated state.
	 */
	public static function update_state( array $updates ): array {
		$current = self::get_state();
		$updated = array_merge( $current, $updates );
		$updated['updated_at'] = time();

		update_option( self::OPTION_STATE, $updated, false );
		return $updated;
	}

	/**
	 * Check whether the current local time falls into the defined night-mode window.
	 *
	 * Handles windows spanning midnight (e.g. 22:00 to 06:00) cleanly.
	 *
	 * @param string|null $custom_time Optional time string in 'H:i' format for testing.
	 * @return bool True if within the night window.
	 */
	public static function is_night_time( ?string $custom_time = null ): bool {
		$start = (string) Settings::get( 'auditor_night_start', '02:00' );
		$end   = (string) Settings::get( 'auditor_night_end', '06:00' );

		$now = $custom_time ?? ( function_exists( 'wp_date' ) ? wp_date( 'H:i' ) : date( 'H:i' ) );

		if ( $start === $end ) {
			return true;
		}

		if ( $start < $end ) {
			return ( $now >= $start && $now <= $end );
		}

		// Window crosses midnight (e.g. 22:00 to 06:00)
		return ( $now >= $start || $now <= $end );
	}

	/**
	 * Determine whether background processing is allowed to run right now.
	 *
	 * Checks background toggle, night-time window, and server load thresholds.
	 *
	 * @param string|null $reason Out-parameter with explanation if not allowed.
	 * @return bool
	 */
	public static function can_run_now( ?string &$reason = null ): bool {
		$is_bg_enabled = (bool) Settings::get( 'auditor_background_enabled', false );
		if ( ! $is_bg_enabled ) {
			$reason = __( 'Hintergrund-Tasks sind in den Einstellungen deaktiviert.', 'ha-image-optimizer' );
			return false;
		}

		$is_night_mode = (bool) Settings::get( 'auditor_night_mode_enabled', true );
		if ( $is_night_mode && ! self::is_night_time() ) {
			$start   = (string) Settings::get( 'auditor_night_start', '02:00' );
			$end     = (string) Settings::get( 'auditor_night_end', '06:00' );
			$current = function_exists( 'wp_date' ) ? wp_date( 'H:i' ) : date( 'H:i' );

			$reason = sprintf(
				/* translators: 1: start time, 2: end time, 3: current server time */
				__( 'Wartet auf Nacht-Zeitfenster (%1$s – %2$s Uhr). Aktuelle Serverzeit: %3$s Uhr.', 'ha-image-optimizer' ),
				$start,
				$end,
				$current
			);
			return false;
		}

		$max_load    = (float) Settings::get( 'auditor_max_server_load', 2.5 );
		$server_load = MediaAuditor::get_server_load();
		if ( null !== $server_load && $server_load > $max_load ) {
			$reason = sprintf(
				/* translators: 1: current load, 2: max allowed load */
				__( 'Serverlast zu hoch (%1$s > %2$s). Abkühlpause (Cooldown) aktiv.', 'ha-image-optimizer' ),
				number_format( $server_load, 1 ),
				number_format( $max_load, 1 )
			);
			return false;
		}

		return true;
	}

	/**
	 * Start or restart a background audit scan.
	 *
	 * @param bool $skip_scanned Whether to skip already cached media. Default true.
	 * @param bool $reset_cache  Whether to clear cached results first. Default false.
	 * @return array<string, mixed> New state.
	 */
	public static function start( bool $skip_scanned = true, bool $reset_cache = false ): array {
		if ( $reset_cache ) {
			MediaAuditor::clear_results();
		}

		$queue           = MediaAuditor::get_audit_queue( $skip_scanned );
		$total           = count( $queue );
		$cached          = MediaAuditor::get_cached_results();
		$already_scanned = ! empty( $cached['items'] ) ? count( $cached['items'] ) : 0;

		$reason = null;
		$can_run = self::can_run_now( $reason );

		$status = $can_run
			? self::STATUS_RUNNING
			: ( str_contains( (string) $reason, 'Nacht' ) ? self::STATUS_WAITING_NIGHT : self::STATUS_COOLDOWN );

		$status_msg = $can_run
			? sprintf( __( 'Hintergrund-Scan gestartet (%d Medien in der Queue)...', 'ha-image-optimizer' ), $total )
			: (string) $reason;

		$state = self::update_state( [
			'status'          => ( 0 === $total ) ? self::STATUS_COMPLETED : $status,
			'mode'            => Settings::get( 'auditor_pacing_mode', 'standard' ),
			'queue'           => $queue,
			'total'           => $total,
			'processed'       => 0,
			'already_scanned' => $already_scanned,
			'skip_scanned'    => $skip_scanned,
			'started_at'      => time(),
			'completed_at'    => ( 0 === $total ) ? time() : 0,
			'last_error'      => '',
			'current_status'  => ( 0 === $total ) ? __( 'Alle Medien bereits geprüft. Keine ungescannten Dateien vorhanden.', 'ha-image-optimizer' ) : $status_msg,
		] );

		if ( $total > 0 ) {
			self::schedule_cron();

			if ( $can_run ) {
				self::dispatch_async();
			}
		}

		return $state;
	}

	/**
	 * Pause the running background audit.
	 *
	 * @return array<string, mixed> Updated state.
	 */
	public static function pause(): array {
		$state = self::get_state();
		if ( in_array( $state['status'], [ self::STATUS_RUNNING, self::STATUS_WAITING_NIGHT, self::STATUS_COOLDOWN ], true ) ) {
			$state = self::update_state( [
				'status'         => self::STATUS_PAUSED,
				'current_status' => __( 'Hintergrund-Scan wurde vom Benutzer pausiert.', 'ha-image-optimizer' ),
			] );
		}
		return $state;
	}

	/**
	 * Resume a paused background audit.
	 *
	 * @return array<string, mixed> Updated state.
	 */
	public static function resume(): array {
		$state = self::get_state();
		if ( self::STATUS_PAUSED === $state['status'] ) {
			$reason  = null;
			$can_run = self::can_run_now( $reason );

			$new_status = $can_run
				? self::STATUS_RUNNING
				: ( str_contains( (string) $reason, 'Nacht' ) ? self::STATUS_WAITING_NIGHT : self::STATUS_COOLDOWN );

			$state = self::update_state( [
				'status'         => $new_status,
				'current_status' => $can_run
					? __( 'Hintergrund-Scan wird fortgesetzt...', 'ha-image-optimizer' )
					: (string) $reason,
			] );

			self::schedule_cron();

			if ( $can_run ) {
				self::dispatch_async();
			}
		}
		return $state;
	}

	/**
	 * Cancel and abort the current background audit.
	 *
	 * @return array<string, mixed> Updated state.
	 */
	public static function cancel(): array {
		self::clear_cron();

		return self::update_state( [
			'status'         => self::STATUS_CANCELLED,
			'queue'          => [],
			'current_status' => __( 'Hintergrund-Scan wurde abgebrochen.', 'ha-image-optimizer' ),
		] );
	}

	/**
	 * Process a single batch chunk in the background.
	 *
	 * Executes one throttled step, updates state atomar, and schedules/dispatches the next step.
	 *
	 * @return array<string, mixed> Updated state.
	 */
	public static function process_batch(): array {
		$state = self::get_state();

		// Only proceed if currently active
		if ( ! in_array( $state['status'], [ self::STATUS_RUNNING, self::STATUS_WAITING_NIGHT, self::STATUS_COOLDOWN ], true ) ) {
			return $state;
		}

		$reason = null;
		if ( ! self::can_run_now( $reason ) ) {
			$new_status = str_contains( (string) $reason, 'Nacht' ) ? self::STATUS_WAITING_NIGHT : self::STATUS_COOLDOWN;
			return self::update_state( [
				'status'         => $new_status,
				'current_status' => (string) $reason,
			] );
		}

		// Active running state
		$queue = (array) ( $state['queue'] ?? [] );
		if ( empty( $queue ) ) {
			self::clear_cron();
			return self::update_state( [
				'status'         => self::STATUS_COMPLETED,
				'completed_at'   => time(),
				'current_status' => __( 'Hintergrund-Scan erfolgreich abgeschlossen! Alle Medien wurden analysiert.', 'ha-image-optimizer' ),
			] );
		}

		// Pacing configuration
		$pacing     = MediaAuditor::get_pacing_config();
		$batch_size = (int) $pacing['batch_size'];
		$chunk      = array_splice( $queue, 0, $batch_size );

		// Execute batch audit
		@set_time_limit( 120 );
		wp_raise_memory_limit( 'image' );

		$prefetched = MediaAuditor::prefetch_batch_data( $chunk );
		$fast_mode  = (bool) Settings::get( 'auditor_fast_mode', true );

		$audited = [];
		foreach ( $chunk as $id ) {
			$id = absint( $id );
			if ( $id > 0 ) {
				$audited[] = MediaAuditor::audit_attachment( $id, $prefetched, $fast_mode );
			}
		}

		if ( ! empty( $audited ) ) {
			MediaAuditor::update_cached_results( $audited );
		}

		$processed     = (int) ( $state['processed'] ?? 0 ) + count( $chunk );
		$total         = (int) ( $state['total'] ?? 0 );
		$is_finished   = empty( $queue );

		$status_text = $is_finished
			? __( 'Hintergrund-Scan erfolgreich abgeschlossen!', 'ha-image-optimizer' )
			: sprintf(
				/* translators: 1: processed count, 2: total count, 3: chunk IDs */
				__( 'Geprüft: %1$d von %2$d Medien (IDs: %3$s)...', 'ha-image-optimizer' ),
				$processed,
				$total,
				implode( ', ', $chunk )
			);

		$new_state = self::update_state( [
			'status'         => $is_finished ? self::STATUS_COMPLETED : self::STATUS_RUNNING,
			'queue'          => $queue,
			'processed'      => $processed,
			'completed_at'   => $is_finished ? time() : 0,
			'current_status' => $status_text,
		] );

		if ( $is_finished ) {
			self::clear_cron();
		} else {
			// Enforce micro-sleep pacing
			if ( $pacing['sleep_ms'] > 0 ) {
				usleep( $pacing['sleep_ms'] * 1000 );
			}

			// Chain to next async batch
			self::dispatch_async();
		}

		return $new_state;
	}

	/**
	 * Non-blocking asynchronous HTTP dispatcher (Action Scheduler / WP Background Process pattern).
	 *
	 * Dispatches a self-spawning HTTP request to admin-ajax.php with an ultra-short timeout (0.01s).
	 *
	 * @return bool True if dispatch request was initiated.
	 */
	public static function dispatch_async(): bool {
		if ( ! function_exists( 'wp_remote_post' ) ) {
			return false;
		}

		$ajax_url = admin_url( 'admin-ajax.php' );
		$token    = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( 'ha_io_bg_audit_worker_nonce' ) : '';

		$args = [
			'timeout'   => 0.01,
			'blocking'  => false,
			'sslverify' => function_exists( 'apply_filters' ) ? apply_filters( 'https_local_ssl_verify', false ) : false,
			'body'      => [
				'action' => self::ACTION_WORKER,
				'token'  => $token,
			],
		];

		$response = wp_remote_post( $ajax_url, $args );
		return ! is_wp_error( $response );
	}

	/**
	 * AJAX handler for the asynchronous background worker.
	 *
	 * @return void
	 */
	public static function handle_async_worker(): void {
		$token = isset( $_POST['token'] ) ? sanitize_text_field( (string) $_POST['token'] ) : '';
		if ( ! wp_verify_nonce( $token, 'ha_io_bg_audit_worker_nonce' ) ) {
			status_header( 403 );
			exit;
		}

		// Close connection early to client if fastcgi_finish_request is available
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			echo 'OK';
			fastcgi_finish_request();
		}

		self::process_batch();
		exit;
	}

	/**
	 * WP-Cron fallback handler.
	 *
	 * Picks up execution periodically if the async HTTP loop is interrupted.
	 *
	 * @return void
	 */
	public static function cron_step(): void {
		$state = self::get_state();
		if ( in_array( $state['status'], [ self::STATUS_RUNNING, self::STATUS_WAITING_NIGHT, self::STATUS_COOLDOWN ], true ) ) {
			self::process_batch();
		}
	}

	/**
	 * Schedule the WP-Cron fallback event.
	 *
	 * @return void
	 */
	public static function schedule_cron(): void {
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			if ( function_exists( 'wp_schedule_event' ) ) {
				wp_schedule_event( time() + 60, 'ha_io_five_minutes', self::CRON_HOOK );
			}
		}
	}

	/**
	 * Remove the WP-Cron fallback event.
	 *
	 * @return void
	 */
	public static function clear_cron(): void {
		if ( function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_unschedule_event' ) ) {
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, self::CRON_HOOK );
			}
		}
	}
}
