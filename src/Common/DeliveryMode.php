<?php
/**
 * Delivery Mode Enum.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Common;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enum DeliveryMode
 */
enum DeliveryMode: string {
	case CONTENT_FILTER = 'content_filter';
	case HTACCESS       = 'htaccess';
	case OFF            = 'off';

	/**
	 * Get human-readable label.
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::CONTENT_FILTER => __( '<picture> tag replacement (Universal & CDN-safe)', 'ha-image-optimizer' ),
			self::HTACCESS       => __( '.htaccess Server Rewrite (0 ms PHP overhead, Apache & LiteSpeed)', 'ha-image-optimizer' ),
			self::OFF            => __( 'Disabled (images are not replaced automatically)', 'ha-image-optimizer' ),
		};
	}
}
