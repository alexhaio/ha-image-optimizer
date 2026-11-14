<?php
/**
 * Backup Manager for original images.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BackupManager
 */
class BackupManager {

	/**
	 * Directory name inside wp-content/uploads.
	 */
	public const BACKUP_DIR_NAME = 'ha-backups';

	/**
	 * Get the absolute base path to the backup folder.
	 *
	 * @return string
	 */
	public static function get_backup_dir(): string {
		$upload_dir = wp_upload_dir();
		$path       = trailingslashit( $upload_dir['basedir'] ) . self::BACKUP_DIR_NAME;

		if ( ! file_exists( $path ) ) {
			wp_mkdir_p( $path );
			self::secure_directory( $path );
		}

		return $path;
	}

	/**
	 * Secure the backup folder with .htaccess and index.php.
	 *
	 * @param string $dir Path to secure.
	 * @return void
	 */
	public static function secure_directory( string $dir ): void {
		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$rules = "# Secure HA Image Optimizer Backups\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n";
			@file_put_contents( $htaccess, $rules );
		}

		$index = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Create a backup of an original file before it is touched.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $source_path Absolute path to the original file.
	 * @return bool True if backup exists or was successfully created.
	 */
	public static function backup( int $attachment_id, string $source_path ): bool {
		if ( ! file_exists( $source_path ) ) {
			return false;
		}

		$backup_path = self::get_backup_file_path( $attachment_id, $source_path );
		if ( file_exists( $backup_path ) ) {
			return true; // Already backed up previously
		}

		$target_dir = dirname( $backup_path );
		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		return (bool) @copy( $source_path, $backup_path );
	}

