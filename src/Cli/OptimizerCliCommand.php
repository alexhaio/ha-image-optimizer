<?php
/**
 * WP-CLI Command for HA Image Optimizer.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Cli;

use ha\ImageOptimizer\Common\Environment;
use ha\ImageOptimizer\Bulk\ProgressTracker;
use ha\ImageOptimizer\Bulk\BulkProcessor;
use ha\ImageOptimizer\Media\BackupManager;
use ha\ImageOptimizer\Media\AttachmentProcessor;
use ha\ImageOptimizer\Media\CustomFolderProcessor;
use ha\ImageOptimizer\Auditor\MediaAuditor;
use ha\ImageOptimizer\Engine\SizeGuard;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OptimizerCliCommand
 */
class OptimizerCliCommand {

	/**
	 * Register the command with WP-CLI.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'ha-optimizer', __CLASS__ );
		}
	}

	/**
	 * Display current optimization status and server diagnostics.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ha-optimizer status
	 *
	 * @param array<string> $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function status( array $args, array $assoc_args ): void {
		$caps  = Environment::get_capabilities();
		$stats        = ProgressTracker::get_stats();
		$custom_stats = CustomFolderProcessor::get_stats();

		WP_CLI::line( WP_CLI::colorize( '%G=== HA Image Optimizer: System-Status ===%n' ) );
		WP_CLI::line( sprintf( 'PHP-Version: %s (%s)', $caps['php']['version'], $caps['php']['compatible'] ? 'OK' : 'Inkompatibel' ) );
		WP_CLI::line( sprintf( 'Aktiver Treiber: %s', strtoupper( $caps['best_driver'] ) ) );
		WP_CLI::line( sprintf( 'WebP-Unterstützung: %s', $caps['supports_webp'] ? 'Ja' : 'Nein' ) );
		WP_CLI::line( sprintf( 'AVIF-Unterstützung: %s', $caps['supports_avif'] ? 'Ja' : 'Nein' ) );
		WP_CLI::line( sprintf( 'Server: %s (Rewrite-Unterstützung: %s)', $caps['server']['software'], $caps['server']['supports_htaccess'] ? 'Ja' : 'Nein' ) );
		WP_CLI::line( sprintf( 'Speicherlimit: %s', $caps['memory']['limit_string'] ) );

		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%G=== Mediathek-Statistik ===%n' ) );
		WP_CLI::line( sprintf( 'Bilder Gesamt: %d', $stats['total_images'] ) );
		WP_CLI::line( sprintf( 'Bereits Optimiert: %d', $stats['optimized_images'] ) );
		WP_CLI::line( sprintf( 'Noch Unoptimiert: %d', $stats['unoptimized'] ) );
		WP_CLI::line( sprintf( 'Gesparter Speicherplatz: %s (%s%%)', SizeGuard::format_size( $stats['saved_bytes'], 1 ), $stats['saved_percent'] ) );

		if ( ! empty( $custom_stats['total_images'] ) ) {
			WP_CLI::line( '' );
			WP_CLI::line( WP_CLI::colorize( '%G=== Theme- & Custom-Ordner-Statistik ===%n' ) );
			WP_CLI::line( sprintf( 'Dateien Gesamt: %d', $custom_stats['total_images'] ) );
			WP_CLI::line( sprintf( 'Bereits Optimiert: %d', $custom_stats['optimized_images'] ) );
			WP_CLI::line( sprintf( 'Noch Unoptimiert: %d', $custom_stats['unoptimized'] ) );
			WP_CLI::line( sprintf( 'Gesparter Speicherplatz: %s (%s%%)', SizeGuard::format_size( $custom_stats['saved_bytes'], 1 ), $custom_stats['saved_percent'] ) );
		}
	}

	/**
	 * Optimize images via CLI.
	 *
	 * ## OPTIONS
	 *
	 * [--id=<int>]
	 * : Optimize a specific single attachment ID.
	 *
	 * [--level=<string>]
	 * : Custom compression level ('ultra'/'hoch', 'smart'/'mittel', 'lossless'/'niedrig', 'default').
	 *
	 * [--all]
	 * : Process all images including already optimized ones.
	 *
	 * [--force]
	 * : Force re-optimization even if already processed.
	 *
	 * [--batch-size=<int>]
	 * : Number of images per batch (default: 25).
	 *
	 * ## EXAMPLES
	 *
	 *     wp ha-optimizer optimize
	 *     wp ha-optimizer optimize --id=123 --level=ultra
	 *     wp ha-optimizer optimize --all --force
	 *
	 * @param array<string> $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function optimize( array $args, array $assoc_args ): void {
		if ( ! empty( $assoc_args['id'] ) ) {
			$id    = absint( $assoc_args['id'] );
			$level = isset( $assoc_args['level'] ) ? (string) $assoc_args['level'] : null;
			$force = ! empty( $assoc_args['force'] ) || ! empty( $assoc_args['all'] );

			WP_CLI::log( sprintf( 'Optimiere Bild ID %d (Stufe: %s)...', $id, $level ?: 'Standard' ) );
			try {
				AttachmentProcessor::process( $id, null, $force, $level );
				$data  = get_post_meta( $id, '_ha_io_data', true );
				$saved = SizeGuard::format_size( (int) ( $data['total']['saved'] ?? 0 ), 1 );
				$pct   = number_format_i18n( (float) ( $data['total']['percent'] ?? 0.0 ), 1 );
				WP_CLI::success( sprintf( 'Bild ID %d erfolgreich optimiert! Ersparnis: %s (%s%%).', $id, $saved, $pct ) );
			} catch ( \Throwable $e ) {
				WP_CLI::error( sprintf( 'Fehler beim Optimieren von Bild ID %d: %s', $id, $e->getMessage() ) );
			}
			return;
		}

		$all        = ! empty( $assoc_args['all'] );
		$force      = ! empty( $assoc_args['force'] ) || $all;
		$batch_size = isset( $assoc_args['batch-size'] ) ? absint( $assoc_args['batch-size'] ) : 25;

		WP_CLI::log( 'Ermittle zu optimierende Bilder...' );

		$ids = ProgressTracker::get_queue( 999999, $all );
		$total = count( $ids );

		if ( 0 === $total ) {
			WP_CLI::success( 'Keine unoptimierten Bilder gefunden. Alles auf dem neuesten Stand!' );
			return;
		}

		WP_CLI::log( sprintf( 'Starte Optimierung von %d Bildern...', $total ) );
		$progress = \WP_CLI\Utils\make_progress_bar( 'Fortschritt', $total );

		$total_saved = 0;
		$chunks      = array_chunk( $ids, $batch_size );

		foreach ( $chunks as $chunk ) {
			$res = BulkProcessor::process_batch( $chunk, $force );
			$total_saved += $res['saved_bytes'];

			foreach ( $chunk as $id ) {
				$progress->tick();
			}
		}

		$progress->finish();
		WP_CLI::success( sprintf( 'Optimierung abgeschlossen! Insgesamt wurden %s Speicherplatz gespart.', SizeGuard::format_size( $total_saved, 1 ) ) );
	}

	/**
	 * Restore original images from backups.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Restore all backed-up images.
	 *
	 * [--id=<int>]
	 * : Restore a specific attachment ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ha-optimizer restore --id=123
	 *     wp ha-optimizer restore --all
	 *
	 * @param array<string> $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function restore( array $args, array $assoc_args ): void {
		if ( ! empty( $assoc_args['id'] ) ) {
			$id = absint( $assoc_args['id'] );
			if ( BackupManager::restore( $id ) ) {
				WP_CLI::success( sprintf( 'Bild ID %d erfolgreich wiederhergestellt.', $id ) );
			} else {
				WP_CLI::error( sprintf( 'Konnte Bild ID %d nicht wiederherstellen (kein Backup vorhanden oder Fehler).', $id ) );
			}
			return;
		}

		if ( ! empty( $assoc_args['all'] ) ) {
			global $wpdb;
			$all_ids = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ha_io_data'" );
			$count   = 0;

			foreach ( $all_ids as $id ) {
				if ( BackupManager::restore( (int) $id ) ) {
					$count++;
				}
			}

			WP_CLI::success( sprintf( '%d Originalbilder wurden erfolgreich wiederhergestellt.', $count ) );
			return;
		}

		WP_CLI::error( 'Bitte gib entweder --id=<ID> oder --all an.' );
	}

	/**
	 * Optimize images in theme and custom folders via CLI.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Process all custom folder images including already optimized ones.
	 *
	 * [--force]
	 * : Force re-optimization even if already processed.
	 *
	 * [--batch-size=<int>]
	 * : Number of files per batch (default: 25).
	 *
	 * ## EXAMPLES
	 *
	 *     wp ha-optimizer optimize-custom
	 *     wp ha-optimizer optimize-custom --all --force
	 *
	 * @subcommand optimize-custom
	 *
	 * @param array<string> $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function optimize_custom( array $args, array $assoc_args ): void {
		$all        = ! empty( $assoc_args['all'] );
		$force      = ! empty( $assoc_args['force'] ) || $all;
		$batch_size = isset( $assoc_args['batch-size'] ) ? absint( $assoc_args['batch-size'] ) : 25;

		WP_CLI::log( 'Ermittle zu optimierende Theme- und Custom-Dateien...' );

		$files = CustomFolderProcessor::get_queue( $all );
		$total = count( $files );

		if ( 0 === $total ) {
			WP_CLI::success( 'Keine unoptimierten Dateien in Custom-Ordnern gefunden. Alles auf dem neuesten Stand!' );
			return;
		}

		WP_CLI::log( sprintf( 'Starte Optimierung von %d Dateien...', $total ) );
		$progress = \WP_CLI\Utils\make_progress_bar( 'Fortschritt', $total );

		$total_saved = 0;
		$chunks      = array_chunk( $files, $batch_size );

		foreach ( $chunks as $chunk ) {
			$res = BulkProcessor::process_custom_batch( $chunk, $force );
			$total_saved += $res['saved_bytes'];

			foreach ( $chunk as $file ) {
				$progress->tick();
			}
		}

		$progress->finish();
		WP_CLI::success( sprintf( 'Custom-Optimierung abgeschlossen! Insgesamt wurden %s Speicherplatz gespart.', SizeGuard::format_size( $total_saved, 1 ) ) );
	}

	/**
	 * Restore original images in custom folders from backups.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Restore all backed-up custom files.
	 *
	 * [--file=<path>]
	 * : Relative file path from wp-content to restore.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ha-optimizer restore-custom --file="themes/my-theme/assets/hero.jpg"
	 *     wp ha-optimizer restore-custom --all
	 *
	 * @subcommand restore-custom
	 *
	 * @param array<string> $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function restore_custom( array $args, array $assoc_args ): void {
		if ( ! empty( $assoc_args['file'] ) ) {
			$file = sanitize_text_field( (string) $assoc_args['file'] );
			if ( CustomFolderProcessor::restore_file( $file ) ) {
				WP_CLI::success( sprintf( 'Datei %s erfolgreich aus Backup wiederhergestellt.', $file ) );
			} else {
				WP_CLI::error( sprintf( 'Konnte Datei %s nicht wiederherstellen (kein Backup vorhanden oder Fehler).', $file ) );
			}
			return;
		}

		if ( ! empty( $assoc_args['all'] ) ) {
			$stored = get_option( CustomFolderProcessor::OPTION_NAME, [] );
			$count  = 0;

			if ( is_array( $stored ) ) {
				foreach ( $stored as $item ) {
					if ( ! empty( $item['file'] ) && CustomFolderProcessor::restore_file( (string) $item['file'] ) ) {
						$count++;
					}
				}
			}

			WP_CLI::success( sprintf( '%d Custom-Originaldateien wurden erfolgreich wiederhergestellt.', $count ) );
			return;
		}

		WP_CLI::error( 'Bitte gib entweder --file=<rel_path> oder --all an.' );
	}

	/**
	 * Audit media library for orphan or unused files.
	 *
	 * ## OPTIONS
	 *
	 * [--skip-scanned]
	 * : Skip media attachments that already have cached audit results.
	 *
	 * [--reset]
	 * : Reset and clear the audit cache before scanning.
	 *
	 * [--filter=<all|unattached|attached|used>]
	 * : Filter displayed items (default: unattached).
	 *
	 * [--format=<table|json|csv>]
	 * : Render output in particular format (default: table).
	 *
	 * ## EXAMPLES
	 *
	 *     wp ha-optimizer audit
	 *     wp ha-optimizer audit --skip-scanned
	 *     wp ha-optimizer audit --reset
	 *     wp ha-optimizer audit --filter=all
	 *     wp ha-optimizer audit --format=json
	 *
	 * @subcommand audit
	 *
	 * @param array<string> $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function audit( array $args, array $assoc_args ): void {
		$skip_scanned = ! empty( $assoc_args['skip-scanned'] );
		$reset        = ! empty( $assoc_args['reset'] );

		if ( $reset ) {
			MediaAuditor::clear_results();
			WP_CLI::line( 'Audit-Cache wurde zurückgesetzt.' );
		}

		$queue = MediaAuditor::get_audit_queue( $skip_scanned );
		$total = count( $queue );

		if ( 0 === $total ) {
			if ( $skip_scanned ) {
				$cached       = MediaAuditor::get_cached_results();
				$cached_count = ! empty( $cached['items'] ) ? count( $cached['items'] ) : 0;
				WP_CLI::success( sprintf( 'Alle Medien (%d) wurden bereits gescannt. Keine ungescannten Dateien vorhanden. Verwende --reset für einen erneuten Komplettscan.', $cached_count ) );
			} else {
				WP_CLI::success( 'Keine Medien zum Scannen vorhanden.' );
			}
			return;
		}

		if ( $skip_scanned ) {
			$cached       = MediaAuditor::get_cached_results();
			$cached_count = ! empty( $cached['items'] ) ? count( $cached['items'] ) : 0;
			WP_CLI::line( sprintf( 'Scanne %d ungescannte Medien-Anhänge (%d bereits im Cache übersprungen)...', $total, $cached_count ) );
		} else {
			WP_CLI::line( sprintf( 'Scanne %d Medien-Anhänge...', $total ) );
		}

		$progress = \WP_CLI\Utils\make_progress_bar( 'Analysiere Verwendung', $total );

		$results = [];
		$chunks  = array_chunk( $queue, 15 );
		foreach ( $chunks as $chunk ) {
			$prefetched = MediaAuditor::prefetch_batch_data( $chunk );
			foreach ( $chunk as $id ) {
				$results[] = MediaAuditor::audit_attachment( $id, $prefetched, true );
				$progress->tick();
			}
		}
		$progress->finish();

		$summary = MediaAuditor::update_cached_results( $results );

		$filter = $assoc_args['filter'] ?? 'unattached';
		$format = $assoc_args['format'] ?? 'table';

		// Display items matching filter (from full cached results)
		$all_items = $summary['items'] ?? [];

		$filtered = array_filter( $all_items, function ( $item ) use ( $filter ) {
			if ( 'all' === $filter ) {
				return true;
			}
			if ( 'unattached' === $filter ) {
				return MediaAuditor::STATUS_UNATTACHED_UNUSED === ( $item['status'] ?? '' );
			}
			if ( 'attached' === $filter ) {
				return MediaAuditor::STATUS_ATTACHED_UNUSED === ( $item['status'] ?? '' );
			}
			if ( 'used' === $filter ) {
				return MediaAuditor::STATUS_USED === ( $item['status'] ?? '' );
			}
			return true;
		} );

		$table_data = [];
		foreach ( $filtered as $f ) {
			$ref_text = 'Keine';
			if ( ! empty( $f['references'] ) ) {
				$labels = array_map( function ( $r ) {
					return is_array( $r ) ? ( $r['label'] ?? '' ) : (string) $r;
				}, $f['references'] );
				$ref_text = implode( '; ', array_filter( $labels ) );
			} elseif ( ! empty( $f['parent_title'] ) ) {
				$ref_text = 'Zugeordnet zu: ' . $f['parent_title'];
			}

			$table_data[] = [
				'ID'         => $f['id'],
				'Datei'      => $f['filename'],
				'Speicher'   => $f['disk_human'] ?? SizeGuard::format_size( (int) ( $f['disk_bytes'] ?? 0 ), 1 ),
				'Status'     => $f['status'],
				'Referenzen' => $ref_text,
			];
		}

		\WP_CLI\Utils\format_items( $format, $table_data, [ 'ID', 'Datei', 'Speicher', 'Status', 'Referenzen' ] );
	}

	/**
	 * Move orphan media files to trash.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Permanently delete without moving to trash.
	 *
	 * [--yes]
	 * : Proceed without interactive confirmation prompt.
	 *
	 * [--from-cache]
	 * : Use existing cached audit results instead of re-scanning.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ha-optimizer clean-orphans
	 *     wp ha-optimizer clean-orphans --yes
	 *     wp ha-optimizer clean-orphans --from-cache --yes
	 *     wp ha-optimizer clean-orphans --force --yes
	 *
	 * @subcommand clean-orphans
	 *
	 * @param array<string> $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function clean_orphans( array $args, array $assoc_args ): void {
		$force      = ! empty( $assoc_args['force'] );
		$yes        = ! empty( $assoc_args['yes'] );
		$from_cache = ! empty( $assoc_args['from-cache'] );

		$orphans = [];

		if ( $from_cache ) {
			$cached = MediaAuditor::get_cached_results();
			foreach ( $cached['items'] ?? [] as $item ) {
				if ( MediaAuditor::STATUS_UNATTACHED_UNUSED === ( $item['status'] ?? '' ) ) {
					$orphans[] = $item;
				}
			}
		} else {
			$queue = MediaAuditor::get_audit_queue();
			WP_CLI::line( 'Ermittle verwaiste Medien...' );
			$chunks = array_chunk( $queue, 15 );
			foreach ( $chunks as $chunk ) {
				$prefetched = MediaAuditor::prefetch_batch_data( $chunk );
				foreach ( $chunk as $id ) {
					$audit = MediaAuditor::audit_attachment( $id, $prefetched, true );
					if ( MediaAuditor::STATUS_UNATTACHED_UNUSED === $audit['status'] ) {
						$orphans[] = $audit;
					}
				}
			}
		}

		if ( empty( $orphans ) ) {
			WP_CLI::success( 'Keine verwaisten Medien gefunden. Deine Mediathek ist sauber!' );
			return;
		}

		$total_bytes = array_sum( array_column( $orphans, 'disk_bytes' ) );
		WP_CLI::line( sprintf( '%d vollständig verwaiste Medien gefunden (%s belegter Speicher).', count( $orphans ), SizeGuard::format_size( $total_bytes, 1 ) ) );

		if ( ! $yes ) {
			$action_desc = $force ? 'DAUERHAFT LÖSCHEN' : 'in den Papierkorb verschieben';
			WP_CLI::confirm( sprintf( 'Möchtest du %d verwaiste Medien jetzt %s?', count( $orphans ), $action_desc ) );
		}

		$progress = \WP_CLI\Utils\make_progress_bar( 'Bereinige Medien', count( $orphans ) );
		$cleaned = 0;

		foreach ( $orphans as $orphan ) {
			if ( MediaAuditor::trash_attachment( $orphan['id'], $force ) ) {
				$cleaned++;
			}
			$progress->tick();
		}
		$progress->finish();

		WP_CLI::success( sprintf( '%d verwaiste Medien erfolgreich bereinigt! %s Speicherplatz freigegeben.', $cleaned, SizeGuard::format_size( $total_bytes, 1 ) ) );
	}
}

