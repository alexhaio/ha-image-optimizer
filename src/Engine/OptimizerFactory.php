<?php
/**
 * Optimizer Factory.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Engine;

use ha\ImageOptimizer\Common\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OptimizerFactory
 */
class OptimizerFactory {

	/**
	 * Cached driver instance.
	 *
	 * @var OptimizerInterface|null
	 */
	private static ?OptimizerInterface $instance = null;

	/**
	 * Get the best available optimizer driver.
	 *
	 * @return OptimizerInterface
	 * @throws \RuntimeException If neither Imagick nor GD is available.
	 */
	public static function create(): OptimizerInterface {
		if ( null !== self::$instance ) {
			return self::$instance;
		}

		$imagick = new ImagickDriver();
		if ( $imagick->is_available() ) {
			self::$instance = $imagick;
			return self::$instance;
		}

		$gd = new GdDriver();
		if ( $gd->is_available() ) {
			self::$instance = $gd;
			return self::$instance;
		}

		throw new \RuntimeException(
			esc_html__( 'Weder ImageMagick (Imagick) noch die GD-Bibliothek sind auf diesem Server verfügbar.', 'ha-image-optimizer' )
		);
	}

	/**
	 * Explicitly set driver instance (for unit testing or custom injection).
	 *
	 * @param OptimizerInterface|null $driver Driver instance.
	 * @return void
	 */
	public static function set_driver( ?OptimizerInterface $driver ): void {
		self::$instance = $driver;
	}

	/**
	 * Reset cached driver instance (for unit testing or switching).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance = null;
	}
}
