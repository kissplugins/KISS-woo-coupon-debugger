<?php
/**
 * Admin Interface Implementation
 *
 * @package KissPlugins\WooCouponDebugger
 */

namespace KissPlugins\WooCouponDebugger\Admin;

use KissPlugins\WooCouponDebugger\Interfaces\AdminInterface as AdminContract;
use KissPlugins\WooCouponDebugger\Interfaces\SettingsRepositoryInterface;

/**
 * Handles admin interface and pages
 */
class AdminUI implements AdminContract {

    /**
     * Plugin version
     *
     * @var string
     */
    private $version;

    /**
     * Settings repository
     *
     * @var SettingsRepositoryInterface
     */
    private $settings;

    /**
     * Constructor
     *
     * @param string $version Plugin version
     * @param SettingsRepositoryInterface $settings Settings repository
     */
    public function __construct(string $version = '1.3.0', SettingsRepositoryInterface $settings = null) {
        $this->version = $version;
        $this->settings = $settings;
    }

    /**
     * Initialize admin hooks
     *
     * @return void
     */
    public function init(): void {
        add_action('admin_menu', [$this, 'addAdminMenuPage']);
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);

        // Add plugin action links
        add_filter('plugin_action_links_' . plugin_basename(WC_SC_DEBUGGER_PLUGIN_FILE), [$this, 'addPluginActionLinks']);

