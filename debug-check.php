<?php
/**
 * WC SC Debugger - Debug Check Script
 * 
 * This file helps diagnose issues with the plugin on different environments.
 * Access this file directly via: your-site.com/wp-content/plugins/KISS-woo-coupon-debugger/debug-check.php
 */

// Prevent direct access in production - remove this check for debugging
if (!defined('ABSPATH')) {
    // For debugging purposes, we'll allow direct access
    // In production, you should uncomment the line below:
    // exit('Direct access not allowed');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>WC SC Debugger - Environment Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .check { margin: 10px 0; padding: 10px; border-radius: 5px; }
        .pass { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .fail { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>WC SC Debugger - Environment Check</h1>
    <p>This page helps diagnose issues with the plugin. Check the results below:</p>

    <?php
    // Check 1: PHP Version
    $php_version = PHP_VERSION;
    $php_ok = version_compare($php_version, '7.4', '>=');
    ?>
    <div class="check <?php echo $php_ok ? 'pass' : 'fail'; ?>">
        <strong>PHP Version:</strong> <?php echo $php_version; ?>
        <?php echo $php_ok ? '✓ Compatible' : '✗ Requires PHP 7.4+'; ?>
    </div>

    <?php
    // Check 2: WordPress
    if (function_exists('get_bloginfo')) {
        $wp_version = get_bloginfo('version');
        echo '<div class="check pass"><strong>WordPress:</strong> ' . $wp_version . ' ✓ Loaded</div>';
    } else {
        echo '<div class="check fail"><strong>WordPress:</strong> ✗ Not loaded</div>';
    }
    ?>

    <?php
    // Check 3: Plugin files
    $plugin_dir = __DIR__;
    $files_to_check = [
        'kiss-coupon-debugger.php',
        'assets/js/admin.js',
        'assets/css/admin.css',
        'src/Admin/AdminUI.php',
        'src/Settings/SettingsRepository.php'
    ];

    foreach ($files_to_check as $file) {
        $file_path = $plugin_dir . '/' . $file;
        $exists = file_exists($file_path);
        $size = $exists ? filesize($file_path) : 0;
        $modified = $exists ? date('Y-m-d H:i:s', filemtime($file_path)) : 'N/A';
        
        echo '<div class="check ' . ($exists ? 'pass' : 'fail') . '">';
        echo '<strong>File:</strong> ' . $file . ' ';
        echo $exists ? '✓ Exists' : '✗ Missing';
        if ($exists) {
            echo ' (Size: ' . $size . ' bytes, Modified: ' . $modified . ')';
        }
        echo '</div>';
    }
    ?>

    <?php
    // Check 4: URL accessibility
    $js_url = plugins_url('assets/js/admin.js', __FILE__);
    $css_url = plugins_url('assets/css/admin.css', __FILE__);
    ?>
    <div class="check info">
        <strong>Asset URLs:</strong><br>
        JS: <a href="<?php echo $js_url; ?>" target="_blank"><?php echo $js_url; ?></a><br>
        CSS: <a href="<?php echo $css_url; ?>" target="_blank"><?php echo $css_url; ?></a><br>
        <small>Click these links to verify files are accessible via HTTP</small>
    </div>

    <?php
    // Check 5: WordPress constants
    $constants = ['WP_DEBUG', 'WP_DEBUG_LOG', 'SCRIPT_DEBUG'];
    echo '<div class="check info"><strong>WordPress Debug Settings:</strong><br>';
    foreach ($constants as $constant) {
        $value = defined($constant) ? (constant($constant) ? 'true' : 'false') : 'undefined';
        echo $constant . ': ' . $value . '<br>';
    }
    echo '</div>';
    ?>

    <?php
    // Check 6: Server info
    echo '<div class="check info">';
    echo '<strong>Server Information:</strong><br>';
    echo 'Server Software: ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . '<br>';
    echo 'Document Root: ' . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . '<br>';
    echo 'Plugin Directory: ' . $plugin_dir . '<br>';
    echo 'Current Time: ' . date('Y-m-d H:i:s') . '<br>';
    echo '</div>';
    ?>

    <div class="check info">
        <strong>Next Steps:</strong><br>
        1. Verify all files show as "✓ Exists" above<br>
        2. Click the asset URLs to ensure they're accessible<br>
        3. Check your browser's Network tab for 404 errors<br>
        4. Check WordPress error logs for PHP errors<br>
        5. Compare file modification times between local and remote
    </div>

    <script>
        console.log('WC SC Debugger: Debug check page loaded');
        console.log('WC SC Debugger: Current URL:', window.location.href);
        console.log('WC SC Debugger: User Agent:', navigator.userAgent);
    </script>
</body>
</html>
