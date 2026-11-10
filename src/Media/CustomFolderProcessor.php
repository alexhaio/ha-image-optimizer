<?php
/**
 * Custom Folder Processor for scanning and optimizing theme and non-media images.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Media;

use ha\ImageOptimizer\Common\Settings;
use ha\ImageOptimizer\Common\CompressionLevel;
use ha\ImageOptimizer\Engine\OptimizerFactory;
use ha\ImageOptimizer\Engine\SizeGuard;
use ha\ImageOptimizer\NextGen\WebpConverter;
use ha\ImageOptimizer\NextGen\AvifConverter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CustomFolderProcessor
 */
class CustomFolderProcessor {

	/**
	 * Option name for storing custom folder optimization stats.
	 */
	public const OPTION_NAME = 'ha_io_custom_data';

	/**
	 * Supported file extensions.
	 */
	public const SUPPORTED_EXTENSIONS = [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ];

	/**
	 * Get absolute base content directory.
	 *
	 * @return string
	 */
	public static function get_content_dir(): string {
		$dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
		return wp_normalize_path( $dir );
	}

	/**
	 * Get configured directories as absolute safe paths.
	 *
	 * @return array<string>
	 */
	public static function get_configured_folders(): array {
		$content_dir = self::get_content_dir();
		$raw_setting = (string) Settings::get( 'custom_folders', 'themes' );
		$lines       = explode( "\n", str_replace( "\r", '', $raw_setting ) );
		$valid_paths = [];

		foreach ( $lines as $line ) {
			$rel = trim( str_replace( '\\', '/', $line ) );
			$rel = trim( $rel, '/' );

			if ( empty( $rel ) || str_contains( $rel, '..' ) ) {
				continue;
			}

			$full_path = trailingslashit( $content_dir ) . $rel;
			$real_path = realpath( $full_path );

			if ( false !== $real_path && is_dir( $real_path ) ) {
				$real_norm    = wp_normalize_path( $real_path );
				$content_norm = wp_normalize_path( realpath( $content_dir ) ?: $content_dir );

				// Ensure path is strictly inside WP_CONTENT_DIR
				if ( str_starts_with( $real_norm, $content_norm ) ) {
					$valid_paths[] = $real_norm;
				}
			}
		}

		return array_unique( $valid_paths );
	}

	/**
	 * Recursively scan configured folders for eligible image files.
	 *
	 * @param array<string>|null $dirs Optional explicit directory paths.
	 * @return array<string> List of relative paths from WP_CONTENT_DIR.
	 */
	public static function scan_images( ?array $dirs = null ): array {
		$target_dirs = ( null !== $dirs ) ? $dirs : self::get_configured_folders();
		$content_dir = trailingslashit( self::get_content_dir() );
		$images      = [];

		foreach ( $target_dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}

			try {
				$directory = new \RecursiveDirectoryIterator(
					$dir,
					\FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
				);

				$filter = new \RecursiveCallbackFilterIterator(
					$directory,
					function ( \SplFileInfo $file ): bool {
						$name = $file->getFilename();

						// Skip hidden and vendor folders
						if ( str_starts_with( $name, '.' ) ) {
							return false;
						}

						if ( $file->isDir() ) {
							$ignored = [ 'node_modules', 'vendor', 'ha-backups', 'languages', '.git' ];
							return ! in_array( $name, $ignored, true );
						}

						$ext = strtolower( $file->getExtension() );
						return in_array( $ext, self::SUPPORTED_EXTENSIONS, true );
					}
				);

				$iterator = new \RecursiveIteratorIterator( $filter, \RecursiveIteratorIterator::LEAVES_ONLY );

				foreach ( $iterator as $item ) {
					if ( ! $item->isFile() ) {
						continue;
					}

					$file_path = wp_normalize_path( $item->getPathname() );

					// Skip generated next-gen companion files (e.g. photo.jpg.webp or photo.png.webp)
					if ( preg_match( '/\.(jpe?g|png|gif)\.(webp|avif)$/i', $file_path ) ) {
						continue;
					}

					// Skip standalone .avif files
					if ( str_ends_with( strtolower( $file_path ), '.avif' ) ) {
						continue;
					}

					// Calculate relative path to WP_CONTENT_DIR
					$rel = str_replace( $content_dir, '', $file_path );
					$rel = ltrim( $rel, '/\\' );

					$images[] = $rel;
				}
			} catch ( \Throwable ) {
				continue;
			}
		}

