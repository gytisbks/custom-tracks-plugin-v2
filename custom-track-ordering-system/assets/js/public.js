jQuery(document).ready(function($) {
    // Order form modal toggle
    $('.ctos-request-button').on('click', function() {
        var producerId = $(this).data('producer-id');
        $('#ctos-order-form-modal').show();
        $('#ctos-producer-id').val(producerId);
    });
    
    $('.ctos-modal-close').on('click', function() {
        $('#ctos-order-form-modal').hide();
    });
    
    // Close modal when clicking outside
    $(window).on('click', function(e) {
        if ($(e.target).is('#ctos-order-form-modal')) {
            $('#ctos-order-form-modal').hide();
        }
    });
    
    // Handle addon selection
    $('.ctos-addon-checkbox').on('change', function() {
        updateTotalPrice();
    });
    
    // Update total price when service type changes
    $('#ctos-service-type').on('change', function() {
        updateTotalPrice();
        
        // Update deposit price text
        var selectedOption = $(this).find('option:selected');
        var depositPercentage = selectedOption.data('deposit-percentage');
        var basePrice = parseFloat(selectedOption.data('price'));
        var depositAmount = basePrice * (depositPercentage / 100);
        
        $('#ctos-deposit-percentage').text(depositPercentage);
        $('#ctos-deposit-amount').text(formatPrice(depositAmount));
    });
    
    // Update total price calculation
    function updateTotalPrice() {
        var serviceSelect = $('#ctos-service-type');
        var selectedOption = serviceSelect.find('option:selected');
        var basePrice = parseFloat(selectedOption.data('price'));
        
        if (isNaN(basePrice)) {
            basePrice = 0;
        }
        
        var addonTotal = 0;
        $('.ctos-addon-checkbox:checked').each(function() {
            addonTotal += parseFloat($(this).data('price'));
        });
        
        var totalPrice = basePrice + addonTotal;
        $('#ctos-base-price').text(formatPrice(basePrice));
        $('#ctos-addon-price').text(formatPrice(addonTotal));
        $('#ctos-total-price').text(formatPrice(totalPrice));
    }
    
    // Format price with currency symbol
    function formatPrice(price) {
        if (typeof ctos_data !== 'undefined' && ctos_data.currency_format) {
            return ctos_data.currency_format.replace('%s', price.toFixed(2));
        }
        
        return '$' + price.toFixed(2);
    }
    
    // Initialize price display
    updateTotalPrice();
    
    // Reference track input handling
    var maxReferenceTrackCount = 5;
    var referenceTrackCount = 1;
    
    $('#ctos-add-reference').on('click', function(e) {
        e.preventDefault();
        
        if (referenceTrackCount >= maxReferenceTrackCount) {
            alert('Maximum of ' + maxReferenceTrackCount + ' reference tracks allowed.');
            return;
        }
        
        referenceTrackCount++;
        
        var newInput = '<div class="ctos-form-row">' +
            '<input type="text" name="reference_tracks[]" placeholder="Reference Track ' + referenceTrackCount + ' (Artist - Title)" class="ctos-input">' +
            '</div>';
            
        $('#ctos-reference-tracks-container').append(newInput);
    });
    
    // Debug helper function that shows if our JS is loaded
    console.log('CTOS: Public JS loaded');
    
    // Handle demo upload
    $('.ctos-demo-upload').on('change', function() {
        var orderId = $(this).data('order-id');
        var file = this.files[0];
        
        if (!file) {
            return;
        }
        
        // Create form data
        var formData = new FormData();
        formData.append('action', 'ctos_upload_demo');
        formData.append('order_id', orderId);
        formData.append('demo_file', file);
        formData.append('nonce', ctos_vars.nonce);
        
        // Show loading message
        var $button = $(this).prev('.ctos-button, .btn');
        var originalText = $button.text();
        $button.text('Uploading...').prop('disabled', true);
        
        // Submit via AJAX
        $.ajax({
            url: ctos_vars.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Demo uploaded successfully. The customer will be notified.');
                    window.location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                    $button.text(originalText).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Handle final files upload
    $('.ctos-final-files-upload').on('change', function() {
        var orderId = $(this).data('order-id');
        var files = this.files;
        
        if (files.length === 0) {
            return;
        }
        
        // Create form data
        var formData = new FormData();
        formData.append('action', 'ctos_upload_final_files');
        formData.append('order_id', orderId);
        formData.append('nonce', ctos_vars.nonce);
        
        // Add all files
        for (var i = 0; i < files.length; i++) {
            formData.append('file_' + i, files[i]);
        }
        
        // Show loading message
        var $button = $(this).prev('.ctos-button, .btn');
        var originalText = $button.text();
        $button.text('Uploading...').prop('disabled', true);
        
        // Submit via AJAX
        $.ajax({
            url: ctos_vars.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Final files uploaded successfully. The customer will be notified.');
                    window.location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                    $button.text(originalText).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Handle demo approval
    $('.ctos-approve-demo').on('click', function(e) {
        e.preventDefault();
        var orderId = $(this).data('order-id');
        
        if (!confirm('Are you sure you want to approve this demo? This will move the order to the final payment stage.')) {
            return;
        }
        
        // Create data
        var data = {
            action: 'ctos_approve_demo',
            order_id: orderId,
            nonce: ctos_vars.nonce
        };
        
        // Show loading message
        var $button = $(this);
        var originalText = $button.text();
        $button.text('Processing...').prop('disabled', true);
        
        // Submit via AJAX
        $.ajax({
            url: ctos_vars.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    alert('Demo approved. You will now be redirected to the payment page.');
                    if (response.data && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                    $button.text(originalText).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Handle revision request
    $('.ctos-request-revision').on('click', function(e) {
        e.preventDefault();
        var orderId = $(this).data('order-id');
        
        // Get revision notes
        var revisionNotes = prompt('Please provide details for the revision request:');
        
        if (revisionNotes === null) {
            return; // User cancelled
        }
        
        if (revisionNotes.trim() === '') {
            alert('Please provide details for the revision request.');
            return;
        }
        
        // Create data
        var data = {
            action: 'ctos_request_revision',
            order_id: orderId,
            notes: revisionNotes,
            nonce: ctos_vars.nonce
        };
        
        // Show loading message
        var $button = $(this);
        var originalText = $button.text();
        $button.text('Processing...').prop('disabled', true);
        
        // Submit via AJAX
        $.ajax({
            url: ctos_vars.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    alert('Revision requested. The producer will be notified.');
                    window.location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                    $button.text(originalText).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {                alert('Error: ' + error);
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
});
