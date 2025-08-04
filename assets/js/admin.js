jQuery(document).ready(function($) {
    var $couponCodeInput = $('#coupon_code');
    var $debugProductsSelect = $('#debug_products_select');
    var $debugUserSelect = $('#debug_user');
    var $runDebugButton = $('#run_debug');
    var $clearDebugButton = $('#clear_debug');
    var $debugResults = $('#debug_results');
    var $loadingIndicator = $('.loading-indicator');
    var $productStockStatus = $('#product_stock_status');

    // Store product details fetched via AJAX
    var productDetailsCache = {};

    $debugUserSelect.selectWoo({
        ajax: {
            url: wcSCDebugger.ajax_url,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    term: params.term,
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
        minimumInputLength: 1,
        allowClear: true,
        placeholder: $(this).data('placeholder'),
        escapeMarkup: function (markup) { return markup; }
    });

    $debugProductsSelect.on('change', function() {
        var productId = $(this).val();
        $productStockStatus.empty().removeClass('error notice-error');

        if (!productId) {
            $runDebugButton.prop('disabled', false);
            return;
        }

        // Fetch product details
        $.ajax({
            url: wcSCDebugger.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_sc_get_product_details',
                security: wcSCDebugger.get_product_details_nonce,
                product_id: productId
            },
            success: function(response) {
                if (response.success) {
                    productDetailsCache[productId] = response.data;
                    if (!response.data.is_in_stock) {
                        $productStockStatus.text('This product is out of stock.').addClass('error notice-error');
                        $runDebugButton.prop('disabled', true);
                    } else {
                        $runDebugButton.prop('disabled', false);
                    }
                } else {
                    $productStockStatus.text(response.data.message || 'Error checking stock.').addClass('error notice-error');
                    $runDebugButton.prop('disabled', true);
                }
            }
        });
    });

    $runDebugButton.on('click', function() {
        var couponCode = $couponCodeInput.val().trim();
        var productId = $debugProductsSelect.val();
        var userId = $debugUserSelect.val();
        var variationId = 0; // Default to no variation

        if (!couponCode) {
            alert('Please enter a coupon code to debug.');
            return;
        }

        // If a product is selected and it's a variable product, pick a random variation
        if (productId && productDetailsCache[productId]) {
            var details = productDetailsCache[productId];
            if (details.product_type === 'variable' && details.available_variations.length > 0) {
                variationId = details.available_variations[Math.floor(Math.random() * details.available_variations.length)];
            }
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
                variation_id: variationId,
                user_id: userId
            },
            success: function(response) {
                $loadingIndicator.hide();
                $runDebugButton.prop('disabled', false);
                // Re-enable button but respect stock status
                if ($debugProductsSelect.val() && !$productStockStatus.is(':empty')) {
                     $runDebugButton.prop('disabled', true);
                }

                if (response.success) {
                    displayDebugMessages(response.data.messages);
                } else {
                    $debugResults.append('<div class="debug-message error">' + (response.data.message || 'An unknown error occurred.') + '</div>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $loadingIndicator.hide();
                $runDebugButton.prop('disabled', false);
                $debugResults.append('<div class="debug-message error">AJAX Error: ' + textStatus + ' - ' + errorThrown + '</div>');
            }
        });
    });

    $clearDebugButton.on('click', function() {
        $debugResults.empty().append('<p>Enter a coupon code and click "Run Debug" to see the processing details.</p>');
        $couponCodeInput.val('');
        $debugProductsSelect.val('').trigger('change');
        $debugUserSelect.val(null).trigger('change');
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
                                return '[Circular Reference]';
                            }
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
});