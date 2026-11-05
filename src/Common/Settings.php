<?php
/**
 * Settings Repository for HA Image Optimizer.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Common;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 */
class Settings {

	/**
	 * WordPress Option Name.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'ha_io_settings';

	/**
	 * Get default settings values.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return [
			'compression_original'         => CompressionLevel::LOSSLESS->value,
			'compression_sizes'            => CompressionLevel::SMART->value,
			'generate_webp'                => true,
			'generate_avif'                => Environment::can_generate_avif(),
			'backup_originals'             => true,
			'delivery_mode'                => DeliveryMode::CONTENT_FILTER->value,
			'enable_full_buffer'           => false,
			'resize_large_images'          => true,
			'max_width'                    => 2560,
			'max_height'                   => 2560,
			'convert_non_transparent_png'  => false,
			'keep_exif'                    => false,
			'optimize_custom_folders'      => true,
			'custom_folders'               => 'themes',
			'auditor_fast_mode'            => true,
			'auditor_generic_db_scan'      => true,
			'auditor_safety_buffer_days'   => 0,
			'auditor_custom_tables'        => '',
		];
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		$saved = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}

		return wp_parse_args( $saved, self::get_defaults() );
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Optional fallback default.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$all = self::get_all();
		return $all[ $key ] ?? $default;
	}

	/**
	 * Save updated settings with sanitization.
	 *
	 * @param array<string, mixed> $input Raw input array.
	 * @return bool
	 */
	public static function update( array $input ): bool {
		$sanitized = self::sanitize( $input );
		return update_option( self::OPTION_NAME, $sanitized );
	}

	/**
	 * Sanitize all setting fields safely.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input ): array {
		$defaults = self::get_defaults();
		$clean    = [];

		// Compression Original
		$orig_level = sanitize_key( $input['compression_original'] ?? $defaults['compression_original'] );
		$clean['compression_original'] = in_array( $orig_level, [ 'lossless', 'smart', 'ultra' ], true )
			? $orig_level
			: $defaults['compression_original'];

		// Compression Sizes
		$sizes_level = sanitize_key( $input['compression_sizes'] ?? $defaults['compression_sizes'] );
		$clean['compression_sizes'] = in_array( $sizes_level, [ 'lossless', 'smart', 'ultra' ], true )
			? $sizes_level
			: $defaults['compression_sizes'];

		// Booleans
		$clean['generate_webp']               = ! empty( $input['generate_webp'] );
		$clean['generate_avif']               = ! empty( $input['generate_avif'] );
		$clean['backup_originals']            = ! empty( $input['backup_originals'] );
		$clean['enable_full_buffer']          = ! empty( $input['enable_full_buffer'] );
		$clean['resize_large_images']         = ! empty( $input['resize_large_images'] );
		$clean['convert_non_transparent_png'] = ! empty( $input['convert_non_transparent_png'] );
		$clean['keep_exif']                   = ! empty( $input['keep_exif'] );

		// Delivery Mode
		$delivery = sanitize_key( $input['delivery_mode'] ?? $defaults['delivery_mode'] );
		$clean['delivery_mode'] = in_array( $delivery, [ 'content_filter', 'htaccess', 'off' ], true )
			? $delivery
			: $defaults['delivery_mode'];

		// Max dimensions
		$max_w = isset( $input['max_width'] ) ? absint( $input['max_width'] ) : $defaults['max_width'];
		$clean['max_width'] = ( $max_w >= 300 && $max_w <= 10000 ) ? $max_w : 2560;

		$max_h = isset( $input['max_height'] ) ? absint( $input['max_height'] ) : $defaults['max_height'];
		$clean['max_height'] = ( $max_h >= 300 && $max_h <= 10000 ) ? $max_h : 2560;

		// Custom Folders
		$clean['optimize_custom_folders'] = ! empty( $input['optimize_custom_folders'] );
		$raw_folders                      = (string) ( $input['custom_folders'] ?? $defaults['custom_folders'] );
		$clean_folders                    = [];
		$lines                            = explode( "\n", str_replace( "\r", '', $raw_folders ) );

		foreach ( $lines as $line ) {
			$folder = trim( str_replace( '\\', '/', $line ) );
			$folder = trim( $folder, '/' );

			// Security: skip empty lines or directory traversal attempts
			if ( empty( $folder ) || str_contains( $folder, '..' ) ) {
				continue;
			}

			// Disallow protected internal paths
			if ( str_starts_with( $folder, 'ha-backups' ) ) {
				continue;
			}

			$clean_folders[] = sanitize_text_field( $folder );
		}

		$clean['custom_folders'] = implode( "\n", array_unique( $clean_folders ) );

		// Auditor Settings
		$clean['auditor_fast_mode']       = ! empty( $input['auditor_fast_mode'] );
		$clean['auditor_generic_db_scan'] = ! empty( $input['auditor_generic_db_scan'] );

		$safety_days = isset( $input['auditor_safety_buffer_days'] ) ? absint( $input['auditor_safety_buffer_days'] ) : $defaults['auditor_safety_buffer_days'];
		$clean['auditor_safety_buffer_days'] = ( $safety_days >= 0 && $safety_days <= 365 ) ? $safety_days : 0;

		$raw_tbls    = (string) ( $input['auditor_custom_tables'] ?? '' );
		$clean_tbls  = [];
		$tbl_lines   = explode( "\n", str_replace( "\r", '', $raw_tbls ) );
		foreach ( $tbl_lines as $tline ) {
			$tbl = sanitize_key( trim( $tline ) );
			if ( ! empty( $tbl ) ) {
				$clean_tbls[] = $tbl;
			}
		}
		$clean['auditor_custom_tables'] = implode( "\n", array_unique( $clean_tbls ) );

		return $clean;
	}
}

