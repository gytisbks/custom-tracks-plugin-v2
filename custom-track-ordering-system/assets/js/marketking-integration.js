jQuery(document).ready(function($) {
    console.log('CTOS MarketKing Integration loaded - v3');
    
    // Create modal container if it doesn't exist
    if ($('#ctos-modal-container').length === 0) {
        $('body').append('<div id="ctos-modal-container" class="ctos-modal-container"></div>');
    }
    
    // Intercept all view button clicks
    $(document).on('click', '.view, button:contains("View"), [href*="order_id"]', function(e) {
        // Extract order ID from different sources
        var orderId = $(this).data('order-id');
        
        if (!orderId) {
            // Try to get from href attribute
            var href = $(this).attr('href');
            if (href && href.indexOf('order_id=') > -1) {
                var match = href.match(/order_id=(\d+)/);
                if (match && match[1]) {
                    orderId = match[1];
                }
            }
            
            // Try to get from first column text (for orders table)
            if (!orderId) {
                var firstCol = $(this).closest('tr').find('td:first').text();
                if (firstCol) {
                    orderId = firstCol.replace('#', '').trim();
                }
            }
        }
        
        if (orderId) {
            e.preventDefault();
            e.stopPropagation();
            
            // Load and display the modal
            openOrderModal(orderId);
            return false;
        }
    });
    
    // Function to load and display order modal
    function openOrderModal(orderId) {
        // Show loading state
        $('#ctos-modal-container').html('<div class="ctos-modal-loading">Loading order details...</div>').addClass('active');
        
        // Load modal content via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ctos_get_order_details',
                order_id: orderId,
                nonce: $('#_wpnonce').val() || $('input[name="_wpnonce"]').val()
            },
            success: function(response) {
                if (response.success && response.data && response.data.html) {
                    // Insert the modal content
                    $('#ctos-modal-container').html(response.data.html).addClass('active');
                    
                    // Setup modal functionality
                    setupModalFunctionality();
                } else {
                    $('#ctos-modal-container').html('<div class="ctos-modal-error">Error loading order details.</div>');
                }
            },
            error: function() {
                $('#ctos-modal-container').html('<div class="ctos-modal-error">Error connecting to server.</div>');
            }
        });
    }
    
    // Close modal when clicking outside or on close button
    $(document).on('click', '#ctos-modal-container', function(e) {
        if (e.target === this) {
            $(this).removeClass('active');
        }
    });
    
    $(document).on('click', '.ctos-close-modal', function() {
        $('#ctos-modal-container').removeClass('active');
    });
    
    // Setup modal internal functionality
    function setupModalFunctionality() {
        // Tab switching in files section
        $('.ctos-tab').on('click', function() {
            var tabId = $(this).data('tab');
            
            // Switch active tab
            $('.ctos-tab').removeClass('active');
            $(this).addClass('active');
            
            // Switch active content
            $('.ctos-tab-content').removeClass('active');
            $('#ctos-' + tabId).addClass('active');
        });
        
        // File upload triggers
        $('#ctos-upload-demo-btn').on('click', function() {
            $('#ctos-demo-file-input').click();
        });
        
        $('#ctos-upload-final-btn').on('click', function() {
            $('#ctos-final-files-input').click();
        });
        
        // Demo file upload handler
        $('#ctos-demo-file-input').on('change', function() {
            var file = this.files[0];
            var orderId = $(this).data('order-id');
            
            if (!file) return;
            
            var formData = new FormData();
            formData.append('action', 'ctos_upload_demo');
            formData.append('order_id', orderId);
            formData.append('demo_file', file);
            formData.append('nonce', $('#_wpnonce').val() || $('input[name="_wpnonce"]').val());
            
            var $button = $('#ctos-upload-demo-btn');
            var originalText = $button.text();
            $button.text('Uploading...').prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert('Demo uploaded successfully!');
                        // Reload the modal to show updated content
                        openOrderModal(orderId);
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Upload failed. Please try again.');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });
        
        // Final files upload handler
        $('#ctos-final-files-input').on('change', function() {
            var files = this.files;
            var orderId = $(this).data('order-id');
            
            if (files.length === 0) return;
            
            var formData = new FormData();
            formData.append('action', 'ctos_upload_final_files');
            formData.append('order_id', orderId);
            formData.append('nonce', $('#_wpnonce').val() || $('input[name="_wpnonce"]').val());
            
            for (var i = 0; i < files.length; i++) {
                formData.append('file_' + i, files[i]);
            }
            
            var $button = $('#ctos-upload-final-btn');
            var originalText = $button.text();
            $button.text('Uploading...').prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert('Files uploaded successfully!');
                        // Reload the modal to show updated content
                        openOrderModal(orderId);
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Upload failed. Please try again.');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });
        
        // Approve demo button handler
        $('.ctos-approve-demo').on('click', function() {
            var orderId = $(this).data('order-id');
            
            if (!confirm('Are you sure you want to approve this demo? This will move to the final payment stage.')) {
                return;
            }
            
            var $button = $(this);
            var originalText = $button.text();
            $button.text('Processing...').prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ctos_approve_demo',
                    order_id: orderId,
                    nonce: $('#_wpnonce').val() || $('input[name="_wpnonce"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert('Demo approved. Redirecting to payment page...');
                        if (response.data && response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            // Reload the modal to show updated status
                            openOrderModal(orderId);
                        }
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Process failed. Please try again.');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });
        
        // Request revision button handler
        $('.ctos-request-revision').on('click', function() {
            var orderId = $(this).data('order-id');
            var notes = prompt('Please enter details for the revision request:');
            
            if (notes === null) return;
            
            if (notes.trim() === '') {
                alert('Please provide details for the revision request.');
                return;
            }
            
            var $button = $(this);
            var originalText = $button.text();
            $button.text('Processing...').prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ctos_request_revision',
                    order_id: orderId,
                    notes: notes,
                    nonce: $('#_wpnonce').val() || $('input[name="_wpnonce"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert('Revision requested successfully!');
                        // Reload the modal to show updated status
                        openOrderModal(orderId);
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Process failed. Please try again.');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });
        
        // Message form submission handler
        $('#ctos-message-form').on('submit', function(e) {
            e.preventDefault();
            
            var message = $('#ctos-message-input').val().trim();
            if (!message) return;
            
            var threadId = $(this).data('thread-id');
            var orderId = $(this).data('order-id');
            
            var $button = $('#ctos-send-message');
            $button.prop('disabled', true).text('Sending...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ctos_send_message',
                    thread_id: threadId,
                    order_id: orderId,
                    message: message,
                    nonce: $('#_wpnonce').val() || $('input[name="_wpnonce"]').val()
                },
                success: function(response) {
                    $button.prop('disabled', false).text('Send');
                    
                    if (response.success) {
                        // Add the message to the chat
                        var newMessageHtml = `
                            <div class="ctos-message ctos-message-sent">
                                <div class="ctos-message-header">
                                    <span class="ctos-message-sender">You</span>
                                    <span class="ctos-message-time">${new Date().toLocaleString()}</span>
                                </div>
                                <div class="ctos-message-content">
                                    <p>${message.replace(/\n/g, '<br>')}</p>
                                </div>
                            </div>
                        `;
                        
                        $('#ctos-chat-messages').append(                        $('#ctos-chat-messages').append(newMessageHtml);
                        
                        // Clear input and scroll to bottom
                        $('#ctos-message-input').val('');
                        $('#ctos-chat-messages').scrollTop($('#ctos-chat-messages')[0].scrollHeight);
                    } else {
                        alert('Error sending message: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text('Send');
                    alert('Failed to send message. Please try again.');
                }
            });
        });
    }
    
    // Add Custom Tracks tab to dashboard
    function addCustomTracksTab() {
        var $sidebar = $('.dashboard-sidebar, .marketking-dashboard-menu');
        
        if ($sidebar.length && $('#custom-tracks-tab').length === 0) {
            // Create tab item with similar styling to other tabs
            var $tabItem = $(`
                <a href="#" id="custom-tracks-tab" class="dashboard-item">
                    <span class="dashicons dashicons-format-audio"></span>
                    <span>Custom Tracks</span>
                </a>
            `);
            
            // Add the tab to the menu
            $sidebar.append($tabItem);
            
            // Handle tab click
            $tabItem.on('click', function(e) {
                e.preventDefault();
                
                // Show loading state
                $('.dashboard-content').html('<div class="loading-content">Loading custom tracks...</div>');
                
                // Load custom tracks content via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ctos_get_producer_orders',
                        nonce: $('#_wpnonce').val() || $('input[name="_wpnonce"]').val()
                    },
                    success: function(response) {
                        if (response.success && response.data && response.data.html) {
                            $('.dashboard-content').html(response.data.html);
                        } else {
                            $('.dashboard-content').html('<div class="error-message">Could not load custom tracks.</div>');
                        }
                    },
                    error: function() {
                        $('.dashboard-content').html('<div class="error-message">Connection error. Please try again.</div>');
                    }
                });
            });
        }
    }
    
    // Initialize
    addCustomTracksTab();
    
    // Make modal function available globally
    window.openCustomTrackOrder = function(orderId) {
        openOrderModal(orderId);
    };
});