		return array_unique( $images );
	}

	/**
	 * Get queue of unoptimized relative file paths.
	 *
	 * @param bool $all Return all files regardless of optimization status.
	 * @return array<string>
	 */
	public static function get_queue( bool $all = false ): array {
		$all_files = self::scan_images();
		if ( $all ) {
			return $all_files;
		}

		$stored = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$queue = [];
		foreach ( $all_files as $rel ) {
			$key = md5( $rel );
			if ( empty( $stored[ $key ] ) ) {
				$queue[] = $rel;
			}
		}

		return $queue;
	}

	/**
	 * Get stats for custom folder images.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_stats(): array {
		$all_files = self::scan_images();
		$total     = count( $all_files );
		$stored    = get_option( self::OPTION_NAME, [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$optimized_count = 0;
		$total_before    = 0;
		$total_after     = 0;

		foreach ( $all_files as $rel ) {
			$key = md5( $rel );
			if ( ! empty( $stored[ $key ] ) ) {
				$data          = $stored[ $key ];
				$optimized_count++;
				$total_before += (int) ( $data['before'] ?? 0 );
				$total_after  += (int) ( $data['after'] ?? 0 );
			}
		}

		$saved_bytes   = max( 0, $total_before - $total_after );
		$saved_percent = ( $total_before > 0 ) ? round( ( $saved_bytes / $total_before ) * 100, 2 ) : 0.0;

		return [
			'total_images'     => $total,
			'optimized_images' => $optimized_count,
			'unoptimized'      => max( 0, $total - $optimized_count ),
			'total_before'     => $total_before,
			'total_after'      => $total_after,
			'saved_bytes'      => $saved_bytes,
			'saved_percent'    => $saved_percent,
		];
	}

	/**
	 * Process and optimize a single file in a custom directory.
	 *
	 * @param string $relative_path Relative path from WP_CONTENT_DIR.
	 * @param bool   $force Whether to force re-optimization.
	 * @return array<string, mixed>
	 * @throws \RuntimeException On error.
	 */
	public static function process_file( string $relative_path, bool $force = false ): array {
		@set_time_limit( 60 );
		wp_raise_memory_limit( 'image' );

		$content_dir = trailingslashit( self::get_content_dir() );
		$rel_clean   = ltrim( str_replace( '\\', '/', $relative_path ), '/' );

		// Security: prevent directory traversal
		if ( str_contains( $rel_clean, '..' ) ) {
			throw new \RuntimeException( 'Sicherheitsfehler: Ungültiger Pfad.' );
		}

		$abs_path = wp_normalize_path( $content_dir . $rel_clean );
		if ( ! file_exists( $abs_path ) ) {
			throw new \RuntimeException( 'Datei existiert nicht: ' . esc_html( $rel_clean ) );
		}

		$stored = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$key = md5( $rel_clean );
		if ( ! empty( $stored[ $key ] ) && ! $force ) {
			return $stored[ $key ];
		}

		// If forcing re-optimization and backup exists, restore pristine original first
		if ( $force ) {
			$custom_backup = BackupManager::get_custom_backup_file_path( $abs_path );
			if ( file_exists( $custom_backup ) ) {
				@copy( $custom_backup, $abs_path );
			}
		}

		$before_size = (int) filesize( $abs_path );
		if ( $force && ! empty( $stored[ $key ]['before'] ) && (int) $stored[ $key ]['before'] > $before_size ) {
			$before_size = (int) $stored[ $key ]['before'];
		}

		$settings = Settings::get_all();

		// 1. Backup original before touching
		if ( ! empty( $settings['backup_originals'] ) ) {
			BackupManager::backup_custom( $abs_path );
		}

		// 2. Pre-flight downscaling if oversized
		if ( ! empty( $settings['resize_large_images'] ) ) {
			$max_w = (int) ( $settings['max_width'] ?? 2560 );
			$max_h = (int) ( $settings['max_height'] ?? 2560 );
			Resizer::maybe_downscale( $abs_path, $max_w, $max_h );
		}

		// 3. Compress original file
		$level     = CompressionLevel::tryFrom( (string) $settings['compression_original'] ) ?? CompressionLevel::LOSSLESS;
		$optimizer = OptimizerFactory::create();
		$optimizer->optimize( $abs_path, $level, (bool) $settings['keep_exif'] );

		// Ensure size guard does not keep negative savings
		SizeGuard::protect_file( $abs_path, $before_size );
		$after_size = (int) filesize( $abs_path );

		// 4. Generate WebP
		$has_webp = false;
		if ( ! empty( $settings['generate_webp'] ) ) {
			$webp_path = WebpConverter::convert( $abs_path, 82 );
			if ( $webp_path && file_exists( $webp_path ) ) {
				$has_webp = true;
				SizeGuard::protect_nextgen( $webp_path, $after_size );
			}
		}

		// 5. Generate AVIF
		$has_avif = false;
		if ( ! empty( $settings['generate_avif'] ) ) {
			$avif_path = AvifConverter::convert( $abs_path, 80 );
			if ( $avif_path && file_exists( $avif_path ) ) {
				$has_avif = true;
				SizeGuard::protect_nextgen( $avif_path, $after_size );
			}
		}

		$saved_b = max( 0, $before_size - $after_size );
		$percent = ( $before_size > 0 ) ? round( ( $saved_b / $before_size ) * 100, 1 ) : 0.0;

		$item_data = [
			'file'      => $rel_clean,
			'filename'  => wp_basename( $abs_path ),
			'before'    => $before_size,
			'after'     => $after_size,
			'saved'     => $saved_b,
			'percent'   => $percent,
			'has_webp'  => $has_webp,
			'has_avif'  => $has_avif,
			'time'      => time(),
		];

		$stored[ $key ] = $item_data;
		update_option( self::OPTION_NAME, $stored );

		return $item_data;
	}

	/**
	 * Restore a custom file from its backup.
	 *
	 * @param string $relative_path Relative path from WP_CONTENT_DIR.
	 * @return bool
	 */
	public static function restore_file( string $relative_path ): bool {
		$content_dir = trailingslashit( self::get_content_dir() );
		$rel_clean   = ltrim( str_replace( '\\', '/', $relative_path ), '/' );
		$abs_path    = wp_normalize_path( $content_dir . $rel_clean );

		if ( ! BackupManager::restore_custom( $abs_path ) ) {
			return false;
		}

		$stored = get_option( self::OPTION_NAME, [] );
		if ( is_array( $stored ) ) {
			$key = md5( $rel_clean );
			unset( $stored[ $key ] );
			update_option( self::OPTION_NAME, $stored );
		}

		return true;
	}
}
