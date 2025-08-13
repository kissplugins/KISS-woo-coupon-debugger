<?php
/**
 * Plugin Name: KISS Woo Coupon Debugger
 * Plugin URI:  https://github.com/kissplugins/KISS-woo-coupon-debugger
 * Description: A companion plugin for WooCommerce Smart Coupons to debug coupon application and hook/filter processing.
 * Version:     2.0.0
 * Author:      KISS Plugins
 * Author URI:  https://kissplugins.com
 * License:     GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wc-sc-debugger
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.0
 * Tested up to: 6.3
 * WC requires at least: 4.0
 * WC tested up to: 8.0
 *
 * @package KissPlugins\WooCouponDebugger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants
define( 'WC_SC_DEBUGGER_VERSION', '2.0.0' );
define( 'WC_SC_DEBUGGER_PLUGIN_FILE', __FILE__ );
define( 'WC_SC_DEBUGGER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_SC_DEBUGGER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load the autoloader
require_once WC_SC_DEBUGGER_PLUGIN_DIR . 'src/Autoloader.php';

use KissPlugins\WooCouponDebugger\Autoloader;
use KissPlugins\WooCouponDebugger\Container\Container;
use KissPlugins\WooCouponDebugger\Core\DebuggerCore;
use KissPlugins\WooCouponDebugger\Core\Logger;
use KissPlugins\WooCouponDebugger\Hooks\HookTracker;
use KissPlugins\WooCouponDebugger\Cart\CartSimulator;
use KissPlugins\WooCouponDebugger\Admin\AdminInterface;
use KissPlugins\WooCouponDebugger\Ajax\AjaxHandler;
use KissPlugins\WooCouponDebugger\Interfaces\LoggerInterface;
use KissPlugins\WooCouponDebugger\Interfaces\DebuggerInterface;
use KissPlugins\WooCouponDebugger\Interfaces\HookTrackerInterface;
use KissPlugins\WooCouponDebugger\Interfaces\CartSimulatorInterface;
use KissPlugins\WooCouponDebugger\Interfaces\ContainerInterface;

// Register autoloader
Autoloader::register();
Autoloader::addNamespace( 'KissPlugins\\WooCouponDebugger\\', WC_SC_DEBUGGER_PLUGIN_DIR . 'src/' );

/**
 * Main plugin class using new architecture
 */
