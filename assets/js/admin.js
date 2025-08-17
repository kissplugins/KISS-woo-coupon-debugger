jQuery(document).ready(function($) {
    console.log('WC SC Debugger: JavaScript loaded and DOM ready');

    var $couponCodeInput = $('#coupon_code');
    var $debugProductsSelect = $('#debug_products_select');
    var $debugUserSelect = $('#debug_user');
    var $runDebugButton = $('#run_debug');
    var $skipSmartCoupons = $('#skip_smart_coupons');
    var $clearDebugButton = $('#clear_debug');
    var $debugResults = $('#debug_results');
    var $loadingIndicator = $('.loading-indicator');

    // New elements for URL sharing and settings management
    var $generateUrlButton = $('#generate_url');
    var $clearAllSettingsButton = $('#clear_all_settings');
    var $generatedUrlContainer = $('#generated_url_container');
    var $generatedUrlInput = $('#generated_url');
    var $copyUrlButton = $('#copy_url');

    // Debug: Check if all elements are found
    console.log('WC SC Debugger: Element availability check:', {
        'couponCodeInput': $couponCodeInput.length,
        'debugProductsSelect': $debugProductsSelect.length,
        'debugUserSelect': $debugUserSelect.length,
        'runDebugButton': $runDebugButton.length,
        'skipSmartCoupons': $skipSmartCoupons.length,
        'clearDebugButton': $clearDebugButton.length,
        'debugResults': $debugResults.length,
        'loadingIndicator': $loadingIndicator.length,
        'generateUrlButton': $generateUrlButton.length,
        'clearAllSettingsButton': $clearAllSettingsButton.length,
        'generatedUrlContainer': $generatedUrlContainer.length,
        'generatedUrlInput': $generatedUrlInput.length,
        'copyUrlButton': $copyUrlButton.length
    });

    // Debug: Check wcSCDebugger object
    if (typeof wcSCDebugger !== 'undefined') {
        console.log('WC SC Debugger: wcSCDebugger object available:', wcSCDebugger);
    } else {
        console.error('WC SC Debugger: wcSCDebugger object not found! This may indicate a script loading issue.');
    }

    // The product dropdown is a standard select, so it doesn't need SelectWoo initialization.
    // We only initialize SelectWoo for the customer search, which uses AJAX.
    $debugUserSelect.selectWoo({
        ajax: {
            url: wcSCDebugger.ajax_url,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    term: params.term, // search term
                    action: 'woocommerce_json_search_customers',
                    security: wcSCDebugger.search_customers_nonce
                };
            },
            processResults: function (data) {
                var options = [];
                if (data) {
                    $.each(data, function (id, text) {
                        options.push({ id: id, text: text });
                    });
                }
                return {
                    results: options
                };
            },
            cache: true
        },
        minimumInputLength: 1, // Start search after 1 character
        allowClear: true, // Allow clearing the selection
        placeholder: $(this).data('placeholder'),
        escapeMarkup: function (markup) { return markup; }
    });

    // Load pre-selected user if specified
    var selectedUserId = $debugUserSelect.data('selected-user-id');
    console.log('WC SC Debugger: Selected user ID from data attribute:', selectedUserId);

    if (selectedUserId && selectedUserId > 0) {
        console.log('WC SC Debugger: Loading pre-selected user with ID:', selectedUserId);

        // Make AJAX call to get user details
        $.ajax({
            url: wcSCDebugger.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'woocommerce_json_search_customers',
                term: '',
                security: wcSCDebugger.search_customers_nonce,
                include: [selectedUserId]
            },
            beforeSend: function() {
                console.log('WC SC Debugger: Making AJAX request to load user details');
            },
            success: function(data) {
                console.log('WC SC Debugger: User search response:', data);

                if (data && data[selectedUserId]) {
                    var option = new Option(data[selectedUserId], selectedUserId, true, true);
                    $debugUserSelect.append(option).trigger('change');
                    console.log('WC SC Debugger: User option added and selected:', data[selectedUserId]);
                } else {
                    console.warn('WC SC Debugger: User not found in response data');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('WC SC Debugger: Error loading user details:', {
                    textStatus: textStatus,
                    errorThrown: errorThrown,
                    status: jqXHR.status,
                    statusText: jqXHR.statusText,
                    responseText: jqXHR.responseText
                });
            }
        });
    } else {
        console.log('WC SC Debugger: No pre-selected user to load');
    }

    $runDebugButton.on('click', function() {
        var couponCode = $couponCodeInput.val().trim();
        var productId = $debugProductsSelect.val();
        var userId = $debugUserSelect.val();

        if (!couponCode) {
            alert('Please enter a coupon code to debug.');
            return;
        }

        $debugResults.empty();
        $loadingIndicator.show();
        $runDebugButton.prop('disabled', true);

        $.ajax({
            url: wcSCDebugger.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_sc_debug_coupon',
                security: wcSCDebugger.debug_coupon_nonce,
                coupon_code: couponCode,
                product_id: productId,
                user_id: userId,
                skip_smart_coupons: $skipSmartCoupons.is(':checked') ? 1 : 0
            },
            success: function(response) {
                $loadingIndicator.hide();
                $runDebugButton.prop('disabled', false);
                if (response.success) {
                    displayDebugMessages(response.data.messages);
                } else {
                    // Check if this is a Smart Coupons compatibility error
                    if (response.data && response.data.smart_coupons_error) {
                        displaySmartCouponsError(response.data.message, response.data.debug_info);
                    } else {
                        $debugResults.append('<div class="debug-message error">' + (response.data.message || 'An unknown error occurred.') + '</div>');
                    }
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $loadingIndicator.hide();
                $runDebugButton.prop('disabled', false);

                var message = 'AJAX Error: ' + textStatus;
                if (errorThrown) {
                    message += ' - ' + errorThrown;
                }
                message += '<br>Status: ' + jqXHR.status + ' (' + jqXHR.statusText + ')';

                var responseDetail = '';
                if (jqXHR.responseJSON) {
                    if (jqXHR.responseJSON.message) {
                        responseDetail = jqXHR.responseJSON.message;
                    }
                    if (jqXHR.responseJSON.data) {
                        if (jqXHR.responseJSON.data.message) {
                            responseDetail += (responseDetail ? '\n' : '') + jqXHR.responseJSON.data.message;
                        }
                        if (jqXHR.responseJSON.data.debug_info) {
                            responseDetail += (responseDetail ? '\n' : '') + 'Debug Info: ' + jqXHR.responseJSON.data.debug_info;
                        }
                    }
                } else if (jqXHR.responseText) {
                    responseDetail = jqXHR.responseText;
                }

                if (responseDetail) {
                    var safeResponse = $('<div/>').text(responseDetail).html();
                    message += '<br>Response:<pre>' + safeResponse + '</pre>';
                }

                $debugResults.append('<div class="debug-message error">' + message + '</div>');

                console.error('AJAX request failed', {
                    textStatus: textStatus,
                    errorThrown: errorThrown,
                    status: jqXHR.status,
                    statusText: jqXHR.statusText,
                    responseText: jqXHR.responseText
                });
            }
        });
    });

    $clearDebugButton.on('click', function() {
        $debugResults.empty().append('<p>Enter a coupon code and click "Run Debug" to see the processing details.</p>');
        $couponCodeInput.val('');
        $debugProductsSelect.val('').trigger('change');
        $debugUserSelect.val(null).trigger('change');
    });

    // Generate shareable URL
    $generateUrlButton.on('click', function() {
        console.log('WC SC Debugger: Generate URL button clicked');

        // Debug: Check if elements exist
        console.log('WC SC Debugger: Element checks:', {
            'couponCodeInput exists': $couponCodeInput.length > 0,
            'debugProductsSelect exists': $debugProductsSelect.length > 0,
            'debugUserSelect exists': $debugUserSelect.length > 0,
            'skipSmartCoupons exists': $skipSmartCoupons.length > 0,
            'generatedUrlInput exists': $generatedUrlInput.length > 0,
            'generatedUrlContainer exists': $generatedUrlContainer.length > 0
        });

        var couponCode = $couponCodeInput.val().trim();
        var productId = $debugProductsSelect.val();
        var userId = $debugUserSelect.val();
        var skipSmartCoupons = $skipSmartCoupons.is(':checked');

        console.log('WC SC Debugger: Form values collected:', {
            'couponCode': couponCode,
            'productId': productId,
            'userId': userId,
            'skipSmartCoupons': skipSmartCoupons
        });

        var params = [];
        if (couponCode) {
            params.push('coupon_code=' + encodeURIComponent(couponCode));
            console.log('WC SC Debugger: Added coupon_code parameter');
        }
        if (productId) {
            params.push('product_id=' + encodeURIComponent(productId));
            console.log('WC SC Debugger: Added product_id parameter');
        }
        if (userId) {
            params.push('user_id=' + encodeURIComponent(userId));
            console.log('WC SC Debugger: Added user_id parameter');
        }
        if (skipSmartCoupons) {
            params.push('skip_smart_coupons=1');
            console.log('WC SC Debugger: Added skip_smart_coupons parameter');
        }

        console.log('WC SC Debugger: Parameters array:', params);

        // Debug: Check wcSCDebugger object
        console.log('WC SC Debugger: wcSCDebugger object:', wcSCDebugger);

        var baseUrl = wcSCDebugger.admin_url;
        console.log('WC SC Debugger: Base admin URL:', baseUrl);

        var url = baseUrl;
        if (params.length > 0) {
            url += '&' + params.join('&');
            console.log('WC SC Debugger: URL with parameters:', url);
        } else {
            console.log('WC SC Debugger: No parameters to add, using base URL');
        }

        try {
            $generatedUrlInput.val(url);
            $generatedUrlContainer.show();
            console.log('WC SC Debugger: URL set and container shown successfully');
        } catch (error) {
            console.error('WC SC Debugger: Error setting URL or showing container:', error);
        }
    });

    // Copy URL to clipboard
    $copyUrlButton.on('click', function() {
        console.log('WC SC Debugger: Copy URL button clicked');

        try {
            var urlToCopy = $generatedUrlInput.val();
            console.log('WC SC Debugger: URL to copy:', urlToCopy);

            $generatedUrlInput.select();
            var copySuccess = document.execCommand('copy');
            console.log('WC SC Debugger: Copy command result:', copySuccess);

            // Show feedback
            var originalText = $copyUrlButton.text();
            console.log('WC SC Debugger: Original button text:', originalText);

            $copyUrlButton.text('Copied!');
            setTimeout(function() {
                $copyUrlButton.text(originalText);
                console.log('WC SC Debugger: Button text restored to:', originalText);
            }, 2000);

        } catch (error) {
            console.error('WC SC Debugger: Error copying URL:', error);
        }
    });

    // Clear all settings
    $clearAllSettingsButton.on('click', function() {
        console.log('WC SC Debugger: Clear all settings button clicked');

        if (!confirm('Are you sure you want to clear all settings? This will reset all form fields and clear your saved preferences.')) {
            console.log('WC SC Debugger: User cancelled clear settings operation');
            return;
        }

        console.log('WC SC Debugger: User confirmed clear settings, making AJAX request');
        console.log('WC SC Debugger: AJAX URL:', wcSCDebugger.ajax_url);
        console.log('WC SC Debugger: Security nonce:', wcSCDebugger.debug_coupon_nonce);

        $.ajax({
            url: wcSCDebugger.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'wc_sc_clear_settings',
                security: wcSCDebugger.debug_coupon_nonce
            },
            beforeSend: function() {
                console.log('WC SC Debugger: Clear settings AJAX request starting');
            },
            success: function(response) {
                console.log('WC SC Debugger: Clear settings AJAX response:', response);

                if (response.success) {
                    console.log('WC SC Debugger: Clear settings successful, clearing form fields');

                    // Clear all form fields
                    $couponCodeInput.val('');
                    $debugProductsSelect.val('').trigger('change');
                    $debugUserSelect.val(null).trigger('change');
                    $skipSmartCoupons.prop('checked', false);
                    $generatedUrlContainer.hide();
                    $debugResults.empty().append('<p>Enter a coupon code and click "Run Debug" to see the processing details.</p>');

                    console.log('WC SC Debugger: Form fields cleared successfully');

                    // Show success message
                    alert('All settings cleared successfully!');
                } else {
                    console.error('WC SC Debugger: Clear settings failed:', response);
                    alert('Error clearing settings: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('WC SC Debugger: Clear settings AJAX error:', {
                    textStatus: textStatus,
                    errorThrown: errorThrown,
                    status: jqXHR.status,
                    statusText: jqXHR.statusText,
                    responseText: jqXHR.responseText
                });
                alert('Error clearing settings. Please try again.');
            }
        });
    });

    /**
     * Displays the debugging messages in a structured format.
     * @param {Array} messages - Array of message objects.
     */
    function displayDebugMessages(messages) {
        if (messages.length === 0) {
            $debugResults.append('<div class="debug-message info">No specific hooks or filters were triggered for this coupon.</div>');
            return;
        }

        messages.forEach(function(msg) {
            var $messageDiv = $('<div class="debug-message ' + msg.type + '"></div>');
            var $header = $('<h4>' + msg.message + '</h4>');
            var $content = $('<div class="debug-content" style="display: none;"></div>');

            if (msg.data) {
                // Use a recursive function to safely stringify complex objects
                var safeJsonStringify = function(obj) {
                    var cache = new Set();
                    return JSON.stringify(obj, function(key, value) {
                        if (typeof value === 'object' && value !== null) {
                            if (cache.has(value)) {
                                // Circular reference found, discard key
                                return '[Circular Reference]';
                            }
                            // Store value in our collection
                            cache.add(value);
                        }
                        return value;
                    }, 2);
                };

                if (msg.data.args) {
                    $content.append('<h5>Arguments:</h5><pre>' + safeJsonStringify(msg.data.args) + '</pre>');
                }
                if (msg.data.return !== undefined) {
                    $content.append('<h5>Return Value:</h5><pre>' + safeJsonStringify(msg.data.return) + '</pre>');
                }
            }

            $messageDiv.append($header).append($content);
            $debugResults.append($messageDiv);

            // Toggle content visibility on header click
            $header.on('click', function() {
                $content.slideToggle();
                $messageDiv.toggleClass('expanded');
            });
        });
    }

    /**
     * Display Smart Coupons compatibility error with helpful information
     * @param {string} message - Error message
     * @param {object} debugInfo - Debug information
     */
    function displaySmartCouponsError(message, debugInfo) {
        $debugResults.empty();

        var errorHtml = '<div class="debug-message error smart-coupons-error">';
        errorHtml += '<h3>🚨 Smart Coupons Compatibility Issue</h3>';
        errorHtml += '<p><strong>' + message + '</strong></p>';
        errorHtml += '<div class="smart-coupons-help">';
        errorHtml += '<h4>What does this mean?</h4>';
        errorHtml += '<ul>';
        errorHtml += '<li>The WooCommerce Smart Coupons plugin has a compatibility issue with your current PHP version</li>';
        errorHtml += '<li>This is typically caused by running Smart Coupons on PHP 8+ when it was designed for older PHP versions</li>';
        errorHtml += '<li>The coupon debugging cannot proceed due to this plugin conflict</li>';
        errorHtml += '</ul>';
        errorHtml += '<h4>How to fix this:</h4>';
        errorHtml += '<ol>';
        errorHtml += '<li><strong>Update Smart Coupons:</strong> Check if there\'s a newer version available that supports your PHP version</li>';
        errorHtml += '<li><strong>Contact Support:</strong> Reach out to the Smart Coupons plugin author for PHP 8+ compatibility</li>';
        errorHtml += '<li><strong>Temporary Workaround:</strong> You may need to downgrade PHP or disable Smart Coupons temporarily for debugging</li>';
        errorHtml += '</ol>';
        errorHtml += '</div>';

        if (debugInfo) {
            errorHtml += '<details class="debug-details">';
            errorHtml += '<summary>Technical Details (for developers)</summary>';
            errorHtml += '<pre>' + JSON.stringify(debugInfo, null, 2) + '</pre>';
            errorHtml += '</details>';
        }

        errorHtml += '</div>';
        $debugResults.append(errorHtml);
    }
});