        // Add changelog modal handler
        add_action('wp_ajax_wc_sc_debugger_view_changelog', [$this, 'handleChangelogAjax']);
    }

    public function addAdminMenuPage(): void {
        add_submenu_page(
            'woocommerce',
            __('SC Debugger', 'wc-sc-debugger'),
            __('SC Debugger', 'wc-sc-debugger'),
            'manage_woocommerce',
            'wc-sc-debugger',
            [$this, 'renderAdminPage']
        );
    }

    public function addSettingsPage(): void {
        add_submenu_page(
            'woocommerce',
            __('SC Debugger Settings', 'wc-sc-debugger'),
            __('SC Debugger Settings', 'wc-sc-debugger'),
            'manage_woocommerce',
            'wc-sc-debugger-settings',
            [$this, 'renderSettingsPage']
        );
    }

    public function registerSettings(): void {
        register_setting(
            'wc_sc_debugger_options_group',
            'wc_sc_debugger_validated_products',
            [$this, 'sanitizeValidatedProducts']
        );

        register_setting(
            'wc_sc_debugger_options_group',
            'wc_sc_debugger_skip_smart_coupons',
            function($val){ return (int) (bool) $val; }
        );

        add_settings_section(
            'wc_sc_debugger_products_section',
            __('Pre-define Products for Testing', 'wc-sc-debugger'),
            [$this, 'productsSectionCallback'],
            'wc-sc-debugger-settings'
        );

        add_settings_section(
            'wc_sc_debugger_behavior_section',
            __('Behavior', 'wc-sc-debugger'),
            function(){ echo '<p>' . esc_html__('Control how the debugger behaves around known third-party issues.', 'wc-sc-debugger') . '</p>'; },
            'wc-sc-debugger-settings'
        );

        add_settings_field(
            'wc_sc_debugger_skip_smart_coupons',
            __('Skip Smart Coupons stack (simulate)', 'wc-sc-debugger'),
            [$this, 'skipSmartCouponsFieldCallback'],
            'wc-sc-debugger-settings',
            'wc_sc_debugger_behavior_section',
            [ 'label_for' => 'wc_sc_debugger_skip_smart_coupons' ]
        );

        for ($i = 1; $i <= 3; $i++) {
            add_settings_field(
                'wc_sc_debugger_product_id_' . $i,
                sprintf(__('Product ID %d', 'wc-sc-debugger'), $i),
                [$this, 'productIdFieldCallback'],
                'wc-sc-debugger-settings',
                'wc_sc_debugger_products_section',
                [
                    'label_for' => 'wc_sc_debugger_product_id_' . $i,
                    'field_id'  => $i,
                ]
            );
        }
    }
    public function productsSectionCallback(): void {
        echo '<p>' . esc_html__('Enter up to 3 product IDs that you frequently use for testing. These will be validated and available for selection on the main debugger page.', 'wc-sc-debugger') . '</p>';
    }

    public function productIdFieldCallback(array $args): void {
        $options = get_option('wc_sc_debugger_validated_products', []);
        $product_id = isset($options['product_id_' . $args['field_id']]['id']) ? $options['product_id_' . $args['field_id']]['id'] : '';
        $product_name = isset($options['product_id_' . $args['field_id']]['name']) ? $options['product_id_' . $args['field_id']]['name'] : '';
        $validation_message = isset($options['product_id_' . $args['field_id']]['message']) ? $options['product_id_' . $args['field_id']]['message'] : '';
        $validation_type = isset($options['product_id_' . $args['field_id']]['type']) ? $options['product_id_' . $args['field_id']]['type'] : '';

        printf(
            '<input type="text" id="%1$s" name="wc_sc_debugger_validated_products[product_id_%2$s][id]" value="%3$s" class="regular-text" placeholder="%4$s" />',
            esc_attr($args['label_for']),
            esc_attr($args['field_id']),
            esc_attr($product_id),
            esc_attr__('Enter Product ID', 'wc-sc-debugger')
        );

        if (!empty($product_name)) {
            printf('<p class="description">%s: <strong>%s</strong></p>', esc_html__('Current Product', 'wc-sc-debugger'), esc_html($product_name));
        }

        if (!empty($validation_message)) {
            printf('<p class="description validation-message %s">%s</p>', esc_attr($validation_type), esc_html($validation_message));
        }
    }

    public function sanitizeValidatedProducts(array $input): array {
        $new_options = [];
        for ($i = 1; $i <= 3; $i++) {
            $field_key = 'product_id_' . $i;
            $id = isset($input[$field_key]['id']) ? absint($input[$field_key]['id']) : 0;
            $product_name = '';
            $message = '';
            $type = '';
            if ($id > 0) {
                $product = wc_get_product($id);
                if ($product && $product->exists()) {
                    $product_name = $product->get_name();
                    $message = sprintf(__('Product found: %s', 'wc-sc-debugger'), $product_name);
                    $type = 'success';
                } else {
                    $message = sprintf(__('Product ID %d not found or is invalid.', 'wc-sc-debugger'), $id);
                    $type = 'error';
                    $id = 0; // Clear invalid ID
                }
            } else {
                $message = __('No product ID entered or ID is zero.', 'wc-sc-debugger');
                $type = 'info';
            }

            $new_options[$field_key] = [
                'id'      => $id,
                'name'    => $product_name,
                'message' => $message,
                'type'    => $type,
            ];
        }
        return $new_options;
    }

    public function enqueueAdminScripts(string $hook): void {
        // Enqueue changelog scripts on plugins page
        global $pagenow;
        if ('plugins.php' === $pagenow) {
            $this->enqueueChangelogScripts();
            return;
        }

        if ('woocommerce_page_wc-sc-debugger' !== $hook && 'woocommerce_page_wc-sc-debugger-settings' !== $hook) {
            return;
        }

        wp_enqueue_script('selectWoo');
        wp_enqueue_style('select2');

        wp_enqueue_script(
            'wc-sc-debugger-admin',
            plugins_url('assets/js/admin.js', WC_SC_DEBUGGER_PLUGIN_FILE),
            ['jquery', 'selectWoo'],
            $this->version,
            true
        );

        $localize_data = [
            'ajax_url'               => admin_url('admin-ajax.php'),
            'debug_coupon_nonce'     => wp_create_nonce('wc-sc-debug-coupon-nonce'),
            'search_customers_nonce' => wp_create_nonce('search-customers'),
            'admin_url'              => admin_url('admin.php?page=wc-sc-debugger'),
        ];

        // Debug: Log the localized data
        error_log('WC SC Debugger: Localizing script data: ' . print_r($localize_data, true));

        wp_localize_script(
            'wc-sc-debugger-admin',
            'wcSCDebugger',
            $localize_data
        );

        wp_enqueue_style(
            'wc-sc-debugger-admin',
            plugins_url('assets/css/admin.css', WC_SC_DEBUGGER_PLUGIN_FILE),
            [],
            $this->version
        );
    }

    private function enqueueChangelogScripts(): void {
        wp_enqueue_script(
            'wc-sc-debugger-changelog',
            plugins_url('assets/js/changelog.js', WC_SC_DEBUGGER_PLUGIN_FILE),
            ['jquery'],
            $this->version,
            true
        );

        wp_localize_script(
            'wc-sc-debugger-changelog',
            'wcSCDebuggerChangelog',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'strings' => [
                    'changelog_title' => __('KISS Woo Coupon Debugger - Changelog', 'wc-sc-debugger'),
                    'loading' => __('Loading changelog...', 'wc-sc-debugger'),
                    'error' => __('Error loading changelog. Please try again.', 'wc-sc-debugger'),
                    'close' => __('Close', 'wc-sc-debugger'),
                ]
            ]
        );

        wp_enqueue_style(
            'wc-sc-debugger-changelog',
            plugins_url('assets/css/changelog.css', WC_SC_DEBUGGER_PLUGIN_FILE),
            [],
            $this->version
        );
    }

    public function renderAdminPage(): void {
        $validated_products = get_option('wc_sc_debugger_validated_products', []);

        // Get parameters from URL or last used parameters
        $params = $this->getPageParameters();

        ?>
        <div class="wrap woocommerce">
            <h1><?php esc_html_e('WooCommerce Smart Coupons Debugger', 'wc-sc-debugger'); ?></h1>

            <div class="wc-sc-debugger-container">
                <div class="wc-sc-debugger-form">
                    <!-- URL Sharing Section -->
                    <div class="form-field url-sharing-section">
                        <label><?php esc_html_e('Share Configuration:', 'wc-sc-debugger'); ?></label>
                        <div class="url-sharing-controls">
                            <button id="generate_url" class="button button-secondary" type="button">
                                <?php esc_html_e('Generate Shareable URL', 'wc-sc-debugger'); ?>
                            </button>
                            <button id="clear_all_settings" class="button button-secondary" type="button">
                                <?php esc_html_e('Clear All Settings', 'wc-sc-debugger'); ?>
                            </button>
                        </div>
                        <div id="generated_url_container" style="display: none;">
                            <input type="text" id="generated_url" class="regular-text" readonly />
                            <button id="copy_url" class="button button-secondary" type="button">
                                <?php esc_html_e('Copy URL', 'wc-sc-debugger'); ?>
                            </button>
                        </div>
                        <p class="description"><?php esc_html_e('Generate a URL with current settings that can be shared or bookmarked.', 'wc-sc-debugger'); ?></p>
                    </div>

                    <div class="form-field">
                        <label for="coupon_code"><?php esc_html_e('Coupon Code:', 'wc-sc-debugger'); ?></label>
                        <input type="text" id="coupon_code" name="coupon_code" placeholder="<?php esc_attr_e('Enter coupon code', 'wc-sc-debugger'); ?>" class="regular-text" value="<?php echo esc_attr($params['coupon_code']); ?>" />
                    </div>

                    <div class="form-field">
                        <label for="debug_products_select"><?php esc_html_e('Select Product for Testing (optional):', 'wc-sc-debugger'); ?></label>
                        <select id="debug_products_select" name="debug_products_select" class="regular-text">
                            <option value=""><?php esc_html_e('No specific product', 'wc-sc-debugger'); ?></option>
                            <?php
                            foreach ((array) $validated_products as $key => $product_data) {
                                if (!empty($product_data['id'])) {
                                    $selected = ($params['product_id'] == $product_data['id']) ? 'selected' : '';
                                    printf(
                                        '<option value="%d" %s>%s (ID: %d)</option>',
                                        esc_attr($product_data['id']),
                                        $selected,
                                        esc_html($product_data['name']),
                                        esc_html($product_data['id'])
                                    );
                                }
                            }
                            ?>
                        </select>
                        <p class="description"><?php esc_html_e('Choose a pre-defined product to test coupon compatibility. Define products in the ', 'wc-sc-debugger'); ?><a href="<?php echo esc_url(admin_url('admin.php?page=wc-sc-debugger-settings')); ?>"><?php esc_html_e('SC Debugger Settings', 'wc-sc-debugger'); ?></a> <?php esc_html_e('page.', 'wc-sc-debugger'); ?></p>
                    </div>

                    <div class="form-field">
                        <label for="debug_user"><?php esc_html_e('Select User (optional):', 'wc-sc-debugger'); ?></label>
                        <select id="debug_user" name="debug_user" class="wc-customer-search" data-placeholder="<?php esc_attr_e('Search for a customer&hellip;', 'wc-sc-debugger'); ?>" data-action="woocommerce_json_search_customers" data-selected-user-id="<?php echo esc_attr($params['user_id']); ?>"></select>
                        <p class="description"><?php esc_html_e('Select a user to test user-specific coupon restrictions (e.g., "for new user only"). Leave empty for guest user.', 'wc-sc-debugger'); ?></p>
                    </div>

                        <div class="field-group">
                            <label>
                                <input type="checkbox" id="skip_smart_coupons" <?php checked($params['skip_smart_coupons']); ?> />
                                <?php esc_html_e('Skip Smart Coupons stack (simulate)', 'wc-sc-debugger'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('If Smart Coupons triggers PHP 8+ errors, skip its discount application and simulate discount so other constraints can still be evaluated.', 'wc-sc-debugger'); ?></p>
                        </div>

                    <button id="run_debug" class="button button-primary"><?php esc_html_e('Run Debug', 'wc-sc-debugger'); ?></button>
                    <button id="clear_debug" class="button button-secondary"><?php esc_html_e('Clear Output', 'wc-sc-debugger'); ?></button>
                </div>

                <div class="wc-sc-debugger-output">
                    <h2><?php esc_html_e('Debugging Output', 'wc-sc-debugger'); ?></h2>
                    <div id="debug_results" class="debug-results">
                        <p><?php esc_html_e('Enter a coupon code and click "Run Debug" to see the processing details.', 'wc-sc-debugger'); ?></p>
                    </div>
                    <div class="loading-indicator" style="display: none;">
                        <p><?php esc_html_e('Debugging in progress...', 'wc-sc-debugger'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get parameters from URL or last used parameters
     *
     * @return array
     */
    private function getPageParameters(): array {
        // Default parameters
        $params = [
            'coupon_code' => '',
            'product_id' => 0,
            'user_id' => 0,
            'skip_smart_coupons' => false,
        ];

        // Debug: Log incoming GET parameters
        error_log('WC SC Debugger: Incoming GET parameters: ' . print_r($_GET, true));

        // Check for URL parameters first
        if (isset($_GET['coupon_code'])) {
            $params['coupon_code'] = sanitize_text_field(wp_unslash($_GET['coupon_code']));
            error_log('WC SC Debugger: Found coupon_code in URL: ' . $params['coupon_code']);
        }
        if (isset($_GET['product_id'])) {
            $params['product_id'] = absint($_GET['product_id']);
            error_log('WC SC Debugger: Found product_id in URL: ' . $params['product_id']);
        }
        if (isset($_GET['user_id'])) {
            $params['user_id'] = absint($_GET['user_id']);
            error_log('WC SC Debugger: Found user_id in URL: ' . $params['user_id']);
        }
        if (isset($_GET['skip_smart_coupons'])) {
            $params['skip_smart_coupons'] = (bool) $_GET['skip_smart_coupons'];
            error_log('WC SC Debugger: Found skip_smart_coupons in URL: ' . ($params['skip_smart_coupons'] ? 'true' : 'false'));
        }

        // If no URL parameters and we have settings repository, load last used parameters
        if (empty($params['coupon_code']) && empty($params['product_id']) && empty($params['user_id']) && $this->settings) {
            error_log('WC SC Debugger: No URL parameters found, checking last used parameters');
            $last_used = $this->settings->getLastUsedParams();
            error_log('WC SC Debugger: Last used parameters: ' . print_r($last_used, true));
            if (!empty($last_used)) {
                $params = array_merge($params, $last_used);
                error_log('WC SC Debugger: Merged parameters: ' . print_r($params, true));
            }
        }

        error_log('WC SC Debugger: Final parameters: ' . print_r($params, true));
        return $params;
    }

    public function renderSettingsPage(): void {
        ?>
        <div class="wrap woocommerce">
            <h1><?php esc_html_e('WooCommerce Smart Coupons Debugger Settings', 'wc-sc-debugger'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wc_sc_debugger_options_group');
                do_settings_sections('wc-sc-debugger-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function addPluginActionLinks(array $links): array {
        $custom_links = [
            'settings' => sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('admin.php?page=wc-sc-debugger-settings')),
                esc_html__('Settings', 'wc-sc-debugger')
            ),
            'debugger' => sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('admin.php?page=wc-sc-debugger')),
                esc_html__('Debugger', 'wc-sc-debugger')
            ),
            'changelog' => sprintf(
                '<a href="#" class="wc-sc-debugger-changelog-link" data-nonce="%s">%s</a>',
                wp_create_nonce('wc_sc_debugger_changelog'),
                esc_html__('Changelog', 'wc-sc-debugger')
            ),
        ];

        return array_merge($custom_links, $links);
    }

    public function handleChangelogAjax(): void {
        // Verify nonce
        check_ajax_referer('wc_sc_debugger_changelog', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to view this content.', 'wc-sc-debugger')]);
        }

        $changelog_file = WC_SC_DEBUGGER_PLUGIN_DIR . 'CHANGELOG.md';

        // Use the kiss_mdv_render_file function if available, otherwise fallback
        if (function_exists('kiss_mdv_render_file')) {
            $html = kiss_mdv_render_file($changelog_file);
        } else {
            // Fallback to plain text rendering
            if (file_exists($changelog_file)) {
                $content = file_get_contents($changelog_file);
                $html = '<pre style="white-space: pre-wrap; word-wrap: break-word; max-height: 500px; overflow-y: auto; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">' . esc_html($content) . '</pre>';
            } else {
                $html = '<p>' . esc_html__('Changelog file not found.', 'wc-sc-debugger') . '</p>';
            }
        }

        wp_send_json_success(['html' => $html]);
    }

    public function skipSmartCouponsFieldCallback(): void {
        $val = (int) get_option('wc_sc_debugger_skip_smart_coupons', 0);
        echo '<label><input type="checkbox" id="wc_sc_debugger_skip_smart_coupons" name="wc_sc_debugger_skip_smart_coupons" value="1" ' . checked(1, $val, false) . ' /> ' . esc_html__('If Smart Coupons throws PHP 8+ errors, skip its stack and simulate discount heuristically.', 'wc-sc-debugger') . '</label>';
    }

}

