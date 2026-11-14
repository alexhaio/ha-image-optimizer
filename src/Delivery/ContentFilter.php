<?php
/**
 * Content Filter for Picture Tags and CSS image-set.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Delivery;

use ha\ImageOptimizer\Common\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ContentFilter
 */
class ContentFilter {

	/**
	 * Register content filters.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'the_content', [ __CLASS__, 'filter_html' ], 999 );
		add_filter( 'post_thumbnail_html', [ __CLASS__, 'filter_html' ], 999 );
		add_filter( 'wp_get_attachment_image', [ __CLASS__, 'filter_html' ], 999 );
		add_filter( 'widget_text', [ __CLASS__, 'filter_html' ], 999 );
	}

	/**
	 * Filter raw HTML content and replace <img> with <picture>.
	 *
	 * @param string $content HTML content.
	 * @return string Modified HTML.
	 */
	public static function filter_html( string $content ): string {
		if ( empty( $content ) || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $content;
		}

		// Protect tags whose inner text must never be rewritten as HTML attributes/styles
		$preserved_blocks = [];
		$content          = (string) preg_replace_callback(
			'/<(script|noscript|style|template|textarea)\b[^>]*>.*?<\/\1>/is',
			static function ( array $matches ) use ( &$preserved_blocks ): string {
				$key                      = '<!--HA_IO_PRESERVED_' . count( $preserved_blocks ) . '-->';
				$preserved_blocks[ $key ] = $matches[0];
				return $key;
			},
			$content
		);

		// 1. Process <img> tags (skip <img> already wrapped inside a <picture> element)
		$content = (string) preg_replace_callback(
			'/<picture\b[^>]*>.*?<\/picture>(*SKIP)(*F)|<img\b([^>]*?)>/is',
			[ __CLASS__, 'replace_img_tag' ],
			$content
		);

		// 2. Process inline background-image styles (supports single quotes inside style="..." and vice versa)
		$content = (string) preg_replace_callback(
			'/\bstyle\s*=\s*(["\'])((?:(?!\1).)*?background(?:-image)?\s*:\s*url\(\s*(?:&quot;|&#0?39;|[\'"])?([^\'"\)]+?)(?:&quot;|&#0?39;|[\'"])?\s*\)(?:(?!\1).)*?)\1/is',
			[ __CLASS__, 'replace_inline_style' ],
			$content
		);

		if ( ! empty( $preserved_blocks ) ) {
			$content = strtr( $content, $preserved_blocks );
		}

		return $content;
	}

	/**
	 * Replace a single <img> tag with a <picture> element if WebP/AVIF exists.
	 *
	 * @param array<int, string> $matches Regex matches.
	 * @return string Replacement HTML.
	 */
	public static function replace_img_tag( array $matches ): string {
		$img_tag    = $matches[0];
		$attributes = $matches[1];

		// Skip if already inside a picture tag, or marked to skip
		if ( stripos( $attributes, 'no-webp' ) !== false || stripos( $attributes, 'data-no-webp' ) !== false ) {
			return $img_tag;
		}

		// Extract src
		if ( ! preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $attributes, $src_matches ) ) {
			return $img_tag;
		}

		$src = $src_matches[1];

		// Calculate local disk path (supports uploads and custom theme folders)
		$local_path = self::resolve_local_path( $src );
		if ( ! $local_path || ! file_exists( $local_path ) ) {
			return $img_tag;
		}

		$has_avif = file_exists( $local_path . '.avif' );
		$has_webp = file_exists( $local_path . '.webp' );

		if ( ! $has_avif && ! $has_webp ) {
			return $img_tag;
		}

		// Extract srcset if present
		$srcset = '';
		if ( preg_match( '/\bsrcset\s*=\s*["\']([^"\']+)["\']/i', $attributes, $srcset_matches ) ) {
			$srcset = $srcset_matches[1];
		}

