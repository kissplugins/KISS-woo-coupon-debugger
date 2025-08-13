/**
 * Changelog Modal Handler for KISS Woo Coupon Debugger
 */
jQuery(document).ready(function($) {
    'use strict';

    // Handle changelog link clicks
    $(document).on('click', '.wc-sc-debugger-changelog-link', function(e) {
        e.preventDefault();
        
        var $link = $(this);
        var nonce = $link.data('nonce');
        
        // Create modal if it doesn't exist
        if (!$('#wc-sc-debugger-changelog-modal').length) {
            createChangelogModal();
        }
        
        var $modal = $('#wc-sc-debugger-changelog-modal');
        var $content = $modal.find('.changelog-content');
        var $loading = $modal.find('.changelog-loading');
        
        // Show modal and loading state
        $modal.show();
        $content.hide();
        $loading.show();
        
        // Load changelog content via AJAX
        $.ajax({
            url: wcSCDebuggerChangelog.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_sc_debugger_view_changelog',
                nonce: nonce
            },
            success: function(response) {
                $loading.hide();
                
                if (response.success && response.data.html) {
                    $content.html(response.data.html).show();
                } else {
                    $content.html('<p class="error">' + wcSCDebuggerChangelog.strings.error + '</p>').show();
                }
            },
            error: function() {
                $loading.hide();
                $content.html('<p class="error">' + wcSCDebuggerChangelog.strings.error + '</p>').show();
            }
        });
    });
    
    // Handle modal close
    $(document).on('click', '.changelog-modal-close, .changelog-modal-overlay', function(e) {
        if (e.target === this) {
            $('#wc-sc-debugger-changelog-modal').hide();
        }
    });
    
    // Handle ESC key
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27 && $('#wc-sc-debugger-changelog-modal').is(':visible')) {
            $('#wc-sc-debugger-changelog-modal').hide();
        }
    });
    
    /**
     * Create the changelog modal HTML
     */
    function createChangelogModal() {
        var modalHtml = [
            '<div id="wc-sc-debugger-changelog-modal" class="changelog-modal-overlay">',
            '    <div class="changelog-modal">',
            '        <div class="changelog-modal-header">',
            '            <h2>' + wcSCDebuggerChangelog.strings.changelog_title + '</h2>',
            '            <button type="button" class="changelog-modal-close" aria-label="' + wcSCDebuggerChangelog.strings.close + '">',
            '                <span class="dashicons dashicons-no-alt"></span>',
            '            </button>',
            '        </div>',
            '        <div class="changelog-modal-body">',
            '            <div class="changelog-loading">',
            '                <p><span class="spinner is-active"></span> ' + wcSCDebuggerChangelog.strings.loading + '</p>',
            '            </div>',
            '            <div class="changelog-content" style="display: none;"></div>',
            '        </div>',
            '    </div>',
            '</div>'
        ].join('');
        
        $('body').append(modalHtml);
    }
});
