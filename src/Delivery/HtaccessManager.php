<?php
/**
 * .htaccess Rewrite Rules Manager for Apache & LiteSpeed.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Delivery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HtaccessManager
 */
class HtaccessManager {

	public const MARKER_START = '# BEGIN HA_IMAGE_OPTIMIZER';
	public const MARKER_END   = '# END HA_IMAGE_OPTIMIZER';

	/**
	 * Get the target .htaccess path in wp-content/uploads/.
	 *
	 * @return string
	 */
	public static function get_htaccess_path(): string {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . '.htaccess';
	}

	/**
	 * Generate the rewrite rules string.
	 *
	 * @return string
	 */
	public static function get_rules(): string {
		$rules  = self::MARKER_START . "\n";
		$rules .= "<IfModule mod_rewrite.c>\n";
		$rules .= "RewriteEngine On\n";
		$rules .= "RewriteCond %{HTTP_ACCEPT} image/avif\n";
		$rules .= "RewriteCond %{REQUEST_FILENAME}.avif -f\n";
		$rules .= "RewriteRule ^(.+)\.(jpe?g|png|webp)$ $1.$2.avif [T=image/avif,E=accept:1,L]\n\n";

		$rules .= "RewriteCond %{HTTP_ACCEPT} image/webp\n";
		$rules .= "RewriteCond %{REQUEST_FILENAME}.webp -f\n";
		$rules .= "RewriteRule ^(.+)\.(jpe?g|png)$ $1.$2.webp [T=image/webp,E=accept:1,L]\n";
		$rules .= "</IfModule>\n\n";

		$rules .= "<IfModule mod_headers.c>\n";
		$rules .= "Header append Vary Accept env=REDIRECT_accept\n";
		$rules .= "</IfModule>\n\n";

		$rules .= "<IfModule mod_mime.c>\n";
		$rules .= "AddType image/webp .webp\n";
		$rules .= "AddType image/avif .avif\n";
		$rules .= "</IfModule>\n";
		$rules .= self::MARKER_END . "\n";

		return $rules;
	}

	/**
	 * Apply the rules to wp-content/uploads/.htaccess.
	 *
	 * @return bool True on success.
	 */
	public static function apply_rules(): bool {
		$path = self::get_htaccess_path();

		$current = file_exists( $path ) ? (string) file_get_contents( $path ) : '';

		// If already contains rules, remove existing block first to update cleanly
		if ( str_contains( $current, self::MARKER_START ) ) {
			$pattern = '/' . preg_quote( self::MARKER_START, '/' ) . '.*?' . preg_quote( self::MARKER_END, '/' ) . '\s*/s';
			$current = (string) preg_replace( $pattern, '', $current );
		}

		$new_content = self::get_rules() . "\n" . ltrim( $current );
		return (bool) @file_put_contents( $path, $new_content );
	}

	/**
	 * Remove the rules from .htaccess.
	 *
	 * @return bool True on success.
	 */
	public static function remove_rules(): bool {
		$path = self::get_htaccess_path();
		if ( ! file_exists( $path ) ) {
			return true;
		}

		$current = (string) file_get_contents( $path );
		if ( ! str_contains( $current, self::MARKER_START ) ) {
			return true;
		}

		$pattern = '/' . preg_quote( self::MARKER_START, '/' ) . '.*?' . preg_quote( self::MARKER_END, '/' ) . '\s*/s';
		$clean   = (string) preg_replace( $pattern, '', $current );

		if ( trim( $clean ) === '' ) {
			wp_delete_file( $path );
			return ! file_exists( $path );
		}

		return (bool) @file_put_contents( $path, $clean );
	}

	/**
	 * Check if rules are currently active in .htaccess.
	 *
	 * @return bool
	 */
	public static function are_rules_active(): bool {
		$path = self::get_htaccess_path();
		if ( ! file_exists( $path ) ) {
			return false;
		}

		$content = (string) file_get_contents( $path );
		return str_contains( $content, self::MARKER_START );
	}
}
