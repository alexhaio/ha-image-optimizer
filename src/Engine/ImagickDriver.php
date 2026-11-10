<?php
/**
 * Imagick Driver implementation.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Engine;

use ha\ImageOptimizer\Common\CompressionLevel;
use ha\ImageOptimizer\Common\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ImagickDriver
 */
class ImagickDriver implements OptimizerInterface {

	/**
	 * Check availability.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		$caps = Environment::get_capabilities();
		return (bool) $caps['imagick']['installed'];
	}

	/**
	 * Get name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'Imagick';
	}

	/**
	 * Optimize an image file.
	 *
	 * @param string           $file_path Absolute path to the file.
	 * @param CompressionLevel $level Compression level.
	 * @param bool             $keep_exif Whether to keep EXIF/IPTC.
	 * @return OptimizationResult
	 */
	public function optimize( string $file_path, CompressionLevel $level, bool $keep_exif = false ): OptimizationResult {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return OptimizationResult::failure( $file_path, __( 'Datei existiert nicht oder ist nicht lesbar.', 'ha-image-optimizer' ) );
		}

		$original_size = (int) filesize( $file_path );
		if ( $original_size <= 0 ) {
			return OptimizationResult::failure( $file_path, __( 'Dateigröße ist 0 Bytes.', 'ha-image-optimizer' ) );
		}

		wp_raise_memory_limit( 'image' );

		$temp_file = $file_path . '.tmp.' . wp_generate_password( 6, false );

