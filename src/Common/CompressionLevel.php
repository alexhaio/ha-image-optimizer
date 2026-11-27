<?php
/**
 * Compression Level Enum.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Common;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enum CompressionLevel
 */
enum CompressionLevel: string {
	case LOSSLESS = 'lossless';
	case SMART    = 'smart';
	case ULTRA    = 'ultra';

	/**
	 * Map a user-facing level string or alias to a CompressionLevel enum.
	 * Supports 'lossless', 'smart', 'ultra', and aliases 'niedrig', 'mittel', 'hoch', 'low', 'medium', 'high'.
	 *
	 * @param string|null $level Raw level string.
	 * @return self|null Matching enum instance, or null if empty / unknown.
	 */
	public static function fromString( ?string $level ): ?self {
		if ( null === $level || '' === trim( $level ) ) {
			return null;
		}

		$normalized = strtolower( trim( $level ) );

		return match ( $normalized ) {
			'lossless', 'niedrig', 'low' => self::LOSSLESS,
			'smart', 'mittel', 'medium'  => self::SMART,
			'ultra', 'hoch', 'high'      => self::ULTRA,
			default                      => self::tryFrom( $normalized ),
		};
	}

	/**
	 * Get human-readable label.
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::LOSSLESS => __( 'Lossless (100% sharpness)', 'ha-image-optimizer' ),
			self::SMART    => __( 'Smart (Balanced - approx. 82% quality)', 'ha-image-optimizer' ),
			self::ULTRA    => __( 'Ultra (Aggressive - approx. 72% quality)', 'ha-image-optimizer' ),
		};
	}

	/**
	 * Get detailed label for selection dropdowns.
	 *
	 * @return string
	 */
	public function selection_label(): string {
		return match ( $this ) {
			self::ULTRA    => __( 'High (Maximum - approx. 72% quality)', 'ha-image-optimizer' ),
			self::SMART    => __( 'Medium (Balanced - approx. 82% quality)', 'ha-image-optimizer' ),
			self::LOSSLESS => __( 'Low (Lossless - 100% sharpness)', 'ha-image-optimizer' ),
		};
	}

	/**
	 * Get short badge label.
	 *
	 * @return string
	 */
	public function badge_label(): string {
		return match ( $this ) {
			self::ULTRA    => __( 'High (Ultra)', 'ha-image-optimizer' ),
			self::SMART    => __( 'Medium (Smart)', 'ha-image-optimizer' ),
			self::LOSSLESS => __( 'Low (Lossless)', 'ha-image-optimizer' ),
		};
	}

	/**
	 * Get JPEG/WebP/AVIF quality value (0-100).
	 *
	 * @param string $mime_type MIME type of the image.
	 * @return int
	 */
	public function quality( string $mime_type = 'image/jpeg' ): int {
		return match ( $this ) {
			self::LOSSLESS => 100,
			self::SMART    => match ( $mime_type ) {
				'image/avif' => 75, // AVIF is denser, 75 achieves exceptional fidelity
				default      => 82,
			},
			self::ULTRA    => match ( $mime_type ) {
				'image/avif' => 65,
				default      => 72,
			},
		};
	}
}
