<?php
/**
 * Plugin Name:       HA Image Optimizer
 * Plugin URI:        https://github.com/mystryx1337/ha-image-optimizer
 * Description:       Leistungsstarke, rein lokale Bildoptimierung für WordPress ohne externe APIs. Generiert WebP und AVIF, verwaltet Backups und bietet Vorher-Nachher-Statistiken.
 * Version:           1.3.5
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Alexander Hacker
 * Author URI:        https://seo-hacker.de/
 * License:           GPL-3.0
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       ha-image-optimizer
 * Domain Path:       /languages
 *
 * GitHub Plugin URI: mystryx1337/ha-image-optimizer
 * Primary Branch:    main
 *
 * @package           HA_Image_Optimizer
 */

namespace ha\ImageOptimizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -----------------------------------------------------------------------------
// 1. Plugin-Konstanten
// -----------------------------------------------------------------------------
define( 'HA_IO_VERSION', '1.3.5' );
define( 'HA_IO_PATH', plugin_dir_path( __FILE__ ) );
define( 'HA_IO_URL', plugin_dir_url( __FILE__ ) );
define( 'HA_IO_BASENAME', plugin_basename( __FILE__ ) );

// -----------------------------------------------------------------------------
// 2. Automatischer GitHub-Updater (Private & Public Repo Support via PUC v5)
// -----------------------------------------------------------------------------
/**
 * Initialisiert den Plugin Update Checker für automatisierte Updates via GitHub.
 *
 * @return \YahnisElsts\PluginUpdateChecker\v5\Vcs\PluginUpdateChecker|null
 */
function ha_io_get_updater() {
	static $update_checker = null;
	if ( null !== $update_checker ) {
		return $update_checker;
	}

	$puc_file = HA_IO_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';
	if ( ! file_exists( $puc_file ) ) {
		return null;
	}

	require_once $puc_file;

	$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/mystryx1337/ha-image-optimizer/',
		__FILE__,
		'ha-image-optimizer'
	);

	$update_checker->setBranch( 'main' );
	$update_checker->getVcsApi()->enableReleaseAssets();

	$token = defined( 'HA_GITHUB_UPDATE_TOKEN' ) ? HA_GITHUB_UPDATE_TOKEN : '';
	if ( empty( $token ) && defined( 'GITHUB_ACCESS_TOKEN' ) ) {
		$token = GITHUB_ACCESS_TOKEN;
	}
	$token = apply_filters( 'ha_github_update_token', $token, 'ha-image-optimizer' );

	if ( ! empty( $token ) ) {
		$update_checker->setAuthentication( $token );
	}

	return $update_checker;
}
ha_io_get_updater();

// -----------------------------------------------------------------------------
// 3. PSR-4 Autoloader initialisieren
// -----------------------------------------------------------------------------
require_once HA_IO_PATH . 'src/Autoloader.php';
Autoloader::register( HA_IO_PATH . 'src' );

// -----------------------------------------------------------------------------
// 4. Aktivierungs- und Deaktivierungshooks
// -----------------------------------------------------------------------------
register_activation_hook( __FILE__, [ __NAMESPACE__ . '\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ __NAMESPACE__ . '\\Plugin', 'deactivate' ] );

// -----------------------------------------------------------------------------
// 5. Plugin starten
// -----------------------------------------------------------------------------
Plugin::get_instance();
