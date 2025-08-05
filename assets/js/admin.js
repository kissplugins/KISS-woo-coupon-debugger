jQuery(document).ready(function($) {
    var $couponCodeInput = $('#coupon_code');
    var $debugProductsSelect = $('#debug_products_select');
    var $debugUserSelect = $('#debug_user');
    var $runDebugButton = $('#run_debug');
    var $clearDebugButton = $('#clear_debug');
    var $debugResults = $('#debug_results');
    var $loadingIndicator = $('.loading-indicator');

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
                user_id: userId
            },
            success: function(response) {
                $loadingIndicator.hide();
                $runDebugButton.prop('disabled', false);
                if (response.success) {
                    displayDebugMessages(response.data.messages);
                } else {
                    $debugResults.append('<div class="debug-message error">' + (response.data.message || 'An unknown error occurred.') + '</div>');
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
});