<?php
/**
 * Progress Tracker & Media Library Statistics.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Bulk;

use ha\ImageOptimizer\Media\AttachmentProcessor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProgressTracker
 */
class ProgressTracker {

	/**
	 * Get total count and statistics of media items.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_stats(): array {
		global $wpdb;

		// Count total eligible images in library
		$mime_placeholders = implode( "','", array_map( 'esc_sql', AttachmentProcessor::SUPPORTED_MIMES ) );
		$total_count       = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type IN ('{$mime_placeholders}')" );

		if ( 0 === $total_count ) {
			return [
				'total_images'     => 0,
				'optimized_images' => 0,
				'unoptimized'      => 0,
				'total_before'     => 0,
				'total_after'      => 0,
				'saved_bytes'      => 0,
				'saved_percent'    => 0.0,
			];
		}

		// Stream optimized meta rows in batches to avoid exhausting PHP memory on huge libraries
		$optimized_count = 0;
		$total_before    = 0;
		$total_after     = 0;
		$batch_size      = 1000;
		$offset          = 0;

		while ( true ) {
			$optimized_query = $wpdb->prepare(
				"SELECT pm.meta_value FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON (pm.post_id = p.ID)
				WHERE pm.meta_key = '_ha_io_data'
				AND p.post_type = 'attachment'
				AND p.post_mime_type IN ('{$mime_placeholders}')
				ORDER BY pm.meta_id ASC
				LIMIT %d OFFSET %d",
				$batch_size,
				$offset
			);
			$meta_rows = $wpdb->get_results( $optimized_query );

			if ( empty( $meta_rows ) ) {
				break;
			}

			$row_count        = count( $meta_rows );
			$optimized_count += $row_count;

			foreach ( $meta_rows as $row ) {
				$data = maybe_unserialize( $row->meta_value );
				if ( is_array( $data ) && ! empty( $data['total'] ) ) {
					$total_before += (int) ( $data['total']['before'] ?? 0 );
					$total_after  += (int) ( $data['total']['after'] ?? 0 );
				}
			}

			if ( $row_count < $batch_size ) {
				break;
			}

			$offset += $batch_size;
		}

		$saved_bytes   = max( 0, $total_before - $total_after );
		$saved_percent = ( $total_before > 0 ) ? round( ( $saved_bytes / $total_before ) * 100, 2 ) : 0.0;

		return [
			'total_images'     => $total_count,
			'optimized_images' => $optimized_count,
			'unoptimized'      => max( 0, $total_count - $optimized_count ),
			'total_before'     => $total_before,
			'total_after'      => $total_after,
			'saved_bytes'      => $saved_bytes,
			'saved_percent'    => $saved_percent,
		];
	}

	/**
	 * Get attachment IDs that need optimization.
	 *
	 * @param int  $limit Max items to retrieve.
	 * @param bool $all If true, returns all eligible items regardless of existing optimization.
	 * @return array<int>
	 */
	public static function get_queue( int $limit = 50, bool $all = false ): array {
		global $wpdb;

		$mime_placeholders = implode( "','", array_map( 'esc_sql', AttachmentProcessor::SUPPORTED_MIMES ) );

		if ( $all ) {
			$sql = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type IN ('{$mime_placeholders}') ORDER BY ID DESC LIMIT %d",
				$limit
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_ha_io_data')
				WHERE p.post_type = 'attachment'
				AND p.post_mime_type IN ('{$mime_placeholders}')
				AND pm.post_id IS NULL
				ORDER BY p.ID DESC
				LIMIT %d",
				$limit
			);
		}

		$results = $wpdb->get_col( $sql );
		return array_map( 'absint', $results );
	}
}
