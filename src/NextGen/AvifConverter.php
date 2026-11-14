<?php
/**
 * AVIF Converter.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\NextGen;

use ha\ImageOptimizer\Engine\OptimizerFactory;
use ha\ImageOptimizer\Common\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AvifConverter
 */
class AvifConverter {

	/**
	 * Convert an image to AVIF.
	 *
	 * @param string $source_path Absolute path to the source image.
	 * @param int    $quality AVIF quality (1-100).
	 * @return string|null Absolute path to generated .avif on success, null on failure.
	 */
	public static function convert( string $source_path, int $quality = 75 ): ?string {
		if ( ! Environment::can_generate_avif() || ! file_exists( $source_path ) ) {
			return null;
		}

		// Do not re-encode files that are already AVIF (.avif.avif)
		if ( str_ends_with( strtolower( $source_path ), '.avif' ) ) {
			return null;
		}

		$target_path = self::get_destination_path( $source_path );

		try {
			$optimizer = OptimizerFactory::create();
			$success   = $optimizer->generate_avif( $source_path, $target_path, $quality );

			return $success ? $target_path : null;
		} catch ( \Throwable ) {
			return null;
		}
	}

	/**
	 * Get the standard destination path for an AVIF version.
	 * Format: /path/to/image.jpg.avif
	 *
	 * @param string $source_path Source file path.
	 * @return string Destination path.
	 */
	public static function get_destination_path( string $source_path ): string {
		return $source_path . '.avif';
	}
}
