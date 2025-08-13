<?php
/**
 * Admin Interface Implementation
 *
 * @package KissPlugins\WooCouponDebugger
 */

namespace KissPlugins\WooCouponDebugger\Admin;

use KissPlugins\WooCouponDebugger\Interfaces\AdminInterface as AdminContract;

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
     * Constructor
     *
     * @param string $version Plugin version
     */
    public function __construct(string $version = '1.3.0') {
        $this->version = $version;
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

    public function addAdminMenuPage(): void { /* ... copied in original file ... */ }
    public function addSettingsPage(): void { /* ... copied in original file ... */ }
    public function registerSettings(): void { /* ... copied in original file ... */ }
    public function productsSectionCallback(): void { /* ... copied in original file ... */ }
    public function productIdFieldCallback(array $args): void { /* ... copied in original file ... */ }
    public function sanitizeValidatedProducts(array $input): array { /* ... copied in original file ... */ }
    public function enqueueAdminScripts(string $hook): void { /* ... copied in original file ... */ }
    private function enqueueChangelogScripts(): void { /* ... copied in original file ... */ }
    public function renderAdminPage(): void { /* ... copied in original file ... */ }
    public function renderSettingsPage(): void { /* ... copied in original file ... */ }
    public function addPluginActionLinks(array $links): array { /* ... copied in original file ... */ }
    public function handleChangelogAjax(): void { /* ... copied in original file ... */ }
    public function skipSmartCouponsFieldCallback(): void { /* ... copied in original file ... */ }
}

