<?php
/**
 * Environment Diagnostic and Capability Checker.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Common;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Environment
 */
class Environment {

	/**
	 * Cached capabilities.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $capabilities = null;

	/**
	 * Get full system diagnostic capabilities.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_capabilities(): array {
		if ( null !== self::$capabilities ) {
			return self::$capabilities;
		}

		$php_version = PHP_VERSION;
		$php_ok      = version_compare( $php_version, '8.1.0', '>=' );

		// Imagick Check
		$imagick_loaded = extension_loaded( 'imagick' ) && class_exists( '\Imagick' );
		$imagick_webp   = false;
		$imagick_avif   = false;
		$imagick_version= '';

		if ( $imagick_loaded ) {
			try {
				$formats          = \Imagick::queryFormats();
				$imagick_webp     = in_array( 'WEBP', $formats, true );
				$imagick_avif     = in_array( 'AVIF', $formats, true );
				$v                = \Imagick::getVersion();
				$imagick_version  = $v['versionString'] ?? 'Unknown';
			} catch ( \Throwable $e ) {
				$imagick_loaded = false;
			}
		}

		// GD Check
		$gd_loaded = extension_loaded( 'gd' ) && function_exists( 'gd_info' );
		$gd_webp   = false;
		$gd_avif   = false;
		$gd_info   = [];

		if ( $gd_loaded ) {
			$gd_info = gd_info();
			$gd_webp = ! empty( $gd_info['WebP Support'] ) && function_exists( 'imagewebp' );
			$gd_avif = ! empty( $gd_info['AVIF Support'] ) && function_exists( 'imageavif' );
		}

		// Server Software
		$server_software = isset( $_SERVER['SERVER_SOFTWARE'] )
			? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) )
			: '';
		$is_apache       = stripos( $server_software, 'apache' ) !== false;
		$is_litespeed    = stripos( $server_software, 'litespeed' ) !== false;
		$is_nginx        = stripos( $server_software, 'nginx' ) !== false;

		// Memory Limit
		$memory_limit_raw = (string) ( ini_get( 'memory_limit' ) ?: '256M' );
		$memory_bytes     = function_exists( 'wp_convert_hr_to_bytes' )
			? wp_convert_hr_to_bytes( $memory_limit_raw )
			: self::convert_hr_to_bytes( $memory_limit_raw );
		$memory_ok        = ( -1 === $memory_bytes || $memory_bytes >= 128 * 1024 * 1024 );

		// Uploads directory writeability
		$upload_dir = wp_upload_dir();
		$can_write  = ! empty( $upload_dir['basedir'] ) && wp_is_writable( $upload_dir['basedir'] );

		// Best available driver
		$best_driver = 'none';
		if ( $imagick_loaded ) {
			$best_driver = 'imagick';
		} elseif ( $gd_loaded ) {
			$best_driver = 'gd';
		}

		self::$capabilities = [
			'php'             => [
				'version'    => $php_version,
				'compatible' => $php_ok,
			],
			'imagick'         => [
				'installed' => $imagick_loaded,
				'version'   => $imagick_version,
				'webp'      => $imagick_webp,
				'avif'      => $imagick_avif,
			],
			'gd'              => [
				'installed' => $gd_loaded,
				'version'   => $gd_info['GD Version'] ?? '',
				'webp'      => $gd_webp,
				'avif'      => $gd_avif,
			],
			'server'          => [
				'software'    => $server_software,
				'is_apache'   => $is_apache,
				'is_litespeed'=> $is_litespeed,
				'is_nginx'    => $is_nginx,
				'supports_htaccess' => ( $is_apache || $is_litespeed ),
			],
			'memory'          => [
				'limit_string' => $memory_limit_raw,
				'bytes'        => $memory_bytes,
				'is_sufficient'=> $memory_ok,
			],
			'uploads'         => [
				'basedir'  => $upload_dir['basedir'],
				'writable' => $can_write,
			],
			'best_driver'     => $best_driver,
			'can_optimize'    => ( 'none' !== $best_driver && $can_write ),
			'supports_webp'   => ( $imagick_webp || $gd_webp ),
			'supports_avif'   => ( $imagick_avif || $gd_avif ),
		];

		return self::$capabilities;
	}

	/**
	 * Reset cached capabilities (e.g. for testing).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$capabilities = null;
	}

	/**
	 * Can the current environment generate WebP images?
	 *
	 * @return bool
	 */
	public static function can_generate_webp(): bool {
		$caps = self::get_capabilities();
		return (bool) $caps['supports_webp'];
	}

	/**
	 * Can the current environment generate AVIF images?
	 *
	 * @return bool
	 */
	public static function can_generate_avif(): bool {
		$caps = self::get_capabilities();
		return (bool) $caps['supports_avif'];
	}

	/**
	 * Does server support Apache .htaccess rewrite rules?
	 *
	 * @return bool
	 */
	public static function supports_htaccess(): bool {
		$caps = self::get_capabilities();
		return (bool) $caps['server']['supports_htaccess'];
	}

	/**
	 * Convert shorthand byte string to integer bytes.
	 *
	 * @param string $value Shorthand string (e.g. '256M', '1G').
	 * @return int Byte count.
	 */
	public static function convert_hr_to_bytes( string $value ): int {
		$value = trim( $value );
		if ( '-1' === $value || -1 === (int) $value ) {
			return -1;
		}

		$last = strtolower( $value[ strlen( $value ) - 1 ] );
		$val  = (int) $value;

		switch ( $last ) {
			case 'g':
				$val *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$val *= 1024 * 1024;
				break;
			case 'k':
				$val *= 1024;
				break;
		}

		return $val;
	}
}

