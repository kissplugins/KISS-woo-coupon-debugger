<?php
/**
 * Plugin Name: WC Smart Coupons Debugger
 * Plugin URI:  https://example.com/
 * Description: A companion plugin for WooCommerce Smart Coupons to debug coupon application and hook/filter processing.
 * Version:     1.2.0
 * Author:      Your Name
 * Author URI:  https://example.com/
 * License:     GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wc-sc-debugger
 * Domain Path: /languages
 *
 * Changelog
 *
 * #### 1.2.0 - 2025-08-03
 * - Fix: PHP fatal error by ensuring WC notices are correctly handled during the test simulation.
 * - Tweak: Improved session handling during the coupon test.
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
		 * Array to store debugging messages.
		 *
		 * @var array
		 */
		public static $debug_messages = array();

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
			$product_id = $options[ 'product_id_' . $args['field_id'] ]['id'] ?? '';
			$product_name = $options[ 'product_id_' . $args['field_id'] ]['name'] ?? '';
			$validation_message = $options[ 'product_id_' . $args['field_id'] ]['message'] ?? '';
			$validation_type = $options[ 'product_id_' . $args['field_id'] ]['type'] ?? '';

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
				$id = absint( $input[ $field_key ]['id'] ?? 0 );
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
				'1.2.0', // Updated version.
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

			wp_enqueue_style(
				'wc-sc-debugger-admin',
				plugins_url( 'assets/css/admin.css', __FILE__ ),
				array(),
				'1.0.0'
			);
		}

		/**
		 * Render the main debugger admin page content.
		 */
		public function render_admin_page() {
			$validated_products = get_option( 'wc_sc_debugger_validated_products', array() );
			?>
			<div class="wrap woocommerce">
				<h1><?php esc_html_e( 'WooCommerce Smart Coupons Debugger', 'wc-sc-debugger' ); ?></h1>

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
							<p class="description"><?php esc_html_e( 'Choose a pre-defined product to test coupon compatibility. Define products in the ', 'wc-sc-debugger' ); ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-sc-debugger-settings' ) ); ?>"><?php esc_html_e( 'SC Debugger Settings', 'wc-sc-debugger' ); ?></a> <?php esc_html_e( 'page.', 'wc-sc-debugger' ); ?></p>
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
				<h1><?php esc_html_e( 'WooCommerce Smart Coupons Debugger Settings', 'wc-sc-debugger' ); ?></h1>
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
		 * Handle AJAX request for debugging coupon.
		 */
		public function handle_debug_coupon_ajax() {
			check_ajax_referer( 'wc-sc-debug-coupon-nonce', 'security' );

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wc-sc-debugger' ) ) );
			}

			if ( ! function_exists( 'WC' ) || ! isset( WC()->cart ) || ! is_object( WC()->cart ) || ! isset( WC()->session ) || ! is_object( WC()->session ) ) {
				wp_send_json_error( array( 'message' => __( 'WooCommerce cart or session is not fully loaded. Please ensure WooCommerce is active and properly initialized.', 'wc-sc-debugger' ) ) );
			}

			$coupon_code = sanitize_text_field( wp_unslash( $_POST['coupon_code'] ?? '' ) );
			$product_id_selected = absint( wp_unslash( $_POST['product_id'] ?? 0 ) );
			$user_id     = absint( wp_unslash( $_POST['user_id'] ?? 0 ) );

			if ( empty( $coupon_code ) ) {
				wp_send_json_error( array( 'message' => __( 'Please enter a coupon code.', 'wc-sc-debugger' ) ) );
			}

			self::$debug_messages = array();

			try {
				$this->start_hook_tracking();

				$product_ids_for_test = ( $product_id_selected > 0 ) ? array( $product_id_selected ) : array();

				$result = $this->test_coupon( $coupon_code, $product_ids_for_test, $user_id );

				$this->stop_hook_tracking();

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
				wp_send_json_error( array(
					'message' => __( 'An unexpected server error occurred during debugging. Please check your server error logs for more details.', 'wc-sc-debugger' ),
					'debug_info' => $e->getMessage(),
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
			// Filters related to coupon validation.
			add_filter( 'woocommerce_coupon_is_valid', array( $this, 'track_filter' ), 9999, 3 );
			add_filter( 'woocommerce_coupon_is_valid_for_product', array( $this, 'track_filter' ), 9999, 4 );
			add_filter( 'woocommerce_coupon_validate_expiry_date', array( $this, 'track_filter' ), 9999, 3 );
			add_filter( 'woocommerce_coupon_get_discount_amount', array( $this, 'track_filter' ), 9999, 5 );
			add_filter( 'woocommerce_apply_individual_use_coupon', array( $this, 'track_filter' ), 9999, 3 );
			add_filter( 'woocommerce_apply_with_individual_use_coupon', array( $this, 'track_filter' ), 9999, 4 );
			add_filter( 'wc_sc_validate_coupon_amount', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_is_send_coupon_email', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_is_coupon_restriction_available', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_percent_discount_types', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_coupon_type', array( $this, 'track_filter' ), 9999, 3 );
			add_filter( 'wc_sc_coupon_amount', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_generated_coupon_description', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_max_fields_to_show_in_coupon_description', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_max_restricted_category_names', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_max_restricted_product_names', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_generate_unique_coupon_code', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_coupon_code_allowed_characters', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_coupon_style_attributes', array( $this, 'track_filter' ), 9999, 1 );
			add_filter( 'wc_sc_coupon_container_classes', array( $this, 'track_filter' ), 9999, 1 );
			add_filter( 'wc_sc_coupon_content_classes', array( $this, 'track_filter' ), 9999, 1 );
			add_filter( 'wc_sc_coupon_design_thumbnail_src_set', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_coupon_design_thumbnail_src', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_storewide_offer_coupon_description', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_get_wc_sc_coupon_styles', array( $this, 'track_filter' ), 9999, 1 );
			add_filter( 'wc_sc_get_coupon_styles', array( $this, 'track_filter' ), 9999, 3 );
			add_filter( 'wc_sc_coupon_cookie_life', array( $this, 'track_filter' ), 9999, 1 );
			add_filter( 'wc_sc_is_generated_store_credit_includes_tax', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_read_price', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_write_price', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_after_get_post_meta', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_before_update_post_meta', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_after_get_session', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_before_set_session', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_after_get_order_item_meta', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_before_update_order_item_meta', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_pending_order_statuses', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'wc_sc_order_actions_to_ignore_for_email', array( $this, 'track_filter' ), 9999, 2 );
			add_filter( 'woocommerce_coupon_error', array( $this, 'track_filter' ), 9999, 3 );

			// Actions related to coupon processing.
			add_action( 'woocommerce_applied_coupon', array( $this, 'track_action' ), 9999, 1 );
			add_action( 'woocommerce_removed_coupon', array( $this, 'track_action' ), 9999, 1 );
			add_action( 'woocommerce_coupon_loaded', array( $this, 'track_action' ), 9999, 1 );
			add_action( 'woocommerce_before_calculate_totals', array( $this, 'track_action' ), 9999, 1 );
			add_action( 'woocommerce_after_calculate_totals', array( $this, 'track_action' ), 9999, 1 );
			add_action( 'wc_sc_new_coupon_generated', array( $this, 'track_action' ), 9999, 1 );
			add_action( 'smart_coupons_after_calculate_totals', array( $this, 'track_action' ), 9999, 0 );
			add_action( 'sc_after_order_calculate_discount_amount', array( $this, 'track_action' ), 9999, 1 );
		}

		/**
		 * Stop tracking hooks and filters.
		 */
		private function stop_hook_tracking() {
			remove_filter( 'woocommerce_coupon_is_valid', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'woocommerce_coupon_is_valid_for_product', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'woocommerce_coupon_validate_expiry_date', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'woocommerce_coupon_get_discount_amount', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'woocommerce_apply_individual_use_coupon', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'woocommerce_apply_with_individual_use_coupon', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_validate_coupon_amount', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_is_send_coupon_email', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_is_coupon_restriction_available', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_percent_discount_types', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_coupon_type', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_coupon_amount', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_generated_coupon_description', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_max_fields_to_show_in_coupon_description', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_max_restricted_category_names', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_max_restricted_product_names', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_generate_unique_coupon_code', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_coupon_code_allowed_characters', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_coupon_style_attributes', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_coupon_container_classes', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_coupon_content_classes', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_coupon_design_thumbnail_src_set', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_coupon_design_thumbnail_src', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_storewide_offer_coupon_description', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_get_wc_sc_coupon_styles', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_get_coupon_styles', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_coupon_cookie_life', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_is_generated_store_credit_includes_tax', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_read_price', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_write_price', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_after_get_post_meta', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_before_update_post_meta', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_after_get_session', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_before_set_session', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_after_get_order_item_meta', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_before_update_order_item_meta', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_pending_order_statuses', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'wc_sc_order_actions_to_ignore_for_email', array( $this, 'track_filter' ), 9999 );
			remove_filter( 'woocommerce_coupon_error', array( $this, 'track_filter' ), 9999 );

			remove_action( 'woocommerce_applied_coupon', array( $this, 'track_action' ), 9999 );
			remove_action( 'woocommerce_removed_coupon', array( $this, 'track_action' ), 9999 );
			remove_action( 'woocommerce_coupon_loaded', array( $this, 'track_action' ), 9999 );
			remove_action( 'woocommerce_before_calculate_totals', array( $this, 'track_action' ), 9999 );
			remove_action( 'woocommerce_after_calculate_totals', array( $this, 'track_action' ), 9999 );
			remove_action( 'wc_sc_new_coupon_generated', array( $this, 'track_action' ), 9999 );
			remove_action( 'smart_coupons_after_calculate_totals', array( $this, 'track_action' ), 9999 );
			remove_action( 'sc_after_order_calculate_discount_amount', array( $this, 'track_action' ), 9999 );
		}

		/**
		 * Callback for tracking filters.
		 *
		 * @param mixed $value The value being filtered.
		 * @param mixed ...$args Additional arguments passed to the filter.
		 * @return mixed The original value.
		 */
		public function track_filter( $value, ...$args ) {
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
			// Limit recursion depth to prevent memory exhaustion.
			if ( $depth > 5 ) {
				return '[Max Depth Reached]';
			}

			if ( is_object( $data ) ) {
				// Check for circular references.
				$hash = spl_object_hash( $data );
				if ( isset( $stack[ $hash ] ) ) {
					return sprintf( '[Circular Reference: %s]', get_class( $data ) );
				}
				$stack[ $hash ] = true;

				if ( method_exists( $data, 'to_array' ) ) {
					$result = $this->sanitize_for_logging( $data->to_array(), $depth + 1, $stack );
				} elseif ( $data instanceof WC_Coupon ) {
					$result = array(
						'type'        => 'WC_Coupon',
						'id'          => $data->get_id(),
						'code'        => $data->get_code(),
						'amount'      => $data->get_amount(),
						'discount_type' => $data->get_discount_type(),
					);
				} elseif ( $data instanceof WC_Product ) {
					$result = array(
						'type' => 'WC_Product',
						'id'   => $data->get_id(),
						'name' => $data->get_name(),
					);
				} elseif ( $data instanceof WC_Cart ) {
					$result = '[Object: WC_Cart]'; // Avoid deep serialization.
				} elseif ( $data instanceof WP_Error ) {
					$result = array(
						'type'    => 'WP_Error',
						'code'    => $data->get_error_code(),
						'message' => $data->get_error_message(),
					);
				} else {
					$result = sprintf( '[Object: %s]', get_class( $data ) );
				}

				unset( $stack[ $hash ] );
				return $result;
			} elseif ( is_array( $data ) ) {
				$sanitized_array = array();
				foreach ( $data as $key => $value ) {
					$sanitized_array[ $key ] = $this->sanitize_for_logging( $value, $depth + 1, $stack );
				}
				return $sanitized_array;
			} elseif ( is_resource( $data ) ) {
				return '[Resource]';
			}

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
			// Store original state.
			$original_cart_contents   = WC()->cart->get_cart_contents();
			$original_applied_coupons = WC()->cart->get_applied_coupons();
			$original_session_data    = WC()->session ? WC()->session->get_session_data() : array();
			$original_user_id         = get_current_user_id();
			// Explicitly back up notices to prevent state leakage and fatal errors.
			$original_notices = WC()->session ? WC()->session->get( 'wc_notices', array() ) : array();

			// Clear the current cart and session for a clean test.
			WC()->cart->empty_cart( true );
			if ( WC()->session ) {
				// empty_cart() handles cart contents and applied coupons session data.
				// We explicitly clear/reset other data points to ensure a clean slate for the test.
				WC()->session->set( 'wc_notices', array() ); // FIX: Prevent fatal error by ensuring notices are a clean array.
				WC()->session->set( 'sc_coupon_valid', null );
				WC()->session->set( 'sc_coupon_error', null );
				WC()->session->set( 'wc_sc_cart_smart_coupons', array() );
			}

			// Simulate user login if a user ID is provided.
			if ( $user_id > 0 && get_user_by( 'id', $user_id ) ) {
				wp_set_current_user( $user_id );
				self::log_message( 'info', sprintf( __( 'Simulating user ID: %d', 'wc-sc-debugger' ), $user_id ) );
			} else {
				wp_set_current_user( 0 ); // Simulate guest user.
				self::log_message( 'info', __( 'Simulating guest user.', 'wc-sc-debugger' ) );
			}

			// Add products to the cart.
			if ( ! empty( $product_ids ) ) {
				foreach ( $product_ids as $product_id ) {
					$product = wc_get_product( $product_id );
					if ( $product && $product->is_purchasable() ) {
						WC()->cart->add_to_cart( $product_id, 1 );
						self::log_message( 'info', sprintf( __( 'Added product to cart: %s (ID: %d)', 'wc-sc-debugger' ), $product->get_name(), $product_id ) );
					} else {
						self::log_message( 'warning', sprintf( __( 'Could not add product ID %d to cart (might not be purchasable or found).', 'wc-sc-debugger' ), $product_id ) );
					}
				}
			}

			// If cart is still empty, add a dummy product.
			if ( WC()->cart->is_empty() ) {
				$dummy_product_id = $this->get_or_create_dummy_product();
				if ( $dummy_product_id ) {
					WC()->cart->add_to_cart( $dummy_product_id, 1 );
					self::log_message( 'info', sprintf( __( 'Cart was empty, added dummy product (ID: %d) for testing general coupons.', 'wc-sc-debugger' ), $dummy_product_id ) );
				} else {
					self::log_message( 'error', __( 'Could not create or find a dummy product for testing, and cart is empty.', 'wc-sc-debugger' ) );
					return new WP_Error( 'no_dummy_product', __( 'Cannot proceed without a product in cart for testing.', 'wc-sc-debugger' ) );
				}
			}

			WC()->cart->calculate_totals();

			self::log_message( 'info', sprintf( __( 'Attempting to apply coupon: "%s"', 'wc-sc-debugger' ), $coupon_code ) );

			$coupon_applied = false;
			try {
				$coupon_applied = WC()->cart->apply_coupon( $coupon_code );

				if ( is_wp_error( $coupon_applied ) ) {
					self::log_message( 'error', sprintf( __( 'Failed to apply coupon: %s', 'wc-sc-debugger' ), $coupon_applied->get_error_message() ) );
					$coupon_applied = false;
				} elseif ( ! $coupon_applied ) {
					self::log_message( 'error', __( 'Coupon could not be applied. Check coupon validity and restrictions.', 'wc-sc-debugger' ) );
				} else {
					WC()->cart->calculate_totals();
					$cart_total = WC()->cart->get_total( 'edit' );
					self::log_message( 'success', sprintf( __( 'New Cart Total: %s', 'wc-sc-debugger' ), wc_price( $cart_total ) ) );
				}
			} catch ( Exception $e ) {
				self::log_message( 'error', sprintf( __( 'An exception occurred during coupon application: %s', 'wc-sc-debugger' ), $e->getMessage() ) );
				$coupon_applied = false;
			}

			// Restore original state.
			WC()->cart->empty_cart( true );
			foreach ( $original_cart_contents as $cart_item_key => $cart_item ) {
				$product_to_restore = wc_get_product( $cart_item['product_id'] );
				if ( $product_to_restore && $product_to_restore->exists() ) {
					WC()->cart->add_to_cart( $cart_item['product_id'], $cart_item['quantity'], $cart_item['variation_id'], $cart_item['variation'], $cart_item['data'] ?? array() );
				}
			}

			// Restore session data using the correct modern method.
			if ( WC()->session ) {
				foreach ( $original_session_data as $key => $value ) {
					WC()->session->set( $key, $value );
				}
				// After restoring the session, make sure our backed-up notices are in place,
				// as session restoration can sometimes be imperfect with complex data types.
				WC()->session->set( 'wc_notices', $original_notices );
			}

			// Restore applied coupons.
			foreach ( $original_applied_coupons as $applied_coupon_code ) {
				WC()->cart->apply_coupon( $applied_coupon_code );
			}

			wp_set_current_user( $original_user_id );
			WC()->cart->calculate_totals();

			return $coupon_applied;
		}

		/**
		 * Get or create a simple dummy product for testing purposes.
		 *
		 * @return int|bool Product ID on success, false on failure.
		 */
		private function get_or_create_dummy_product() {
			$product_id = get_option( 'wc_sc_debugger_dummy_product_id' );

			if ( $product_id && ( $product = wc_get_product( $product_id ) ) && $product->exists() ) {
				return $product_id;
			}

			$new_product = new WC_Product_Simple();
			$new_product->set_name( 'Debugger Dummy Product' );
			$new_product->set_status( 'private' );
			$new_product->set_catalog_visibility( 'hidden' );
			$new_product->set_price( 100 );
			$new_product->set_regular_price( 100 );
			$new_product->set_manage_stock( false );
			$new_product->set_virtual( true );

			$new_product_id = $new_product->save();

			if ( $new_product_id ) {
				update_option( 'wc_sc_debugger_dummy_product_id', $new_product_id );
				return $new_product_id;
			}

			return false;
		}

	} // End class WC_SC_Debugger.

	// Initialize the plugin.
	WC_SC_Debugger::get_instance();

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
		delete_option( 'wc_sc_debugger_dummy_product_id' );
	}

} // End if class_exists.