if ( ! class_exists( 'WC_SC_Debugger' ) ) {

class WC_SC_Debugger {

	/**
	 * Singleton instance of the plugin.
	 *
	 * @var WC_SC_Debugger|null
	 */
	private static $instance = null;

	/**
	 * Dependency injection container
	 *
	 * @var ContainerInterface
	 */
	private $container;

	/**
	 * Admin interface instance
	 *
	 * @var AdminInterface
	 */
	private $adminInterface;

	/**
	 * AJAX handler instance
	 *
	 * @var AjaxHandler
	 */
	private $ajaxHandler;

	/**
	 * Get the singleton instance of the plugin.
	 *
	 * @return WC_SC_Debugger
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->setupContainer();
		$this->initializeComponents();
		$this->setupHooks();
	}

	/**
	 * Setup dependency injection container
	 *
	 * @return void
	 */
	private function setupContainer(): void {
		$this->container = new Container();

		// Bind interfaces to implementations
		$this->container->singleton( LoggerInterface::class, Logger::class );
		$this->container->singleton( HookTrackerInterface::class, function( $container ) {
			return new HookTracker( $container->get( LoggerInterface::class ) );
		});
		$this->container->singleton( CartSimulatorInterface::class, function( $container ) {
			return new CartSimulator( $container->get( LoggerInterface::class ) );
		});
		$this->container->singleton( DebuggerInterface::class, function( $container ) {
			return new DebuggerCore(
				$container->get( LoggerInterface::class ),
				$container->get( HookTrackerInterface::class ),
				$container->get( CartSimulatorInterface::class )
			);
		});

		// Bind concrete classes
		$this->container->singleton( AdminInterface::class, function() {
			return new AdminInterface( WC_SC_DEBUGGER_VERSION );
		});
		$this->container->singleton( AjaxHandler::class, function( $container ) {
			return new AjaxHandler( $container->get( DebuggerInterface::class ) );
		});
	}

	/**
	 * Initialize plugin components
	 *
	 * @return void
	 */
	private function initializeComponents(): void {
		$this->adminInterface = $this->container->get( AdminInterface::class );
		$this->ajaxHandler = $this->container->get( AjaxHandler::class );
	}

	/**
	 * Setup WordPress hooks
	 *
	 * @return void
	 */
	private function setupHooks(): void {
		// Initialize admin interface
		$this->adminInterface->init();

		// Initialize AJAX handler
		$this->ajaxHandler->init();

		// Ensure WooCommerce session is initialized for AJAX requests
		add_action( 'woocommerce_init', array( $this, 'maybe_init_session' ) );
	}

	/**
	 * Initialize WooCommerce session if needed
	 *
	 * @return void
	 */
	public function maybe_init_session(): void {
		if ( wp_doing_ajax() && WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
	}

	/**
	 * Get the dependency injection container
	 *
	 * @return ContainerInterface
	 */
	public function getContainer(): ContainerInterface {
		return $this->container;
	}

	/**
	 * Get a service from the container
	 *
	 * @param string $service Service identifier
	 * @return mixed The service instance
	 */
	public function get( string $service ) {
		return $this->container->get( $service );
	}
}

} // End if class_exists check

// Set up global error handler for Smart Coupons compatibility
add_action( 'init', function() {
	if ( class_exists( 'WC_Smart_Coupons' ) && version_compare( PHP_VERSION, '8.0.0', '>=' ) ) {
		// Track if we've shown the Smart Coupons notice
		static $smart_coupons_notice_shown = false;

		// Set up error handler to suppress Smart Coupons PHP 8+ errors
		set_error_handler( function( $severity, $message, $file, $line ) use ( &$smart_coupons_notice_shown ) {
			// Suppress Smart Coupons PHP 8+ compatibility errors
			if ( strpos( $file, 'woocommerce-smart-coupons' ) !== false &&
				 ( strpos( $message, 'Cannot access offset of type string on string' ) !== false ||
				   strpos( $message, 'Trying to access array offset on value of type string' ) !== false ) ) {

				// Log the suppressed error for debugging
				error_log( sprintf(
					'WC SC Debugger: Suppressed Smart Coupons PHP 8+ error: %s in %s on line %d',
					$message,
					basename( $file ),
					$line
				) );

				// Show admin notice once about Smart Coupons compatibility
				if ( ! $smart_coupons_notice_shown && is_admin() ) {
					$smart_coupons_notice_shown = true;
					add_action( 'admin_notices', function() {
						echo '<div class="notice notice-warning is-dismissible">';
						echo '<p><strong>Smart Coupons Compatibility Notice:</strong> ';
						echo 'PHP 8+ compatibility issues detected with WooCommerce Smart Coupons. ';
						echo 'Errors are being suppressed to prevent disruption, but you may want to ';
						echo '<a href="https://wordpress.org/support/plugin/woocommerce-smart-coupons/" target="_blank">contact Smart Coupons support</a> ';
						echo 'for an updated version compatible with PHP ' . PHP_VERSION . '.';
						echo '</p></div>';
					});
				}

				return true; // Suppress the error
			}

			// Let other errors pass through
			return false;
		}, E_WARNING | E_NOTICE | E_STRICT | E_DEPRECATED );
	}
}, 5 );

// Initialize the plugin when WooCommerce is loaded
add_action( 'plugins_loaded', function() {
	if ( class_exists( 'WooCommerce' ) ) {
		try {
			WC_SC_Debugger::get_instance();
		} catch ( Exception $e ) {
			error_log( 'WC SC Debugger initialization error: ' . $e->getMessage() );
			add_action( 'admin_notices', function() use ( $e ) {
				echo '<div class="notice notice-error"><p><strong>KISS Woo Coupon Debugger:</strong> ' . esc_html( $e->getMessage() ) . '</p></div>';
			});
		}
	} else {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-warning"><p><strong>KISS Woo Coupon Debugger:</strong> WooCommerce is required for this plugin to work.</p></div>';
		});
	}
}, 11 );

/**
 * Activation hook
 */
register_activation_hook( WC_SC_DEBUGGER_PLUGIN_FILE, 'wc_sc_debugger_activate' );
function wc_sc_debugger_activate() {
	// Activation logic can go here
}

/**
 * Deactivation hook
 */
register_deactivation_hook( WC_SC_DEBUGGER_PLUGIN_FILE, 'wc_sc_debugger_deactivate' );
function wc_sc_debugger_deactivate() {
	// Clean up dummy product
	$product_id = get_option( 'wc_sc_debugger_dummy_product_id' );
	if ( $product_id ) {
		wp_delete_post( $product_id, true );
	}
	delete_option( 'wc_sc_debugger_dummy_product_id' );
}
