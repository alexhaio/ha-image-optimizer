<?php
/**
 * GD Driver implementation.
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
 * Class GdDriver
 */
class GdDriver implements OptimizerInterface {

	/**
	 * Convert palette-based GD images to truecolor and preserve alpha channel.
	 *
	 * @param \GdImage $image GD image resource/instance.
	 * @return void
	 */
	private function prepare_truecolor_alpha( \GdImage $image ): void {
		if ( function_exists( 'imageistruecolor' ) && ! imageistruecolor( $image ) && function_exists( 'imagepalettetotruecolor' ) ) {
			imagepalettetotruecolor( $image );
		}
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
	}

	/**
	 * Check availability.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		$caps = Environment::get_capabilities();
		return (bool) $caps['gd']['installed'];
	}

	/**
	 * Get name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'GD Library';
	}

	/**
	 * Optimize an image file.
	 *
	 * @param string           $file_path Absolute path to the file.
	 * @param CompressionLevel $level Compression level.
	 * @param bool             $keep_exif Whether to keep EXIF/IPTC. (Note: GD always strips EXIF on re-save).
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

		$mime = wp_check_filetype( $file_path )['type'] ?? '';

		// Protect animated GIFs from GD
		if ( 'image/gif' === $mime && $this->is_animated_gif( $file_path ) ) {
			return OptimizationResult::skipped(
				$file_path,
				$original_size,
				__( 'Animiertes GIF übersprungen, um Animationsverlust in GD zu verhindern.', 'ha-image-optimizer' )
			);
		}

		wp_raise_memory_limit( 'image' );
		$temp_file = $file_path . '.tmp.' . wp_generate_password( 6, false );

		try {
			$image = $this->create_image_from_file( $file_path, $mime );
			if ( ! $image ) {
				return OptimizationResult::failure( $file_path, __( 'GD konnte das Bild nicht dekodieren.', 'ha-image-optimizer' ), $original_size );
			}

			$saved = false;
			if ( in_array( $mime, [ 'image/jpeg', 'image/jpg' ], true ) ) {
				$quality = ( CompressionLevel::LOSSLESS === $level ) ? 95 : $level->quality( 'image/jpeg' );
				$saved   = imagejpeg( $image, $temp_file, $quality );
			} elseif ( 'image/png' === $mime ) {
				$this->prepare_truecolor_alpha( $image );
				$saved = imagepng( $image, $temp_file, 9 );
			} elseif ( 'image/gif' === $mime ) {
				$saved = imagegif( $image, $temp_file );
			} elseif ( 'image/webp' === $mime && function_exists( 'imagewebp' ) ) {
				$this->prepare_truecolor_alpha( $image );
				$quality = ( CompressionLevel::LOSSLESS === $level ) ? 95 : $level->quality( 'image/webp' );
				$saved   = imagewebp( $image, $temp_file, $quality );
			} elseif ( 'image/avif' === $mime && function_exists( 'imageavif' ) ) {
				$this->prepare_truecolor_alpha( $image );
				$quality = ( CompressionLevel::LOSSLESS === $level ) ? 95 : $level->quality( 'image/avif' );
				$saved   = imageavif( $image, $temp_file, $quality );
			}

			// Free memory
			imagedestroy( $image );

			if ( ! $saved || ! file_exists( $temp_file ) ) {
				if ( file_exists( $temp_file ) ) {
					@unlink( $temp_file );
				}
				return OptimizationResult::failure( $file_path, __( 'Fehler beim Speichern der optimierten GD-Datei.', 'ha-image-optimizer' ), $original_size );
			}

			$new_size = (int) filesize( $temp_file );

			// SizeGuard check
			if ( ! SizeGuard::is_smaller( $original_size, $new_size ) ) {
				@unlink( $temp_file );
				return OptimizationResult::skipped(
					$file_path,
					$original_size,
					__( 'Original war bereits kleiner oder gleich groß. Datei beibehalten.', 'ha-image-optimizer' )
				);
			}

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
	 * Generate WebP image via GD.
	 *
	 * @param string $source_path Source file.
	 * @param string $target_path Target .webp file.
	 * @param int    $quality Quality (1-100).
	 * @return bool
	 */
	public function generate_webp( string $source_path, string $target_path, int $quality ): bool {
		if ( ! function_exists( 'imagewebp' ) || ! file_exists( $source_path ) ) {
			return false;
		}

		wp_raise_memory_limit( 'image' );
		$mime  = wp_check_filetype( $source_path )['type'] ?? '';
		$image = $this->create_image_from_file( $source_path, $mime );
		if ( ! $image ) {
			return false;
		}

		// Handle transparency and palette conversion (8-bit PNG/GIF -> truecolor) for WebP
		$this->prepare_truecolor_alpha( $image );

		$temp_file = $target_path . '.tmp.' . wp_generate_password( 6, false );
		$result    = imagewebp( $image, $temp_file, $quality );
		imagedestroy( $image );

		if ( ! $result || ! file_exists( $temp_file ) ) {
			if ( file_exists( $temp_file ) ) {
				@unlink( $temp_file );
			}
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
	}

	/**
	 * Generate AVIF image via GD (PHP 8.1+).
	 *
	 * @param string $source_path Source file.
	 * @param string $target_path Target .avif file.
	 * @param int    $quality Quality (1-100).
	 * @return bool
	 */
	public function generate_avif( string $source_path, string $target_path, int $quality ): bool {
		if ( ! function_exists( 'imageavif' ) || ! file_exists( $source_path ) ) {
			return false;
		}

		wp_raise_memory_limit( 'image' );
		$mime  = wp_check_filetype( $source_path )['type'] ?? '';
		$image = $this->create_image_from_file( $source_path, $mime );
		if ( ! $image ) {
			return false;
		}

		$this->prepare_truecolor_alpha( $image );

		$temp_file = $target_path . '.tmp.' . wp_generate_password( 6, false );
		try {
			// In PHP 8.1+, imageavif accepts $speed (0 slowest, 10 fastest; 6 is optimal for web servers)
			$result = @imageavif( $image, $temp_file, $quality, 6 );
		} catch ( \ArgumentCountError ) {
			$result = @imageavif( $image, $temp_file, $quality );
		}
		imagedestroy( $image );

		if ( ! $result || ! file_exists( $temp_file ) ) {
			if ( file_exists( $temp_file ) ) {
				@unlink( $temp_file );
			}
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
	}

	/**
	 * Helper: Create GD resource from file.
	 *
	 * @param string $file_path File path.
	 * @param string $mime MIME type.
	 * @return \GdImage|false
	 */
	private function create_image_from_file( string $file_path, string $mime ): mixed {
		return match ( $mime ) {
			'image/jpeg', 'image/jpg' => @imagecreatefromjpeg( $file_path ),
			'image/png'               => @imagecreatefrompng( $file_path ),
			'image/gif'               => @imagecreatefromgif( $file_path ),
			'image/webp'              => function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $file_path ) : false,
			'image/avif'              => function_exists( 'imagecreatefromavif' ) ? @imagecreatefromavif( $file_path ) : false,
			default                   => false,
		};
	}

	/**
	 * Helper: Check if a GIF file contains multiple frames (animated).
	 *
	 * @param string $file_path GIF file path.
	 * @return bool
	 */
	private function is_animated_gif( string $file_path ): bool {
		$filecontents = @file_get_contents( $file_path );
		if ( false === $filecontents ) {
			return false;
		}

		$str_loc = 0;
		$count   = 0;

		// Detect multiple graphic control extensions (0x21 0xF9)
		while ( $count < 2 ) {
			$where1 = strpos( $filecontents, "\x00\x21\xF9\x04", $str_loc );
			if ( false === $where1 ) {
				break;
			}
			$str_loc = $where1 + 1;
			$where2  = strpos( $filecontents, "\x00\x2C", $str_loc );
			if ( false === $where2 ) {
				break;
			}
			if ( $where1 + 8 === $where2 ) {
				$count++;
			}
			$str_loc = $where2 + 1;
		}

		return ( $count > 1 );
	}
}
