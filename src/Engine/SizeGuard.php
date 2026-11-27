<?php
/**
 * Size Guard - Prevents negative savings.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SizeGuard
 */
class SizeGuard {

	/**
	 * Verify if an optimized file is smaller than original.
	 *
	 * @param int $original_bytes Original size in bytes.
	 * @param int $new_bytes New size in bytes.
	 * @return bool True if new file is strictly smaller and valid.
	 */
	public static function is_smaller( int $original_bytes, int $new_bytes ): bool {
		return ( $new_bytes > 0 && $new_bytes < $original_bytes );
	}

	/**
	 * Protect an optimized file from negative savings.
	 *
	 * @param string      $file_path Path to file on disk.
	 * @param int         $original_bytes Size of original before compression.
	 * @param string|null $backup_path Optional backup path to restore from if larger.
	 * @return bool True if kept (smaller), false if rejected (larger or equal).
	 */
	public static function protect_file( string $file_path, int $original_bytes, ?string $backup_path = null ): bool {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$current_size = (int) filesize( $file_path );
		if ( ! self::is_smaller( $original_bytes, $current_size ) ) {
			if ( $backup_path && file_exists( $backup_path ) ) {
				@copy( $backup_path, $file_path );
			}
			return false;
		}

		return true;
	}

	/**
	 * Protect NextGen companion files (WebP/AVIF).
	 * If the NextGen file is larger than or equal to the compressed source, delete it.
	 *
	 * @param string $nextgen_path Path to generated .webp or .avif.
	 * @param int    $source_bytes Size of the source image.
	 * @return bool True if kept (smaller), false if deleted (larger or equal).
	 */
	public static function protect_nextgen( string $nextgen_path, int $source_bytes ): bool {
		if ( ! file_exists( $nextgen_path ) ) {
			return false;
		}

		$nextgen_size = (int) filesize( $nextgen_path );
		if ( ! self::is_smaller( $source_bytes, $nextgen_size ) ) {
			wp_delete_file( $nextgen_path );
			return false;
		}

		return true;
	}

	/**
	 * Calculate percentage saved.
	 *
	 * @param int $original_bytes Size before.
	 * @param int $new_bytes Size after.
	 * @return float Percent saved (e.g. 42.5).
	 */
	public static function calculate_savings_percent( int $original_bytes, int $new_bytes ): float {
		if ( $original_bytes <= 0 || $new_bytes >= $original_bytes ) {
			return 0.0;
		}

		$saved = $original_bytes - $new_bytes;
		return round( ( $saved / $original_bytes ) * 100, 2 );
	}

	/**
	 * Format byte count into human-readable string with decimal precision.
	 * Guarantees at least 1 decimal place for KB, MB, GB so values like
	 * 2.3 MB -> 2.1 MB are never collapsed to "2 MB -> 2 MB".
	 *
	 * @param int $bytes Size in bytes.
	 * @param int $decimals Minimum decimal places (default: 1).
	 * @return string Human-readable size string (e.g. "2,4 MB", "48,5 KB").
	 */
	public static function format_size( int $bytes, int $decimals = 1 ): string {
		$bytes = max( 0, $bytes );
		if ( $bytes < 1024 ) {
			return (string) size_format( $bytes, 0 );
		}

		return (string) size_format( $bytes, $decimals );
	}
}