		// Extract sizes if present
		$sizes_attr = '';
		if ( preg_match( '/\bsizes\s*=\s*["\']([^"\']+)["\']/i', $attributes, $sizes_matches ) ) {
			$sizes_attr = ' sizes="' . esc_attr( $sizes_matches[1] ) . '"';
		}

		$picture = "<picture>\n";

		// AVIF source
		if ( $has_avif ) {
			$avif_srcset = self::convert_srcset( $srcset, '.avif' );
			if ( empty( $avif_srcset ) ) {
				$avif_srcset = esc_url( self::append_nextgen_ext( $src, '.avif' ) );
			}
			$picture .= "\t<source type=\"image/avif\" srcset=\"{$avif_srcset}\"{$sizes_attr}>\n";
		}

		// WebP source
		if ( $has_webp ) {
			$webp_srcset = self::convert_srcset( $srcset, '.webp' );
			if ( empty( $webp_srcset ) ) {
				$webp_srcset = esc_url( self::append_nextgen_ext( $src, '.webp' ) );
			}
			$picture .= "\t<source type=\"image/webp\" srcset=\"{$webp_srcset}\"{$sizes_attr}>\n";
		}

		$picture .= "\t{$img_tag}\n</picture>";

		return $picture;
	}

	/**
	 * Map a site URL to its absolute local file path on disk.
	 * Supports uploads and custom wp-content folders, query strings, URL encoding, and relative/protocol-relative URLs.
	 *
	 * @param string $url URL to check.
	 * @return string|null Absolute file path or null if external.
	 */
	public static function resolve_local_path( string $url ): ?string {
		$url = trim( $url );
		if ( '' === $url || str_starts_with( $url, 'data:' ) ) {
			return null;
		}

		$parsed_url = parse_url( $url );
		if ( false === $parsed_url || empty( $parsed_url['path'] ) ) {
			return null;
		}

		$url_path = rawurldecode( (string) $parsed_url['path'] );
		if ( str_contains( $url_path, '..' ) ) {
			return null;
		}

		$url_host = strtolower( (string) ( $parsed_url['host'] ?? '' ) );

		$upload_dir      = wp_upload_dir();
		$upload_url      = (string) $upload_dir['baseurl'];
		$upload_dir_path = (string) $upload_dir['basedir'];
		$upload_parsed   = parse_url( $upload_url );
		$upload_host     = strtolower( (string) ( $upload_parsed['host'] ?? '' ) );
		$upload_base_p   = (string) ( $upload_parsed['path'] ?? '' );

		if ( '' !== $upload_base_p && ( '' === $url_host || $url_host === $upload_host ) && str_starts_with( $url_path, $upload_base_p ) ) {
			$rel = substr( $url_path, strlen( $upload_base_p ) );
			return rtrim( $upload_dir_path, '/\\' ) . '/' . ltrim( $rel, '/\\' );
		}

		$content_url    = content_url();
		$content_dir    = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
		$content_parsed = parse_url( $content_url );
		$content_host   = strtolower( (string) ( $content_parsed['host'] ?? '' ) );
		$content_base_p = (string) ( $content_parsed['path'] ?? '' );

		if ( '' !== $content_base_p && ( '' === $url_host || $url_host === $content_host ) && str_starts_with( $url_path, $content_base_p ) ) {
			$rel = substr( $url_path, strlen( $content_base_p ) );
			return rtrim( $content_dir, '/\\' ) . '/' . ltrim( $rel, '/\\' );
		}

		return null;
	}

	/**
	 * Append a Next-Gen extension (.webp / .avif) cleanly before any URL query string or fragment.
	 *
	 * @param string $url Original image URL.
	 * @param string $ext Extension suffix (e.g. '.webp' or '.avif').
	 * @return string URL with appended extension.
	 */
	private static function append_nextgen_ext( string $url, string $ext ): string {
		$suffix = '';
		$hash_pos = strpos( $url, '#' );
		if ( false !== $hash_pos ) {
			$suffix = substr( $url, $hash_pos );
			$url    = substr( $url, 0, $hash_pos );
		}
		$query_pos = strpos( $url, '?' );
		if ( false !== $query_pos ) {
			$suffix = substr( $url, $query_pos ) . $suffix;
			$url    = substr( $url, 0, $query_pos );
		}

		return $url . $ext . $suffix;
	}

	/**
	 * Convert an existing srcset string to use .webp or .avif suffixes for matching existing files.
	 * Omits candidates without a corresponding Next-Gen file to avoid MIME/type mismatches.
	 *
	 * @param string $srcset Original srcset.
	 * @param string $ext Extension suffix (e.g. '.webp' or '.avif').
	 * @return string Converted srcset.
	 */
	private static function convert_srcset( string $srcset, string $ext ): string {
		if ( empty( $srcset ) ) {
			return '';
		}

		$entries   = explode( ',', $srcset );
		$converted = [];

		foreach ( $entries as $entry ) {
			$entry = trim( $entry );
			if ( empty( $entry ) ) {
				continue;
			}

			$parts = preg_split( '/\s+/', $entry );
			if ( empty( $parts[0] ) ) {
				continue;
			}

			$url        = $parts[0];
			$descriptor = $parts[1] ?? '';

			$local_path = self::resolve_local_path( $url );
			if ( $local_path && file_exists( $local_path . $ext ) ) {
				$converted[] = esc_url( self::append_nextgen_ext( $url, $ext ) ) . ( $descriptor ? ' ' . $descriptor : '' );
			}
		}

		return implode( ', ', $converted );
	}

	/**
	 * Convert inline background-image to modern CSS image-set.
	 *
	 * @param array<int, string> $matches Matches.
	 * @return string Replacement style attribute.
	 */
	public static function replace_inline_style( array $matches ): string {
		$full_style = $matches[0];
		$quote      = isset( $matches[3] ) ? $matches[1] : '"';
		$style_body = isset( $matches[3] ) ? $matches[2] : ( $matches[1] ?? '' );
		$img_url    = isset( $matches[3] ) ? $matches[3] : ( $matches[2] ?? '' );

		// Idempotency guard: skip if already enhanced (e.g. the_content + BufferParser)
		if ( '' === $img_url || stripos( $style_body, 'image-set(' ) !== false ) {
			return $full_style;
		}

		$local_path = self::resolve_local_path( $img_url );
		if ( ! $local_path || ! file_exists( $local_path ) ) {
			return $full_style;
		}

		$has_webp = file_exists( $local_path . '.webp' );
		$has_avif = file_exists( $local_path . '.avif' );

		if ( ! $has_webp && ! $has_avif ) {
			return $full_style;
		}

		$webp_url = self::append_nextgen_ext( $img_url, '.webp' );
		$avif_url = self::append_nextgen_ext( $img_url, '.avif' );

		$sets = [];
		if ( $has_avif ) {
			$sets[] = "url('{$avif_url}') type('image/avif')";
		}
		if ( $has_webp ) {
			$sets[] = "url('{$webp_url}') type('image/webp')";
		}
		$path_part = (string) parse_url( $img_url, PHP_URL_PATH );
		$ext       = strtolower( pathinfo( $path_part, PATHINFO_EXTENSION ) );
		$orig_mime = match ( $ext ) {
			'png'   => 'image/png',
			'gif'   => 'image/gif',
			'webp'  => 'image/webp',
			'avif'  => 'image/avif',
			default => 'image/jpeg',
		};
		$sets[]    = "url('{$img_url}') type('{$orig_mime}')";

		$set_str = implode( ', ', $sets );

		// Append modern image-set rule
		$enhanced_style = rtrim( $style_body, '; ' ) . "; background-image: -webkit-image-set({$set_str}); background-image: image-set({$set_str});";

		return 'style=' . $quote . esc_attr( $enhanced_style ) . $quote;
	}
}

