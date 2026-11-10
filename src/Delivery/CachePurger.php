<?php
/**
 * Cache Purger for third-party caching plugins.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Delivery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CachePurger
 */
class CachePurger {

	/**
	 * Whether cache purging is temporarily suspended (e.g. during bulk processing).
	 *
	 * @var bool
	 */
	private static bool $suspended = false;

	/**
	 * Suspend cache purging during batch processing.
	 *
	 * @return void
	 */
	public static function suspend(): void {
		self::$suspended = true;
	}

	/**
	 * Resume cache purging.
	 *
	 * @return void
	 */
	public static function resume(): void {
		self::$suspended = false;
	}

	/**
	 * Check if cache purging is suspended.
	 *
	 * @return bool
	 */
	public static function is_suspended(): bool {
		return self::$suspended;
	}

	/**
	 * Register purge hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'ha_io_attachment_optimized', [ __CLASS__, 'purge_all_caches' ] );
	}

	/**
	 * Purge all known WordPress caching layers.
	 *
	 * @return void
	 */
	public static function purge_all_caches(): void {
		if ( self::$suspended ) {
			return;
		}
		// 1. WP Rocket
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		// 2. LiteSpeed Cache
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
		}

		// 3. W3 Total Cache
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		// 4. WP Super Cache
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		// 5. Autoptimize
		if ( class_exists( '\autoptimizeCache' ) && method_exists( '\autoptimizeCache', 'clearall' ) ) {
			\autoptimizeCache::clearall();
		}

		// 6. Nginx Helper
		if ( has_action( 'rt_nginx_helper_purge_all' ) ) {
			do_action( 'rt_nginx_helper_purge_all' );
		}
	}
}
