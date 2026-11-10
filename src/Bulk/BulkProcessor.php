<?php
/**
 * Bulk Processor for batch optimizations.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Bulk;

use ha\ImageOptimizer\Media\AttachmentProcessor;
use ha\ImageOptimizer\Media\CustomFolderProcessor;
use ha\ImageOptimizer\Engine\SizeGuard;
use ha\ImageOptimizer\Delivery\CachePurger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BulkProcessor
 */
class BulkProcessor {

	/**
	 * Process a batch of attachment IDs.
	 *
	 * @param array<int> $attachment_ids Array of attachment IDs.
	 * @param bool       $force Whether to force re-optimization.
	 * @return array<string, mixed> Result summary.
	 */
	public static function process_batch( array $attachment_ids, bool $force = false ): array {
		$processed   = 0;
		$saved_bytes = 0;
		$errors      = [];
		$items       = [];

		CachePurger::suspend();
		try {
			foreach ( $attachment_ids as $id ) {
				$id = absint( $id );
				if ( $id <= 0 ) {
					continue;
				}

				try {
					AttachmentProcessor::process( $id, null, $force );
					$data     = get_post_meta( $id, '_ha_io_data', true );
					$file     = get_attached_file( $id );
					$filename = $file ? wp_basename( $file ) : ( 'ID ' . $id );

					$before_b = (int) ( $data['total']['before'] ?? 0 );
					$after_b  = (int) ( $data['total']['after'] ?? 0 );
					$saved_b  = (int) ( $data['total']['saved'] ?? 0 );
					$percent  = (float) ( $data['total']['percent'] ?? 0.0 );

					if ( $saved_b > 0 ) {
						$saved_bytes += $saved_b;
					}

					$items[] = [
						'id'          => $id,
						'filename'    => $filename,
						'before'      => SizeGuard::format_size( $before_b, 1 ),
						'after'       => SizeGuard::format_size( $after_b, 1 ),
						'saved'       => SizeGuard::format_size( $saved_b, 1 ),
						'percent'     => number_format_i18n( $percent, 1 ),
						'has_webp'    => ! empty( $data['original']['webp_size'] ),
						'has_avif'    => ! empty( $data['original']['avif_size'] ),
					];

					$processed++;
				} catch ( \Throwable $e ) {
					$errors[] = [
						'id'      => $id,
						'message' => $e->getMessage(),
					];
				}
			}
		} finally {
			CachePurger::resume();
			if ( $processed > 0 ) {
				CachePurger::purge_all_caches();
			}
		}

		return [
			'processed'   => $processed,
			'saved_bytes' => $saved_bytes,
			'items'       => $items,
			'errors'      => $errors,
		];
	}

	/**
	 * Process a batch of relative custom folder file paths.
	 *
	 * @param array<string> $relative_paths Array of relative file paths.
	 * @param bool          $force Whether to force re-optimization.
	 * @return array<string, mixed> Result summary.
	 */
	public static function process_custom_batch( array $relative_paths, bool $force = false ): array {
		$processed   = 0;
		$saved_bytes = 0;
		$errors      = [];
		$items       = [];

		CachePurger::suspend();
		try {
			foreach ( $relative_paths as $rel_path ) {
				$rel_path = trim( sanitize_text_field( (string) $rel_path ) );
				if ( empty( $rel_path ) ) {
					continue;
				}

				try {
					$item    = CustomFolderProcessor::process_file( $rel_path, $force );
					$saved_b = (int) ( $item['saved'] ?? 0 );

					if ( $saved_b > 0 ) {
						$saved_bytes += $saved_b;
					}

					$items[] = [
						'id'       => $rel_path,
						'filename' => $item['filename'] ?? wp_basename( $rel_path ),
						'before'   => SizeGuard::format_size( (int) ( $item['before'] ?? 0 ), 1 ),
						'after'    => SizeGuard::format_size( (int) ( $item['after'] ?? 0 ), 1 ),
						'saved'    => SizeGuard::format_size( $saved_b, 1 ),
						'percent'  => number_format_i18n( (float) ( $item['percent'] ?? 0.0 ), 1 ),
						'has_webp' => ! empty( $item['has_webp'] ),
						'has_avif' => ! empty( $item['has_avif'] ),
					];

					$processed++;
				} catch ( \Throwable $e ) {
					$errors[] = [
						'id'      => $rel_path,
						'message' => $e->getMessage(),
					];
				}
			}
		} finally {
			CachePurger::resume();
			if ( $processed > 0 ) {
				CachePurger::purge_all_caches();
			}
		}

		return [
			'processed'   => $processed,
			'saved_bytes' => $saved_bytes,
			'items'       => $items,
			'errors'      => $errors,
		];
	}
}