	/**
	 * Calculate the backup destination path for an attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $source_path Absolute source path.
	 * @return string
	 */
	public static function get_backup_file_path( int $attachment_id, string $source_path ): string {
		$upload_dir = wp_upload_dir();
		$basedir    = function_exists( 'wp_normalize_path' ) ? wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) ) : trailingslashit( $upload_dir['basedir'] );
		$normalized_source = function_exists( 'wp_normalize_path' ) ? wp_normalize_path( $source_path ) : $source_path;

		// Determine relative path inside uploads/ (e.g. 2026/10/foto.jpg)
		$relative = str_replace( $basedir, '', $normalized_source );
		$relative = ltrim( $relative, '/\\' );

		return trailingslashit( self::get_backup_dir() ) . $relative;
	}

	/**
	 * Resolve origin backup record when an attachment was format-converted (e.g. PNG to JPEG).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{target_file: string, backup_path: string, mime: string}|null
	 */
	private static function get_origin_record( int $attachment_id ): ?array {
		$origin = get_post_meta( $attachment_id, '_ha_io_backup_origin', true );
		if ( ! is_array( $origin ) || empty( $origin['mime'] ) ) {
			return null;
		}

		$target_file = '';
		if ( ! empty( $origin['rel_file'] ) && function_exists( 'wp_upload_dir' ) ) {
			$upload_dir  = wp_upload_dir();
			$target_file = trailingslashit( $upload_dir['basedir'] ) . ltrim( (string) $origin['rel_file'], '/\\' );
		} elseif ( ! empty( $origin['abs_file'] ) ) {
			$target_file = (string) $origin['abs_file'];
		}

		if ( '' === $target_file ) {
			return null;
		}

		$backup_path = self::get_backup_file_path( $attachment_id, $target_file );
		if ( ! file_exists( $backup_path ) ) {
			return null;
		}

		return [
			'target_file' => $target_file,
			'backup_path' => $backup_path,
			'mime'        => (string) $origin['mime'],
		];
	}

	/**
	 * Check if an attachment has a stored backup.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function has_backup( int $attachment_id ): bool {
		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return false;
		}

		$backup_path = self::get_backup_file_path( $attachment_id, $file );
		if ( file_exists( $backup_path ) ) {
			return true;
		}

		return null !== self::get_origin_record( $attachment_id );
	}

	/**
	 * Restore pristine master file without deleting the backup (for forced re-optimization).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|null Restored master file path, or null if no backup exists.
	 */
	public static function restore_master_for_reoptimize( int $attachment_id ): ?string {
		$current_file = get_attached_file( $attachment_id );
		if ( ! $current_file ) {
			return null;
		}

		$origin      = self::get_origin_record( $attachment_id );
		$target_file = $origin ? $origin['target_file'] : $current_file;
		$backup_path = $origin ? $origin['backup_path'] : self::get_backup_file_path( $attachment_id, $current_file );

		if ( ! file_exists( $backup_path ) ) {
			return null;
		}

		self::clean_nextgen_files( $attachment_id );

		// If attachment was converted (e.g. PNG -> JPG), remove derived file & its subsizes before copying original back
		if ( $origin && $target_file !== $current_file ) {
			self::delete_subsizes_files( $attachment_id, $current_file );
			if ( file_exists( $current_file ) ) {
				@unlink( $current_file );
			}
		}

		if ( ! @copy( $backup_path, $target_file ) ) {
			return null;
		}

		if ( $origin && $target_file !== $current_file ) {
			if ( function_exists( 'update_attached_file' ) ) {
				update_attached_file( $attachment_id, $target_file );
			}
			if ( function_exists( 'wp_update_post' ) ) {
				wp_update_post( [
					'ID'             => $attachment_id,
					'post_mime_type' => $origin['mime'],
				] );
			}
			delete_post_meta( $attachment_id, '_ha_io_backup_origin' );
		}

		return $target_file;
	}

	/**
	 * Delete generated intermediate sub-size image files for an attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $master_file   Master file path to locate directory.
	 * @return void
	 */
	private static function delete_subsizes_files( int $attachment_id, string $master_file ): void {
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
			return;
		}

		$base_dir = dirname( $master_file );
		foreach ( $meta['sizes'] as $size ) {
			if ( ! empty( $size['file'] ) ) {
				$sub_path = trailingslashit( $base_dir ) . $size['file'];
				if ( file_exists( $sub_path ) && $sub_path !== $master_file ) {
					@unlink( $sub_path );
				}
			}
		}
	}

	/**
	 * Restore an attachment from its backup.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True on success.
	 */
	public static function restore( int $attachment_id ): bool {
		$current_file = get_attached_file( $attachment_id );
		if ( ! $current_file ) {
			return false;
		}

		$origin      = self::get_origin_record( $attachment_id );
		$target_file = $origin ? $origin['target_file'] : $current_file;
		$backup_path = $origin ? $origin['backup_path'] : self::get_backup_file_path( $attachment_id, $current_file );

		if ( ! file_exists( $backup_path ) ) {
			return false;
		}

		// 1. Remove all generated .webp and .avif files first (using current attachment file/meta)
		self::clean_nextgen_files( $attachment_id );

		// If converted from another format (e.g. PNG -> JPEG), delete derived file & its subsizes and switch back
		if ( $origin && $target_file !== $current_file ) {
			self::delete_subsizes_files( $attachment_id, $current_file );
			if ( file_exists( $current_file ) ) {
				@unlink( $current_file );
			}
		}

		// 2. Copy backup back to original location
		if ( ! @copy( $backup_path, $target_file ) ) {
			return false;
		}

		if ( $origin && $target_file !== $current_file ) {
			if ( function_exists( 'update_attached_file' ) ) {
				update_attached_file( $attachment_id, $target_file );
			}
			if ( function_exists( 'wp_update_post' ) ) {
				wp_update_post( [
					'ID'             => $attachment_id,
					'post_mime_type' => $origin['mime'],
				] );
			}
			delete_post_meta( $attachment_id, '_ha_io_backup_origin' );
		}

		// 3. Regenerate thumbnails from restored master
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/image.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( function_exists( 'wp_generate_attachment_metadata' ) ) {
			$metadata = wp_generate_attachment_metadata( $attachment_id, $target_file );
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		// 4. Clear optimization metadata
		delete_post_meta( $attachment_id, '_ha_io_data' );
		delete_post_meta( $attachment_id, '_ha_io_custom_level' );

		// 5. Delete backup file itself
		@unlink( $backup_path );

		return true;
	}

	/**
	 * Clean all generated .webp and .avif files for this attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function clean_nextgen_files( int $attachment_id ): void {
		$meta = wp_get_attachment_metadata( $attachment_id );
		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return;
		}

		$base_dir = dirname( $file );

		// Master file WebP/AVIF
		$candidates = [
			$file . '.webp',
			preg_replace( '/\.[^.]+$/', '.webp', $file ),
			$file . '.avif',
			preg_replace( '/\.[^.]+$/', '.avif', $file ),
		];

		// Sub-sizes WebP/AVIF
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) ) {
					$sub_path = trailingslashit( $base_dir ) . $size['file'];
					$candidates[] = $sub_path . '.webp';
					$candidates[] = preg_replace( '/\.[^.]+$/', '.webp', $sub_path );
					$candidates[] = $sub_path . '.avif';
					$candidates[] = preg_replace( '/\.[^.]+$/', '.avif', $sub_path );
				}
			}
		}

		foreach ( array_unique( $candidates ) as $candidate ) {
			if ( $candidate && file_exists( $candidate ) && $candidate !== $file ) {
				@unlink( $candidate );
			}
		}
	}

	/**
	 * Calculate backup path for a custom file inside WP_CONTENT_DIR.
	 *
	 * @param string $source_path Absolute file path.
	 * @return string Backup path.
	 */
	public static function get_custom_backup_file_path( string $source_path ): string {
		$content_dir     = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
		$normalized_base = trailingslashit( wp_normalize_path( $content_dir ) );
		$normalized_src  = wp_normalize_path( $source_path );

		$relative = str_replace( $normalized_base, '', $normalized_src );
		$relative = ltrim( $relative, '/\\' );

		return trailingslashit( self::get_backup_dir() ) . 'custom/' . $relative;
	}

	/**
	 * Backup a custom file before optimization.
	 *
	 * @param string $source_path Absolute path.
	 * @return bool
	 */
	public static function backup_custom( string $source_path ): bool {
		if ( ! file_exists( $source_path ) ) {
			return false;
		}

		$backup_path = self::get_custom_backup_file_path( $source_path );
		if ( file_exists( $backup_path ) ) {
			return true;
		}

		$target_dir = dirname( $backup_path );
		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		return (bool) @copy( $source_path, $backup_path );
	}

	/**
	 * Check if a custom file has a backup.
	 *
	 * @param string $source_path Absolute path.
	 * @return bool
	 */
	public static function has_custom_backup( string $source_path ): bool {
		$backup_path = self::get_custom_backup_file_path( $source_path );
		return file_exists( $backup_path );
	}

	/**
	 * Restore a custom file from backup.
	 *
	 * @param string $source_path Absolute path.
	 * @return bool
	 */
	public static function restore_custom( string $source_path ): bool {
		$backup_path = self::get_custom_backup_file_path( $source_path );
		if ( ! file_exists( $backup_path ) ) {
			return false;
		}

		if ( ! @copy( $backup_path, $source_path ) ) {
			return false;
		}

		self::clean_custom_nextgen( $source_path );
		@unlink( $backup_path );

		return true;
	}

	/**
	 * Clean next-gen .webp and .avif files for a custom file.
	 *
	 * @param string $source_path Absolute path.
	 * @return void
	 */
	public static function clean_custom_nextgen( string $source_path ): void {
		$candidates = [
			$source_path . '.webp',
			preg_replace( '/\.[^.]+$/', '.webp', $source_path ),
			$source_path . '.avif',
			preg_replace( '/\.[^.]+$/', '.avif', $source_path ),
		];

		foreach ( array_unique( $candidates ) as $candidate ) {
			if ( $candidate && file_exists( $candidate ) && $candidate !== $source_path ) {
				@unlink( $candidate );
			}
		}
	}
}

