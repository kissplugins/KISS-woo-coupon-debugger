<?php
/**
 * Plugin Name: KISS Woo Coupon Debugger
 * Plugin URI:  https://github.com/kissplugins/KISS-woo-coupon-debugger
 * Description: A companion plugin for WooCommerce Smart Coupons to debug coupon application and hook/filter processing.
 * Version:     1.4.3
 * Author:      KISS Plugins
 * Author URI:  https://kissplugins.com
 * License:     GPL-2.0
 * Text Domain: wc-sc-debugger
 * Domain Path: /languages
 *
 * @package WC_SC_Debugger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_SC_Debugger' ) ) {

	/**
	 * Main class for the WC Smart Coupons Debugger plugin.
	 */
	class WC_SC_Debugger {

		/**
		 * Singleton instance of the plugin.
		 *
		 * @var WC_SC_Debugger|null
		 */
		private static $instance = null;

		/**
		 * The plugin version number.
		 *
		 * @var string
		 */
		const VERSION = '1.4.2';

		/**
		 * Array to store debugging messages.
		 *
		 * @var array
		 */
		public static $debug_messages = array();

		/**
		 * Maximum execution time for debugging operations
		 *
		 * @var int
		 */
		private $max_execution_time = 30;

		/**
		 * Track if hooks are currently being monitored to prevent recursion
		 *
		 * @var bool
		 */
		private $hooks_tracking_active = false;

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
			add_action( 'admin_menu', array( $this, 'add_admin_menu_page' ) );
			add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
			add_action( 'wp_ajax_wc_sc_debug_coupon', array( $this, 'handle_debug_coupon_ajax' ) );

			// Ensure WooCommerce session is initialized for AJAX requests
			add_action( 'woocommerce_init', array( $this, 'maybe_init_session' ) );
		}

		/**
		 * Initialize WooCommerce session if needed
		 */
		public function maybe_init_session() {
			if ( wp_doing_ajax() && ! WC()->session->has_session() ) {
				WC()->session->set_customer_session_cookie( true );
			}
		}

		/**
		 * Add admin menu page for the debugger.
		 */
		public function add_admin_menu_page() {
			add_submenu_page(
				'woocommerce',
				__( 'SC Debugger', 'wc-sc-debugger' ),
				__( 'SC Debugger', 'wc-sc-debugger' ),
				'manage_woocommerce',
				'wc-sc-debugger',
				array( $this, 'render_admin_page' )
			);

			// Add a simple self-test page for internal safeguards
			add_submenu_page(
				'woocommerce',
				__( 'SC Self Test', 'wc-sc-debugger' ),
				__( 'SC Self Test', 'wc-sc-debugger' ),
				'manage_woocommerce',
				'wc-sc-debugger-self-test',
				array( $this, 'render_self_test_page' )
			);
		}

		/**
		 * Add a separate settings page for product validation.
		 */
		public function add_settings_page() {
			add_submenu_page(
				'woocommerce',
				__( 'SC Debugger Settings', 'wc-sc-debugger' ),
				__( 'SC Debugger Settings', 'wc-sc-debugger' ),
				'manage_woocommerce',
				'wc-sc-debugger-settings',
				array( $this, 'render_settings_page' )
			);
		}

		/**
		 * Register plugin settings.
		 */
		public function register_settings() {
			register_setting(
				'wc_sc_debugger_options_group',
				'wc_sc_debugger_validated_products',
				array( $this, 'sanitize_validated_products' )
			);

			add_settings_section(
				'wc_sc_debugger_products_section',
				__( 'Pre-define Products for Testing', 'wc-sc-debugger' ),
				array( $this, 'products_section_callback' ),
				'wc-sc-debugger-settings'
			);

			for ( $i = 1; $i <= 3; $i++ ) {
				add_settings_field(
					'wc_sc_debugger_product_id_' . $i,
					sprintf( __( 'Product ID %d', 'wc-sc-debugger' ), $i ),
					array( $this, 'product_id_field_callback' ),
					'wc-sc-debugger-settings',
					'wc_sc_debugger_products_section',
					array(
						'label_for' => 'wc_sc_debugger_product_id_' . $i,
						'field_id'  => $i,
					)
				);
			}
		}

		/**
		 * Callback for the products section description.
		 */
		public function products_section_callback() {
			echo '<p>' . esc_html__( 'Enter up to 3 product IDs that you frequently use for testing. These will be validated and available for selection on the main debugger page.', 'wc-sc-debugger' ) . '</p>';
		}

		/**
		 * Callback for individual product ID fields.
		 *
		 * @param array $args Field arguments.
		 */
		public function product_id_field_callback( $args ) {
			$options = get_option( 'wc_sc_debugger_validated_products', array() );
			$product_id = isset( $options[ 'product_id_' . $args['field_id'] ]['id'] ) ? $options[ 'product_id_' . $args['field_id'] ]['id'] : '';
			$product_name = isset( $options[ 'product_id_' . $args['field_id'] ]['name'] ) ? $options[ 'product_id_' . $args['field_id'] ]['name'] : '';
			$validation_message = isset( $options[ 'product_id_' . $args['field_id'] ]['message'] ) ? $options[ 'product_id_' . $args['field_id'] ]['message'] : '';
			$validation_type = isset( $options[ 'product_id_' . $args['field_id'] ]['type'] ) ? $options[ 'product_id_' . $args['field_id'] ]['type'] : '';

			printf(
				'<input type="text" id="%1$s" name="wc_sc_debugger_validated_products[product_id_%2$s][id]" value="%3$s" class="regular-text" placeholder="%4$s" />',
				esc_attr( $args['label_for'] ),
				esc_attr( $args['field_id'] ),
				esc_attr( $product_id ),
				esc_attr__( 'Enter Product ID', 'wc-sc-debugger' )
			);

			if ( ! empty( $product_name ) ) {
				printf( '<p class="description">%s: <strong>%s</strong></p>', esc_html__( 'Current Product', 'wc-sc-debugger' ), esc_html( $product_name ) );
			}

			if ( ! empty( $validation_message ) ) {
				printf( '<p class="description validation-message %s">%s</p>', esc_attr( $validation_type ), esc_html( $validation_message ) );
			}
		}

		/**
		 * Sanitize and validate product IDs from settings form.
		 *
		 * @param array $input The input array from the settings form.
		 * @return array The sanitized and validated array.
		 */
		public function sanitize_validated_products( $input ) {
			$new_options = array();
			for ( $i = 1; $i <= 3; $i++ ) {
				$field_key = 'product_id_' . $i;
				$id = isset( $input[ $field_key ]['id'] ) ? absint( $input[ $field_key ]['id'] ) : 0;
				$product_name = '';
				$message = '';
				$type = '';

				if ( $id > 0 ) {
					$product = wc_get_product( $id );
					if ( $product && $product->exists() ) {
						$product_name = $product->get_name();
						$message = sprintf( __( 'Product found: %s', 'wc-sc-debugger' ), $product_name );
						$type = 'success';
					} else {
						$message = sprintf( __( 'Product ID %d not found or is invalid.', 'wc-sc-debugger' ), $id );
						$type = 'error';
						$id = 0; // Clear invalid ID.
					}
				} else {
					$message = __( 'No product ID entered or ID is zero.', 'wc-sc-debugger' );
					$type = 'info';
				}

				$new_options[ $field_key ] = array(
					'id'      => $id,
					'name'    => $product_name,
					'message' => $message,
					'type'    => $type,
				);
			}
			return $new_options;
		}

		/**
		 * Enqueue admin scripts and styles.
		 *
		 * @param string $hook The current admin page hook.
		 */
		public function enqueue_admin_scripts( $hook ) {
			if ( 'woocommerce_page_wc-sc-debugger' !== $hook && 'woocommerce_page_wc-sc-debugger-settings' !== $hook ) {
				return;
			}

			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_style( 'select2' );

			wp_enqueue_script(
				'wc-sc-debugger-admin',
				plugins_url( 'assets/js/admin.js', __FILE__ ),
				array( 'jquery', 'selectWoo' ),
				self::VERSION,
				true
			);

			wp_localize_script(
				'wc-sc-debugger-admin',
				'wcSCDebugger',
				array(
					'ajax_url'               => admin_url( 'admin-ajax.php' ),
					'debug_coupon_nonce'     => wp_create_nonce( 'wc-sc-debug-coupon-nonce' ),
					'search_customers_nonce' => wp_create_nonce( 'search-customers' ),
				)
			);

			/**
			 * Render the self-test admin page.
			 */
			public function render_self_test_page() {
				$results = array();

				// Test 1: log_message() hard cap and truncation
				$ref = new ReflectionClass( __CLASS__ );
				$prop = $ref->getProperty( 'debug_messages' );
				$prop->setAccessible( true );
				$prop->setValue( array() );
				for ( $i = 0; $i < 1005; $i++ ) {
					self::log_message( 'info', str_repeat( 'x', 1500 ), array( 'k' => str_repeat( 'y', 11000 ) ) );
				}
				$messages = $prop->getValue();
				$results['log_cap_count'] = count( $messages ) === 1000;
				$results['log_trunc_message'] = isset( $messages[0]['message'] ) && strlen( $messages[0]['message'] ) === 1000;
				$results['log_data_cap'] = isset( $messages[0]['data'] ) && $messages[0]['data'] === array( 'message' => 'Data too large to log' );

				// Test 2: sanitize_for_logging circular and depth
				$method = new ReflectionMethod( $this, 'sanitize_for_logging' );
				$method->setAccessible( true );
				$a = new stdClass();
				$b = new stdClass();
				$a->b = $b; $b->a = $a;
				$stack = array();
				$first = $method->invoke( $this, $a, 0, $stack );
				$circular = $method->invoke( $this, $a, 1, $stack );
				$deep = array( 'l1' => array( 'l2' => array( 'l3' => array( 'l4' => 'x' ) ) ) );
				$stack2 = array();
				$deep_res = $method->invoke( $this, $deep, 0, $stack2 );
				$results['sanitize_circular'] = is_string( $circular ) && strpos( $circular, '[Circular Reference:' ) === 0;
				$results['sanitize_depth'] = isset( $deep_res['l1']['l2']['l3'] ) && $deep_res['l1']['l2']['l3'] === '[Max Depth Reached]';

				?>
				<div class="wrap woocommerce">
					<h1><?php echo esc_html__( 'SC Self Test', 'wc-sc-debugger' ); ?></h1>
					<p><?php esc_html_e( 'Basic checks for internal safeguards.', 'wc-sc-debugger' ); ?></p>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Test', 'wc-sc-debugger' ); ?></th><th><?php esc_html_e( 'Result', 'wc-sc-debugger' ); ?></th></tr></thead>
						<tbody>
							<tr><td>log_message hard cap (1000)</td><td><?php echo $results['log_cap_count'] ? '<span style="color:green">PASS</span>' : '<span style="color:red">FAIL</span>'; ?></td></tr>
							<tr><td>log_message message truncation</td><td><?php echo $results['log_trunc_message'] ? '<span style="color:green">PASS</span>' : '<span style="color:red">FAIL</span>'; ?></td></tr>
							<tr><td>log_message data size cap</td><td><?php echo $results['log_data_cap'] ? '<span style="color:green">PASS</span>' : '<span style="color:red">FAIL</span>'; ?></td></tr>
							<tr><td>sanitize_for_logging circular guard</td><td><?php echo $results['sanitize_circular'] ? '<span style="color:green">PASS</span>' : '<span style="color:red">FAIL</span>'; ?></td></tr>
							<tr><td>sanitize_for_logging depth guard</td><td><?php echo $results['sanitize_depth'] ? '<span style="color:green">PASS</span>' : '<span style="color:red">FAIL</span>'; ?></td></tr>
						</tbody>
					</table>
				</div>
				<?php
			}

					'search_customers_nonce' => wp_create_nonce( 'search-customers' ),
				)
			);

			wp_enqueue_style(
				'wc-sc-debugger-admin',
				plugins_url( 'assets/css/admin.css', __FILE__ ),
				array(),
				self::VERSION
			);
		}

		/**
		 * Render the main debugger admin page content.
		 */
		public function render_admin_page() {
			global $wpdb;
			$validated_products = get_option( 'wc_sc_debugger_validated_products', array() );
			$current_theme = wp_get_theme();
			?>
			<div class="wrap woocommerce">
				<h1><?php echo esc_html__( 'KISS Woo Coupon Debugger', 'wc-sc-debugger' ); ?> <span class="wc-sc-debugger-version">v<?php echo esc_html( self::VERSION ); ?></span></h1>

				<div class="wc-sc-debugger-env-info">
					<h2 class="wc-sc-debugger-env-title"><?php esc_html_e( 'Environment Versions', 'wc-sc-debugger' ); ?></h2>
					<ul>
						<li><strong><?php esc_html_e( 'WordPress:', 'wc-sc-debugger' ); ?></strong> <?php echo esc_html( get_bloginfo( 'version' ) ); ?></li>
						<li><strong><?php esc_html_e( 'PHP:', 'wc-sc-debugger' ); ?></strong> <?php echo esc_html( phpversion() ); ?></li>
						<li><strong><?php esc_html_e( 'MySQL:', 'wc-sc-debugger' ); ?></strong> <?php echo esc_html( $wpdb->get_var( 'SELECT VERSION()' ) ); ?></li>
						<li><strong><?php esc_html_e( 'WooCommerce:', 'wc-sc-debugger' ); ?></strong> <?php echo esc_html( WC()->version ); ?></li>
						<li><strong><?php echo esc_html( $current_theme->get( 'Name' ) ); ?>:</strong> <?php echo esc_html( $current_theme->get( 'Version' ) ); ?></li>
						<li><strong><?php esc_html_e( 'Coupon Debugger:', 'wc-sc-debugger' ); ?></strong> <?php echo esc_html( self::VERSION ); ?></li>
					</ul>
				</div>

				<div class="wc-sc-debugger-container">
					<div class="wc-sc-debugger-form">
						<div class="form-field">
							<label for="coupon_code"><?php esc_html_e( 'Coupon Code:', 'wc-sc-debugger' ); ?></label>
							<input type="text" id="coupon_code" name="coupon_code" placeholder="<?php esc_attr_e( 'Enter coupon code', 'wc-sc-debugger' ); ?>" class="regular-text" />
						</div>

						<div class="form-field">
							<label for="debug_products_select"><?php esc_html_e( 'Select Product for Testing (optional):', 'wc-sc-debugger' ); ?></label>
							<select id="debug_products_select" name="debug_products_select" class="regular-text">
								<option value=""><?php esc_html_e( 'No specific product', 'wc-sc-debugger' ); ?></option>
								<?php
								foreach ( (array) $validated_products as $key => $product_data ) {
									if ( ! empty( $product_data['id'] ) ) {
										printf(
											'<option value="%d">%s (ID: %d)</option>',
											esc_attr( $product_data['id'] ),
											esc_html( $product_data['name'] ),
											esc_html( $product_data['id'] )
										);
									}
								}
								?>
							</select>
							<p class="description"><?php esc_html_e( 'Choose a pre-defined product to test coupon compatibility. Define products in the ', 'wc-sc-debugger' ); ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-sc-debugger-settings' ) ); ?>"><?php esc_html_e( 'Debugger Settings', 'wc-sc-debugger' ); ?></a> <?php esc_html_e( 'page.', 'wc-sc-debugger' ); ?></p>
						</div>

						<div class="form-field">
							<label for="debug_user"><?php esc_html_e( 'Select User (optional):', 'wc-sc-debugger' ); ?></label>
							<select id="debug_user" name="debug_user" class="wc-customer-search" data-placeholder="<?php esc_attr_e( 'Search for a customer&hellip;', 'wc-sc-debugger' ); ?>" data-action="woocommerce_json_search_customers"></select>
							<p class="description"><?php esc_html_e( 'Select a user to test user-specific coupon restrictions (e.g., "for new user only"). Leave empty for guest user.', 'wc-sc-debugger' ); ?></p>
						</div>

						<button id="run_debug" class="button button-primary"><?php esc_html_e( 'Run Debug', 'wc-sc-debugger' ); ?></button>
						<button id="clear_debug" class="button button-secondary"><?php esc_html_e( 'Clear Output', 'wc-sc-debugger' ); ?></button>
					</div>

					<div class="wc-sc-debugger-output">
						<h2><?php esc_html_e( 'Debugging Output', 'wc-sc-debugger' ); ?></h2>
						<div id="debug_results" class="debug-results">
						<div id="json_export_tools" class="json-export-tools" style="display:none; margin-top: 16px;">
							<h2><?php esc_html_e( 'JSON Export', 'wc-sc-debugger' ); ?></h2>
							<p class="description"><?php esc_html_e( 'Copy the raw JSON to share or analyze with external tools.', 'wc-sc-debugger' ); ?></p>
							<p><button id="copy_json" class="button"><?php esc_html_e( 'Copy JSON', 'wc-sc-debugger' ); ?></button></p>
							<pre id="json_export_pre" class="json-export" style="max-height: 300px; overflow: auto; background: #f6f8fa; padding: 10px; border: 1px solid #ddd;"></pre>
						</div>
							<p><?php esc_html_e( 'Enter a coupon code and click "Run Debug" to see the processing details.', 'wc-sc-debugger' ); ?></p>
						</div>
						<div class="loading-indicator" style="display: none;">
							<p><?php esc_html_e( 'Debugging in progress...', 'wc-sc-debugger' ); ?></p>
						</div>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Render the settings page content.
		 */
		public function render_settings_page() {
			?>
			<div class="wrap woocommerce">
				<h1><?php echo esc_html__( 'KISS Woo Coupon Debugger Settings', 'wc-sc-debugger' ); ?> <span class="wc-sc-debugger-version">v<?php echo esc_html( self::VERSION ); ?></span></h1>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'wc_sc_debugger_options_group' );
					do_settings_sections( 'wc-sc-debugger-settings' );
					submit_button();
					?>
				</form>
			</div>
			<?php
		}

			/**
			 * Render the self-test admin page.
			 */
			public function render_self_test_page() {
				$results = array();

				// Test 1: log_message() hard cap and truncation
				$ref = new ReflectionClass( __CLASS__ );
				$prop = $ref->getProperty( 'debug_messages' );
				$prop->setAccessible( true );
				$prop->setValue( array() );
				for ( $i = 0; $i < 1005; $i++ ) {
					self::log_message( 'info', str_repeat( 'x', 1500 ), array( 'k' => str_repeat( 'y', 11000 ) ) );
				}
				$messages = $prop->getValue();
				$results['log_cap_count'] = count( $messages ) === 1000;
				$results['log_trunc_message'] = isset( $messages[0]['message'] ) && strlen( $messages[0]['message'] ) === 1000;
				$results['log_data_cap'] = isset( $messages[0]['data'] ) && $messages[0]['data'] === array( 'message' => 'Data too large to log' );

				// Test 2: sanitize_for_logging circular and depth
				$method = new ReflectionMethod( $this, 'sanitize_for_logging' );
				$method->setAccessible( true );
				$a = new stdClass();
				$b = new stdClass();
				$a->b = $b; $b->a = $a;
				$stack = array();
				$first = $method->invoke( $this, $a, 0, $stack );
				$circular = $method->invoke( $this, $a, 1, $stack );
				$deep = array( 'l1' => array( 'l2' => array( 'l3' => array( 'l4' => 'x' ) ) ) );
				$stack2 = array();
				$deep_res = $method->invoke( $this, $deep, 0, $stack2 );
				$results['sanitize_circular'] = is_string( $circular ) && strpos( $circular, '[Circular Reference:' ) === 0;
				$results['sanitize_depth'] = isset( $deep_res['l1']['l2']['l3'] ) && $deep_res['l1']['l2']['l3'] === '[Max Depth Reached]';

				?>
				<div class="wrap woocommerce">
					<h1><?php echo esc_html__( 'SC Self Test', 'wc-sc-debugger' ); ?></h1>
					<p><?php esc_html_e( 'Basic checks for internal safeguards.', 'wc-sc-debugger' ); ?></p>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Test', 'wc-sc-debugger' ); ?></th><th><?php esc_html_e( 'Result', 'wc-sc-debugger' ); ?></th></tr></thead>
						<tbody>
							<tr><td>log_message hard cap (1000)</td><td><?php echo $results['log_cap_count'] ? '<span style="color:green">PASS</span>' : '<span style="color:red">FAIL</span>'; ?></td></tr>
							<tr><td>log_message message truncation</td><td><?php echo $results['log_trunc_message'] ? '<span style="color:green">PASS</span>' : '<span style="color:red">FAIL</span>'; ?></td></tr>
							<tr><td>log_message data size cap</td><td><?php echo $results['log_data_cap'] ? '<span style="color:green">PASS</span>' : '<span style="color:red">FAIL</span>'; ?></td></tr>
							<tr><td>sanitize_for_logging circular guard</td><td><?php echo $results['sanitize_circular'] ? '<span style="color:green">PASS</span>' : '<span style="color:red">FAIL</span>'; ?></td></tr>
							<tr><td>sanitize_for_logging depth guard</td><td><?php echo $results['sanitize_depth'] ? '<span style="color:green">PASS</span>' : '<span style="color:red">FAIL</span>'; ?></td></tr>
						</tbody>
					</table>
				</div>
				<?php
			}

		/**
		 * Handle AJAX request for debugging coupon.
		 */
		public function handle_debug_coupon_ajax() {
			// Set execution time limit
			@set_time_limit( $this->max_execution_time );

			// Verify nonce
			check_ajax_referer( 'wc-sc-debug-coupon-nonce', 'security' );

			// Check permissions
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wc-sc-debugger' ) ) );
			}

			// Initialize WooCommerce if needed
			if ( ! did_action( 'woocommerce_init' ) ) {
				WC()->init();
			}

			// Ensure session is initialized
			if ( ! WC()->session ) {
				WC()->initialize_session();
			}

			// Force session initialization for AJAX
			if ( ! WC()->session->has_session() ) {
				WC()->session->set_customer_session_cookie( true );
			}

			// Verify WooCommerce components
			if ( ! function_exists( 'WC' ) || ! isset( WC()->cart ) || ! is_object( WC()->cart ) || ! isset( WC()->session ) || ! is_object( WC()->session ) ) {
				wp_send_json_error( array( 'message' => __( 'WooCommerce cart or session is not fully loaded. Please ensure WooCommerce is active and properly initialized.', 'wc-sc-debugger' ) ) );
			}

			$coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
			$product_id_selected = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
			$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;

			if ( empty( $coupon_code ) ) {
				wp_send_json_error( array( 'message' => __( 'Please enter a coupon code.', 'wc-sc-debugger' ) ) );
			}

			self::$debug_messages = array();

			try {
				// Add memory tracking
				$initial_memory = memory_get_usage();
				self::log_message( 'info', sprintf( __( 'Initial memory usage: %s', 'wc-sc-debugger' ), size_format( $initial_memory ) ) );

				$this->start_hook_tracking();

				$product_ids_for_test = ( $product_id_selected > 0 ) ? array( $product_id_selected ) : array();

				$result = $this->test_coupon( $coupon_code, $product_ids_for_test, $user_id );

				$this->stop_hook_tracking();

				// Check memory usage
				$final_memory = memory_get_usage();
				$memory_diff = $final_memory - $initial_memory;
				self::log_message( 'info', sprintf( __( 'Memory used: %s', 'wc-sc-debugger' ), size_format( $memory_diff ) ) );

				if ( is_wp_error( $result ) ) {
					self::log_message( 'error', sprintf( __( 'Coupon application failed: %s', 'wc-sc-debugger' ), $result->get_error_message() ) );
				} elseif ( ! $result ) {
					self::log_message( 'warning', __( 'Coupon could not be applied or is invalid.', 'wc-sc-debugger' ) );
				} else {
					self::log_message( 'success', sprintf( __( 'Coupon "%s" processed successfully.', 'wc-sc-debugger' ), $coupon_code ) );
				}

				wp_send_json_success( array(
					'messages' => self::$debug_messages,
					'coupon_valid' => (bool) $result,
				) );

			} catch ( Exception $e ) {
				error_log( 'WC SC Debugger AJAX Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() );

				// Add more context to the error
				$error_context = array(
					'message' => $e->getMessage(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
					'trace' => wp_debug_backtrace_summary()
				);

				wp_send_json_error( array(
					'message' => sprintf(
						__( 'An error occurred: %s. Please check your server error logs for more details.', 'wc-sc-debugger' ),
						$e->getMessage()
					),
					'debug_info' => WP_DEBUG ? $error_context : null,
				) );
			}
		}

		/**
		 * Helper function to log messages for debugging output.
		 *
		 * @param string $type    Type of message (info, success, warning, error).
		 * @param string $message The message content.
		 * @param array  $data    Optional data to include.
		 */
		public static function log_message( $type, $message, $data = array() ) {
			// Hard cap: stop logging when limit is reached to prevent unbounded memory usage
			if ( count( self::$debug_messages ) >= 1000 ) {
				return;
			}

			// Ensure message is a string and limit its length
			$message = (string) $message;
			if ( strlen( $message ) > 1000 ) {
				$message = substr( $message, 0, 1000 );
			}

			// Limit individual data payload size (approximate via JSON length)
			if ( is_array( $data ) || is_object( $data ) ) {
				$encoded = json_encode( $data );
				if ( false === $encoded || strlen( $encoded ) > 10240 ) { // ~10KB
					$data = array( 'message' => 'Data too large to log' );
				}
			}

			self::$debug_messages[] = array(
				'type'    => $type,
				'message' => $message,
				'data'    => $data,
			);
		}

		/**
		 * Start tracking relevant WooCommerce and Smart Coupons hooks and filters.
		 */
		private function start_hook_tracking() {
			if ( $this->hooks_tracking_active ) {
				return; // Prevent recursion
			}

			$this->hooks_tracking_active = true;

			// Core coupon validation filters
			$core_filters = array(
				'woocommerce_coupon_is_valid',
				'woocommerce_coupon_is_valid_for_product',
				'woocommerce_coupon_validate_expiry_date',
				'woocommerce_coupon_get_discount_amount',
				'woocommerce_apply_individual_use_coupon',
				'woocommerce_apply_with_individual_use_coupon',
				'woocommerce_coupon_error'
			);

			// Smart Coupons specific filters
			$sc_filters = array(
				'wc_sc_validate_coupon_amount',
				'wc_sc_is_send_coupon_email',
				'wc_sc_is_coupon_restriction_available',
				'wc_sc_percent_discount_types',
				'wc_sc_coupon_type',
				'wc_sc_coupon_amount'
			);

			// Add filters with appropriate argument counts
			foreach ( $core_filters as $filter ) {
				add_filter( $filter, array( $this, 'track_filter' ), 9999, 10 );
			}

			foreach ( $sc_filters as $filter ) {
				add_filter( $filter, array( $this, 'track_filter' ), 9999, 10 );
			}

			// Actions
			$actions = array(
				'woocommerce_applied_coupon',
				'woocommerce_removed_coupon',
				'woocommerce_coupon_loaded',
				'woocommerce_before_calculate_totals',
				'woocommerce_after_calculate_totals'
			);

			foreach ( $actions as $action ) {
				add_action( $action, array( $this, 'track_action' ), 9999, 10 );
			}
		}

		/**
		 * Stop tracking hooks and filters.
		 */
		private function stop_hook_tracking() {
			if ( ! $this->hooks_tracking_active ) {
				return;
			}

			$this->hooks_tracking_active = false;

			// Remove all filters
			$all_filters = array(
				'woocommerce_coupon_is_valid',
				'woocommerce_coupon_is_valid_for_product',
				'woocommerce_coupon_validate_expiry_date',
				'woocommerce_coupon_get_discount_amount',
				'woocommerce_apply_individual_use_coupon',
				'woocommerce_apply_with_individual_use_coupon',
				'woocommerce_coupon_error',
				'wc_sc_validate_coupon_amount',
				'wc_sc_is_send_coupon_email',
				'wc_sc_is_coupon_restriction_available',
				'wc_sc_percent_discount_types',
				'wc_sc_coupon_type',
				'wc_sc_coupon_amount'
			);

			foreach ( $all_filters as $filter ) {
				remove_filter( $filter, array( $this, 'track_filter' ), 9999 );
			}

			// Remove all actions
			$all_actions = array(
				'woocommerce_applied_coupon',
				'woocommerce_removed_coupon',
				'woocommerce_coupon_loaded',
				'woocommerce_before_calculate_totals',
				'woocommerce_after_calculate_totals'
			);

			foreach ( $all_actions as $action ) {
				remove_action( $action, array( $this, 'track_action' ), 9999 );
			}
		}

		/**
		 * Callback for tracking filters.
		 *
		 * @param mixed $value The value being filtered.
		 * @param mixed ...$args Additional arguments passed to the filter.
		 * @return mixed The original value.
		 */
		public function track_filter( $value, ...$args ) {
			if ( ! $this->hooks_tracking_active ) {
				return $value; // Prevent logging during restoration
			}

			$filter_name = current_filter();
			self::log_message(
				'filter',
				sprintf( __( 'Filter: %s', 'wc-sc-debugger' ), $filter_name ),
				array(
					'args'    => $this->sanitize_for_logging( $args ),
					'return'  => $this->sanitize_for_logging( $value ),
				)
			);
			return $value;
		}

		/**
		 * Callback for tracking actions.
		 *
		 * @param mixed ...$args Arguments passed to the action.
		 */
		public function track_action( ...$args ) {
			if ( ! $this->hooks_tracking_active ) {
				return; // Prevent logging during restoration
			}

			$action_name = current_action();
			self::log_message(
				'action',
				sprintf( __( 'Action: %s', 'wc-sc-debugger' ), $action_name ),
				array(
					'args' => $this->sanitize_for_logging( $args ),
				)
			);
		}

		/**
		 * Sanitize data for logging to prevent issues with complex objects or circular references.
		 *
		 * @param mixed $data The data to sanitize.
		 * @param int   $depth The current recursion depth.
		 * @param array $stack Keeps track of objects to detect circular references.
		 * @return mixed Sanitized data.
		 */
		private function sanitize_for_logging( $data, $depth = 0, &$stack = array() ) {
			// Tighter recursion depth to reduce risk of runaway traversal
			if ( $depth > 2 ) {
				return '[Max Depth Reached]';
			}

			if ( is_object( $data ) ) {
				// Circular reference protection using object identity
				$hash = spl_object_hash( $data );
				if ( isset( $stack[ $hash ] ) ) {
					return sprintf( '[Circular Reference: %s]', get_class( $data ) );
				}
				$stack[ $hash ] = true; // Do not unset; keep record to prevent reprocessing

				if ( method_exists( $data, 'to_array' ) ) {
					$result = $this->sanitize_for_logging( $data->to_array(), $depth + 1, $stack );
				} elseif ( $data instanceof WC_Coupon ) {
					$result = array(
						'type'          => 'WC_Coupon',
						'id'            => $data->get_id(),
						'code'          => $data->get_code(),
						'amount'        => $data->get_amount(),
						'discount_type' => $data->get_discount_type(),
					);
				} elseif ( $data instanceof WC_Product ) {
					$result = array(
						'type' => 'WC_Product',
						'id'   => $data->get_id(),
						'name' => $data->get_name(),
					);
				} elseif ( $data instanceof WC_Cart ) {
					$result = '[Object: WC_Cart]';
				} elseif ( $data instanceof WP_Error ) {
					$result = array(
						'type'    => 'WP_Error',
						'code'    => $data->get_error_code(),
						'message' => $data->get_error_message(),
					);
				} else {
					$result = sprintf( '[Object: %s]', get_class( $data ) );
				}

				return $result;
			} elseif ( is_array( $data ) ) {
				// Protect against extremely large arrays
				$size = count( $data );
				if ( $size > 100 ) {
					return '[Large Array - ' . $size . ' items]';
				}
				$sanitized_array = array();
				foreach ( $data as $key => $value ) {
					$sanitized_array[ $key ] = $this->sanitize_for_logging( $value, $depth + 1, $stack );
				}
				return $sanitized_array;
			} elseif ( is_resource( $data ) ) {
				return '[Resource]';
			}

			// Scalars
			return $data;
		}

		/**
		 * Simulate a coupon test by creating a temporary cart and applying the coupon.
		 *
		 * @param string $coupon_code The coupon code to test.
		 * @param array  $product_ids Array of product IDs to add to the cart.
		 * @param int    $user_id     The user ID to simulate.
		 * @return bool|WP_Error True if coupon applied successfully, WP_Error on failure.
		 */
		private function test_coupon( $coupon_code, $product_ids = array(), $user_id = 0 ) {
			// Temporarily disable hooks tracking to prevent recursion during backup
			$hooks_were_active = $this->hooks_tracking_active;
			$this->hooks_tracking_active = false;

			// Store original state with error handling
			try {
				$original_cart_contents = WC()->cart->get_cart_contents();
				$original_applied_coupons = WC()->cart->get_applied_coupons();
				$original_user_id = get_current_user_id();

				// Safely get session data
				$original_session_data = array();
				if ( WC()->session && method_exists( WC()->session, 'get_session_data' ) ) {
					$original_session_data = WC()->session->get_session_data();
				}

				// Safely backup notices
				$original_notices = array();
				if ( WC()->session ) {
					$original_notices = WC()->session->get( 'wc_notices', array() );
					if ( ! is_array( $original_notices ) ) {
						$original_notices = array();
					}
				}
			} catch ( Exception $e ) {
				self::log_message( 'error', sprintf( __( 'Failed to backup cart state: %s', 'wc-sc-debugger' ), $e->getMessage() ) );
				return new WP_Error( 'backup_failed', __( 'Could not backup cart state for testing.', 'wc-sc-debugger' ) );
			}

			// Re-enable hooks tracking if it was active
			if ( $hooks_were_active ) {
				$this->hooks_tracking_active = true;
			}

			// Add filter to prevent Smart Coupons from modifying items during our test
			add_filter( 'woocommerce_coupon_get_items_to_validate', array( $this, 'ensure_valid_cart_items' ), 1, 2 );

			try {
				// Clear the current cart and session for a clean test
				WC()->cart->empty_cart( true );
				if ( WC()->session ) {
					WC()->session->set( 'wc_notices', array() );
					WC()->session->set( 'sc_coupon_valid', null );
					WC()->session->set( 'sc_coupon_error', null );
					WC()->session->set( 'wc_sc_cart_smart_coupons', array() );
				}

				// Simulate user login if a user ID is provided
				if ( $user_id > 0 && get_user_by( 'id', $user_id ) ) {
					wp_set_current_user( $user_id );
					self::log_message( 'info', sprintf( __( 'Simulating user ID: %d', 'wc-sc-debugger' ), $user_id ) );
				} else {
					wp_set_current_user( 0 );
					self::log_message( 'info', __( 'Simulating guest user.', 'wc-sc-debugger' ) );
				}

				// Add products to the cart
				$products_added = false;
				if ( ! empty( $product_ids ) ) {
					foreach ( $product_ids as $product_id ) {
						$added = $this->add_product_to_cart( $product_id );
						if ( $added ) {
							$products_added = true;
						}
					}
				}

				// If cart is still empty, add a dummy product
				if ( WC()->cart->is_empty() ) {
					$dummy_product_id = $this->get_or_create_dummy_product();
					if ( $dummy_product_id ) {
						$cart_item_key = WC()->cart->add_to_cart( $dummy_product_id, 1 );
						if ( $cart_item_key ) {
							self::log_message( 'info', sprintf( __( 'Cart was empty, added dummy product (ID: %d) for testing general coupons.', 'wc-sc-debugger' ), $dummy_product_id ) );
						} else {
							throw new Exception( __( 'Failed to add dummy product to cart.', 'wc-sc-debugger' ) );
						}
					} else {
						throw new Exception( __( 'Could not create or find a dummy product for testing.', 'wc-sc-debugger' ) );
					}
				}

				// Calculate totals before applying coupon
				WC()->cart->calculate_totals();

				self::log_message( 'info', sprintf( __( 'Cart total before coupon: %s', 'wc-sc-debugger' ), wc_price( WC()->cart->get_total( 'edit' ) ) ) );
				self::log_message( 'info', sprintf( __( 'Attempting to apply coupon: "%s"', 'wc-sc-debugger' ), $coupon_code ) );

				// Apply the coupon
				$coupon_applied = false;
				$coupon_applied = WC()->cart->apply_coupon( $coupon_code );

				if ( is_wp_error( $coupon_applied ) ) {
					self::log_message( 'error', sprintf( __( 'Failed to apply coupon: %s', 'wc-sc-debugger' ), $coupon_applied->get_error_message() ) );
					$result = false;
				} elseif ( ! $coupon_applied ) {
					// Check for WooCommerce notices
					$notices = wc_get_notices( 'error' );
					if ( ! empty( $notices ) ) {
						foreach ( $notices as $notice ) {
							$notice_text = is_array( $notice ) ? $notice['notice'] : $notice;
							self::log_message( 'error', sprintf( __( 'WooCommerce Notice: %s', 'wc-sc-debugger' ), $notice_text ) );
						}
					}
					self::log_message( 'error', __( 'Coupon could not be applied. Check coupon validity and restrictions.', 'wc-sc-debugger' ) );
					$result = false;
				} else {
					WC()->cart->calculate_totals();
					$cart_total = WC()->cart->get_total( 'edit' );
					$discount_total = WC()->cart->get_discount_total();
					self::log_message( 'success', __( 'Coupon applied successfully!', 'wc-sc-debugger' ) );
					self::log_message( 'success', sprintf( __( 'New Cart Total: %s', 'wc-sc-debugger' ), wc_price( $cart_total ) ) );
					self::log_message( 'success', sprintf( __( 'Discount Amount: %s', 'wc-sc-debugger' ), wc_price( $discount_total ) ) );
					$result = true;
				}

			} catch ( Exception $e ) {
				self::log_message( 'error', sprintf( __( 'Exception during coupon test: %s', 'wc-sc-debugger' ), $e->getMessage() ) );
				$result = new WP_Error( 'test_exception', $e->getMessage() );
			}

			// Remove our filter
			remove_filter( 'woocommerce_coupon_get_items_to_validate', array( $this, 'ensure_valid_cart_items' ), 1 );

			// Restore original state
			$this->restore_cart_state( $original_cart_contents, $original_applied_coupons, $original_session_data, $original_notices, $original_user_id );

			return $result;
		}

		/**
		 * Ensure cart items are properly structured for validation
		 *
		 * @param array $items Cart items
		 * @param WC_Coupon $coupon Coupon object
		 * @return array
		 */
		public function ensure_valid_cart_items( $items, $coupon ) {
			if ( ! is_array( $items ) ) {
				return $items;
			}

			foreach ( $items as $key => $item ) {
				// Ensure each item has proper structure
				if ( ! is_array( $item ) ) {
					unset( $items[ $key ] );
					continue;
				}

				// Ensure data property exists and is an object
				if ( ! isset( $item['data'] ) || ! is_object( $item['data'] ) ) {
					if ( isset( $item['product_id'] ) ) {
						$product_id = isset( $item['variation_id'] ) && $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
						$product = wc_get_product( $product_id );
						if ( $product ) {
							$items[ $key ]['data'] = $product;
						} else {
							unset( $items[ $key ] );
						}
					} else {
						unset( $items[ $key ] );
					}
				}

				// Ensure quantity is numeric
				if ( isset( $item['quantity'] ) && ! is_numeric( $item['quantity'] ) ) {
					$items[ $key ]['quantity'] = 1;
				}
			}

			return array_values( $items ); // Re-index array
		}

		/**
		 * Restore cart state after testing
		 *
		 * @param array $original_cart_contents Original cart contents
		 * @param array $original_applied_coupons Original applied coupons
		 * @param array $original_session_data Original session data
		 * @param array $original_notices Original notices
		 * @param int $original_user_id Original user ID
		 */
		private function restore_cart_state( $original_cart_contents, $original_applied_coupons, $original_session_data, $original_notices, $original_user_id ) {
			// Temporarily disable hooks tracking during restoration
			$hooks_were_active = $this->hooks_tracking_active;
			$this->hooks_tracking_active = false;

			try {
				// Clear cart
				WC()->cart->empty_cart( true );

				// Restore cart contents
				foreach ( $original_cart_contents as $cart_item_key => $cart_item ) {
					$product_id = isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0;
					$quantity = isset( $cart_item['quantity'] ) ? $cart_item['quantity'] : 1;
					$variation_id = isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0;
					$variation = isset( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ? $cart_item['variation'] : array();
					$cart_item_data = isset( $cart_item['data'] ) && is_array( $cart_item['data'] ) ? $cart_item['data'] : array();

					// Ensure we have a valid product ID
					if ( $product_id ) {
						WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation, $cart_item_data );
					}
				}

				// Restore session data
				if ( WC()->session && ! empty( $original_session_data ) ) {
					foreach ( $original_session_data as $key => $value ) {
						WC()->session->set( $key, $value );
					}
				}

				// Restore notices
				if ( WC()->session ) {
					WC()->session->set( 'wc_notices', $original_notices );
				}

				// Restore applied coupons
				foreach ( $original_applied_coupons as $coupon_code ) {
					WC()->cart->apply_coupon( $coupon_code );
				}

				// Restore user
				wp_set_current_user( $original_user_id );

				// Recalculate totals
				WC()->cart->calculate_totals();

			} catch ( Exception $e ) {
				error_log( 'WC SC Debugger: Failed to restore cart state - ' . $e->getMessage() );
			}

			// Re-enable hooks tracking if it was active
			if ( $hooks_were_active ) {
				$this->hooks_tracking_active = true;
			}
		}

		/**
		 * Add a product to cart, handling variable products by selecting first available variation
		 *
		 * @param int $product_id Product ID to add
		 * @return bool True if product was added successfully
		 */
		private function add_product_to_cart( $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				self::log_message( 'warning', sprintf( __( 'Product ID %d not found.', 'wc-sc-debugger' ), $product_id ) );
				return false;
			}

			try {
				// Handle variable products
				if ( $product->is_type( 'variable' ) ) {
					self::log_message( 'info', sprintf( __( 'Product "%s" (ID: %d) is a variable product. Finding first available variation...', 'wc-sc-debugger' ), $product->get_name(), $product_id ) );

					// Get available variations
					$variations = $product->get_available_variations();

					if ( empty( $variations ) ) {
						self::log_message( 'warning', sprintf( __( 'No available variations found for product ID %d.', 'wc-sc-debugger' ), $product_id ) );
						return false;
					}

					// Find the first variation that's purchasable and in stock
					foreach ( $variations as $variation_data ) {
						$variation_id = $variation_data['variation_id'];
						$variation = wc_get_product( $variation_id );

						if ( $variation && $variation->is_purchasable() && $variation->is_in_stock() ) {
							// Get the variation attributes - ensure they're properly formatted
							$variation_attributes = array();
							if ( isset( $variation_data['attributes'] ) && is_array( $variation_data['attributes'] ) ) {
								foreach ( $variation_data['attributes'] as $attr_name => $attr_value ) {
									// Ensure attribute names and values are strings
									$variation_attributes[ (string) $attr_name ] = (string) $attr_value;
								}
							}

							// Add variation to cart - let WooCommerce handle the data structure
							$cart_item_key = WC()->cart->add_to_cart( $product_id, 1, $variation_id, $variation_attributes );

							if ( $cart_item_key ) {
								// Log the selected attributes
								$attribute_string = '';
								foreach ( $variation_attributes as $attr_name => $attr_value ) {
									if ( ! empty( $attr_value ) ) {
										$taxonomy = str_replace( 'attribute_', '', $attr_name );
										$term = get_term_by( 'slug', $attr_value, $taxonomy );
										$label = $term ? $term->name : $attr_value;
										$attribute_string .= ucfirst( str_replace( 'pa_', '', $taxonomy ) ) . ': ' . $label . ', ';
									}
								}
								$attribute_string = rtrim( $attribute_string, ', ' );

								self::log_message( 'success', sprintf(
									__( 'Added variable product to cart: %s (ID: %d, Variation ID: %d) - %s', 'wc-sc-debugger' ),
									$product->get_name(),
									$product_id,
									$variation_id,
									$attribute_string
								) );
								return true;
							}
						}
					}

					self::log_message( 'warning', sprintf( __( 'No purchasable variations in stock for product ID %d.', 'wc-sc-debugger' ), $product_id ) );
					return false;

				} elseif ( $product->is_type( 'grouped' ) ) {
					// Handle grouped products
					self::log_message( 'info', sprintf( __( 'Product "%s" (ID: %d) is a grouped product. Adding first available child product...', 'wc-sc-debugger' ), $product->get_name(), $product_id ) );

					$children = $product->get_children();
					if ( ! empty( $children ) ) {
						foreach ( $children as $child_id ) {
							$child_product = wc_get_product( $child_id );
							if ( $child_product && $child_product->is_purchasable() && $child_product->is_in_stock() ) {
								$cart_item_key = WC()->cart->add_to_cart( $child_id );
								if ( $cart_item_key ) {
									self::log_message( 'success', sprintf(
										__( 'Added grouped product child to cart: %s (Child ID: %d from Parent ID: %d)', 'wc-sc-debugger' ),
										$child_product->get_name(),
										$child_id,
										$product_id
									) );
									return true;
								}
							}
						}
					}

					self::log_message( 'warning', sprintf( __( 'No purchasable child products found for grouped product ID %d.', 'wc-sc-debugger' ), $product_id ) );
					return false;

				} else {
					// Handle simple products and other types
					if ( $product->is_purchasable() && $product->is_in_stock() ) {
						$cart_item_key = WC()->cart->add_to_cart( $product_id );
						if ( $cart_item_key ) {
							self::log_message( 'success', sprintf(
								__( 'Added product to cart: %s (ID: %d)', 'wc-sc-debugger' ),
								$product->get_name(),
								$product_id
							) );
							return true;
						} else {
							self::log_message( 'warning', sprintf( __( 'Failed to add product ID %d to cart.', 'wc-sc-debugger' ), $product_id ) );
							return false;
						}
					} else {
						$reasons = array();
						if ( ! $product->is_purchasable() ) {
							$reasons[] = __( 'not purchasable', 'wc-sc-debugger' );
						}
						if ( ! $product->is_in_stock() ) {
							$reasons[] = __( 'out of stock', 'wc-sc-debugger' );
						}
						self::log_message( 'warning', sprintf(
							__( 'Product ID %d cannot be added to cart: %s.', 'wc-sc-debugger' ),
							$product_id,
							implode( ', ', $reasons )
						) );
						return false;
					}
				}
			} catch ( Exception $e ) {
				self::log_message( 'error', sprintf(
					__( 'Error adding product to cart: %s', 'wc-sc-debugger' ),
					$e->getMessage()
				) );
				return false;
			}
		}

		/**
		 * Get or create a simple dummy product for testing purposes.
		 *
		 * @return int|bool Product ID on success, false on failure.
		 */
		private function get_or_create_dummy_product() {
			$product_id = get_option( 'wc_sc_debugger_dummy_product_id' );

			if ( $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product && $product->exists() ) {
					return $product_id;
				}
			}

			// Create a new dummy product
			try {
				$new_product = new WC_Product_Simple();
				$new_product->set_name( 'Debugger Test Product' );
				$new_product->set_status( 'private' );
				$new_product->set_catalog_visibility( 'hidden' );
				$new_product->set_price( 100 );
				$new_product->set_regular_price( 100 );
				$new_product->set_manage_stock( false );
				$new_product->set_stock_status( 'instock' );
				$new_product->set_virtual( true );

				$new_product_id = $new_product->save();

				if ( $new_product_id ) {
					update_option( 'wc_sc_debugger_dummy_product_id', $new_product_id );
					return $new_product_id;
				}
			} catch ( Exception $e ) {
				error_log( 'WC SC Debugger: Failed to create dummy product - ' . $e->getMessage() );
			}

			return false;
		}

	} // End class WC_SC_Debugger.

	// Initialize the plugin.
	add_action( 'plugins_loaded', function() {
		if ( class_exists( 'WooCommerce' ) ) {
			WC_SC_Debugger::get_instance();
		}
	}, 11 );

	/**
	 * Activation hook.
	 */
	register_activation_hook( __FILE__, 'wc_sc_debugger_activate' );
	function wc_sc_debugger_activate() {
		// Activation logic can go here.
	}

	/**
	 * Deactivation hook.
	 */
	register_deactivation_hook( __FILE__, 'wc_sc_debugger_deactivate' );
	function wc_sc_debugger_deactivate() {
		// Clean up dummy product
		$product_id = get_option( 'wc_sc_debugger_dummy_product_id' );
		if ( $product_id ) {
			wp_delete_post( $product_id, true );
		}
		delete_option( 'wc_sc_debugger_dummy_product_id' );
	}

} // End if class_exists.
