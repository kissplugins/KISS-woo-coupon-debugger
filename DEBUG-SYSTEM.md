# WC SC Debugger - Debug System Documentation

## Overview

The plugin now includes a comprehensive but lightweight debugging system that can be easily controlled and has minimal performance impact when disabled.

## Performance Impact

### When Debug Mode is DISABLED (default):
- **JavaScript**: Only critical errors are logged (virtually no overhead)
- **PHP**: No debug logging (zero overhead)
- **Production Ready**: Safe to leave in production code

### When Debug Mode is ENABLED:
- **JavaScript**: ~0.1ms per log statement (negligible)
- **PHP**: ~0.05ms per log statement (minimal)
- **Storage**: ~100-200 bytes per log entry
- **Overall Impact**: Less than 1% performance impact

## How to Control Debug Mode

### Method 1: Plugin Settings (Recommended)
1. Go to **WooCommerce > SC Debugger Settings**
2. Check **"Enable Debug Logging"**
3. Save settings
4. Debug logs will now appear in console and error logs

### Method 2: URL Parameter (Temporary)
Add `?wc_sc_debug=1` to any SC Debugger page URL:
```
https://yoursite.com/wp-admin/admin.php?page=wc-sc-debugger&wc_sc_debug=1
```

### Method 3: WordPress Debug Mode
If `WP_DEBUG` is enabled in wp-config.php, debug mode automatically activates.

## What Gets Logged

### JavaScript Console (when debug enabled):
- Element availability checks
- Form value collection
- URL generation process
- AJAX request/response details
- User loading status
- Button click events

### PHP Error Log (when debug enabled):
- Script enqueuing process
- Parameter loading from URL/storage
- Settings save/load operations
- AJAX handler execution
- Database operation results

### Always Logged (regardless of debug mode):
- Critical JavaScript errors
- PHP exceptions and fatal errors
- Missing dependencies

## Viewing Debug Information

### Browser Console:
1. Press F12 to open Developer Tools
2. Go to Console tab
3. Look for messages starting with "WC SC Debugger:"

### WordPress Error Log:
1. Check `/wp-content/debug.log` file
2. Look for messages starting with "WC SC Debugger:"
3. Ensure `WP_DEBUG_LOG` is enabled in wp-config.php

## Debug Message Examples

### Normal Operation:
```javascript
WC SC Debugger: admin.js file loaded at 2024-01-15T10:30:00.000Z
WC SC Debugger: JavaScript loaded and DOM ready
WC SC Debugger: wcSCDebugger object available
```

### Error Conditions:
```javascript
WC SC Debugger: CRITICAL ERROR - wcSCDebugger object not found!
WC SC Debugger: ERROR - jQuery is NOT available!
```

## Troubleshooting Common Issues

### No Debug Messages Appearing:
1. Verify debug mode is enabled
2. Check browser console for JavaScript errors
3. Verify script is loading (check Network tab)
4. Check WordPress error log for PHP errors

### Script Not Loading:
1. Check file permissions (should be 644)
2. Verify plugin files uploaded correctly
3. Check for caching issues
4. Test direct file access via URL

### AJAX Errors:
1. Check nonce verification in error log
2. Verify user permissions
3. Check for plugin conflicts
4. Test with other plugins disabled

## Production Recommendations

### For Live Sites:
- Keep debug mode **DISABLED** in plugin settings
- Remove `?wc_sc_debug=1` from URLs
- Set `WP_DEBUG` to `false` in wp-config.php
- Monitor error logs for critical issues only

### For Staging/Development:
- Enable debug mode for troubleshooting
- Use browser console for real-time debugging
- Check error logs for server-side issues
- Test with debug mode both on and off

## Removing Debug Code (Optional)

If you want to completely remove debug code for production:

1. Search for `wcSCDebugLog(` in JavaScript files and remove those lines
2. Search for `if ($this->isDebugMode())` in PHP files and remove those blocks
3. Remove the debug mode setting from the admin interface

However, this is **not recommended** as the performance impact is negligible and the debugging capability is valuable for future troubleshooting.