		try {
			$imagick = new \Imagick( $file_path );
			$mime    = wp_check_filetype( $file_path )['type'] ?? '';

			// Strip metadata if not keeping EXIF
			if ( ! $keep_exif ) {
				$imagick->stripImage();
			}

			// Handle formats
			$is_multi_frame = false;
			if ( in_array( $mime, [ 'image/jpeg', 'image/jpg' ], true ) ) {
				$this->optimize_jpeg( $imagick, $level );
			} elseif ( 'image/png' === $mime ) {
				$this->optimize_png( $imagick, $level );
			} elseif ( 'image/gif' === $mime ) {
				$is_multi_frame = $this->optimize_gif( $imagick, $level );
			} elseif ( 'image/webp' === $mime ) {
				$this->optimize_webp( $imagick, $level );
			} elseif ( 'image/avif' === $mime ) {
				$this->optimize_avif( $imagick, $level );
			}

			if ( $is_multi_frame ) {
				$imagick->writeImages( $temp_file, true );
			} else {
				$imagick->writeImage( $temp_file );
			}
			$imagick->clear();
			$imagick->destroy();

			if ( ! file_exists( $temp_file ) ) {
				return OptimizationResult::failure( $file_path, __( 'Konnte temporäre Datei nicht schreiben.', 'ha-image-optimizer' ), $original_size );
			}

			$new_size = (int) filesize( $temp_file );

			// SizeGuard: Never replace if new file is larger or equal
			if ( ! SizeGuard::is_smaller( $original_size, $new_size ) ) {
				@unlink( $temp_file );
				return OptimizationResult::skipped(
					$file_path,
					$original_size,
					__( 'Original war bereits kleiner oder gleich groß. Datei beibehalten.', 'ha-image-optimizer' )
				);
			}

			// Replace original safely
			if ( ! @rename( $temp_file, $file_path ) ) {
				@copy( $temp_file, $file_path );
				@unlink( $temp_file );
			}

			$saved_bytes   = $original_size - $new_size;
			$saved_percent = SizeGuard::calculate_savings_percent( $original_size, $new_size );

			return new OptimizationResult(
				success: true,
				file_path: $file_path,
				original_size: $original_size,
				optimized_size: $new_size,
				saved_bytes: $saved_bytes,
				saved_percent: $saved_percent
			);

		} catch ( \Throwable $e ) {
			if ( file_exists( $temp_file ) ) {
				@unlink( $temp_file );
			}
			return OptimizationResult::failure( $file_path, $e->getMessage(), $original_size );
		}
	}

	/**
	 * JPEG specific optimization.
	 *
	 * @param \Imagick         $imagick Imagick instance.
	 * @param CompressionLevel $level Compression level.
	 * @return void
	 */
	private function optimize_jpeg( \Imagick $imagick, CompressionLevel $level ): void {
		$imagick->setImageCompression( \Imagick::COMPRESSION_JPEG );

		if ( CompressionLevel::LOSSLESS === $level ) {
			$imagick->setImageCompressionQuality( 100 );
			$imagick->setInterlaceScheme( \Imagick::INTERLACE_PLANE ); // Progressive JPEG
		} else {
			$imagick->setImageCompressionQuality( $level->quality( 'image/jpeg' ) );
			$imagick->setInterlaceScheme( \Imagick::INTERLACE_PLANE );
		}
	}

	/**
	 * PNG specific optimization.
	 *
	 * @param \Imagick         $imagick Imagick instance.
	 * @param CompressionLevel $level Compression level.
	 * @return void
	 */
	private function optimize_png( \Imagick $imagick, CompressionLevel $level ): void {
		$imagick->setImageCompression( \Imagick::COMPRESSION_ZIP );

		if ( CompressionLevel::LOSSLESS === $level ) {
			$imagick->setOption( 'png:compression-level', '9' );
			$imagick->setOption( 'png:compression-filter', '5' );
		} else {
			$imagick->setOption( 'png:compression-level', '9' );
			// Dither / quantize if high compression requested and non-empty palette
			if ( CompressionLevel::ULTRA === $level && $imagick->getImageColors() > 256 ) {
				try {
					$imagick->quantizeImage( 256, \Imagick::COLORSPACE_RGB, 0, false, false );
				} catch ( \Throwable ) {
					// Fallback to standard zip compression if quantize fails
				}
			}
		}
	}

	/**
	 * GIF optimization with frame handling.
	 *
	 * @param \Imagick         $imagick Imagick instance (passed by reference).
	 * @param CompressionLevel $level   Compression level.
	 * @return bool True if animated multi-frame GIF.
	 */
	private function optimize_gif( \Imagick &$imagick, CompressionLevel $level ): bool {
		if ( $imagick->getNumberImages() > 1 ) {
			$coalesced = $imagick->coalesceImages();
			$imagick   = $coalesced->deconstructImages();
			return true;
		}
		return false;
	}

	/**
	 * WebP optimization.
	 *
	 * @param \Imagick         $imagick Imagick instance.
	 * @param CompressionLevel $level   Compression level.
	 * @return void
	 */
	private function optimize_webp( \Imagick $imagick, CompressionLevel $level ): void {
		$imagick->setImageFormat( 'webp' );
		$quality = ( CompressionLevel::LOSSLESS === $level ) ? 100 : $level->quality( 'image/webp' );
		$imagick->setImageCompressionQuality( $quality );
		$imagick->setOption( 'webp:method', '4' );
	}

	/**
	 * AVIF optimization.
	 *
	 * @param \Imagick         $imagick Imagick instance.
	 * @param CompressionLevel $level   Compression level.
	 * @return void
	 */
	private function optimize_avif( \Imagick $imagick, CompressionLevel $level ): void {
		$imagick->setImageFormat( 'avif' );
		$quality = ( CompressionLevel::LOSSLESS === $level ) ? 100 : $level->quality( 'image/avif' );
		$imagick->setImageCompressionQuality( $quality );
	}

	/**
	 * Generate WebP image.
	 *
	 * @param string $source_path Source file.
	 * @param string $target_path Destination file.
	 * @param int    $quality Quality (1-100).
	 * @return bool
	 */
	public function generate_webp( string $source_path, string $target_path, int $quality ): bool {
		if ( ! file_exists( $source_path ) ) {
			return false;
		}

		wp_raise_memory_limit( 'image' );
		$temp_file = $target_path . '.tmp.' . wp_generate_password( 6, false );

		try {
			$imagick = new \Imagick( $source_path );
			$imagick->setImageFormat( 'webp' );
			$imagick->setImageCompressionQuality( $quality );
			$imagick->setOption( 'webp:method', '4' ); // Fast & balanced compression (replaces method 6 to prevent server timeouts)

			$imagick->writeImage( $temp_file );
			$imagick->clear();
			$imagick->destroy();

			if ( ! file_exists( $temp_file ) || filesize( $temp_file ) <= 0 ) {
				@unlink( $temp_file );
				return false;
			}

			// SizeGuard: If source exists and webp is actually larger than source, discard webp
			$src_size = (int) filesize( $source_path );
			$new_size = (int) filesize( $temp_file );
			if ( ! SizeGuard::is_smaller( $src_size, $new_size ) ) {
				@unlink( $temp_file );
				return false;
			}

			if ( ! @rename( $temp_file, $target_path ) ) {
				@copy( $temp_file, $target_path );
				@unlink( $temp_file );
			}

			return file_exists( $target_path );
		} catch ( \Throwable ) {
			if ( file_exists( $temp_file ) ) {
				@unlink( $temp_file );
			}
			return false;
		}
	}

	/**
	 * Generate AVIF image.
	 *
	 * @param string $source_path Source file.
	 * @param string $target_path Destination file.
	 * @param int    $quality Quality (1-100).
	 * @return bool
	 */
	public function generate_avif( string $source_path, string $target_path, int $quality ): bool {
		$caps = Environment::get_capabilities();
		if ( empty( $caps['imagick']['avif'] ) || ! file_exists( $source_path ) ) {
			return false;
		}

		wp_raise_memory_limit( 'image' );
		$temp_file = $target_path . '.tmp.' . wp_generate_password( 6, false );

		try {
			$imagick = new \Imagick( $source_path );
			$imagick->setImageFormat( 'avif' );
			$imagick->setImageCompressionQuality( $quality );

			// AVIF speed optimization: 0 (slowest) - 10 (fastest). 6-7 is optimal for web/server environments.
			try {
				$imagick->setOption( 'heic:speed', '6' );
				$imagick->setOption( 'avif:speed', '6' );
			} catch ( \Throwable ) {
				// Silently continue if underlying ImageMagick coder does not support speed option
			}

			$imagick->writeImage( $temp_file );
			$imagick->clear();
			$imagick->destroy();

			if ( ! file_exists( $temp_file ) || filesize( $temp_file ) <= 0 ) {
				@unlink( $temp_file );
				return false;
			}

			// SizeGuard
			$src_size = (int) filesize( $source_path );
			$new_size = (int) filesize( $temp_file );
			if ( ! SizeGuard::is_smaller( $src_size, $new_size ) ) {
				@unlink( $temp_file );
				return false;
			}

			if ( ! @rename( $temp_file, $target_path ) ) {
				@copy( $temp_file, $target_path );
				@unlink( $temp_file );
			}

			return file_exists( $target_path );
		} catch ( \Throwable ) {
			if ( file_exists( $temp_file ) ) {
				@unlink( $temp_file );
			}
			return false;
		}
	}
}
