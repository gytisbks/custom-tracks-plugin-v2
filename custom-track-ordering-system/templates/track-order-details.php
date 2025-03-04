<?php
/**
 * Template for displaying track order details
 */
defined('ABSPATH') || exit;

// Make sure order_meta is set
if (!isset($order_meta) || empty($order_meta)) {
    echo '<p>' . __('Order details not available.', 'custom-track-ordering-system') . '</p>';
    return;
}

$producer = get_user_by('id', $order_meta->producer_id);
$customer = get_user_by('id', $order_meta->customer_id);
$producer_name = $producer ? $producer->display_name : __('Unknown Producer', 'custom-track-ordering-system');
$customer_name = $customer ? $customer->display_name : __('Unknown Customer', 'custom-track-ordering-system');
$status_label = CTOS_MarketKing_Integration::get_status_label($order_meta->status);
$status_class = CTOS_MarketKing_Integration::get_status_class($order_meta->status);

// Check if we're in the MarketKing dashboard
$is_marketking_dashboard = isset($_GET['page']) && $_GET['page'] === 'custom-tracks';

// Get original order details from woocommerce
$deposit_order = false;
if ($order_meta->deposit_order_id) {
    $deposit_order = wc_get_order($order_meta->deposit_order_id);
}

$final_order = false;
if ($order_meta->final_order_id) {
    $final_order = wc_get_order($order_meta->final_order_id);
}

// Get order details
$order_details = maybe_unserialize($order_meta->order_data);
$reference_tracks = !empty($order_details['reference_tracks']) ? explode(',', $order_details['reference_tracks']) : array();
$selected_addons = !empty($order_details['addons']) ? $order_details['addons'] : array();

// Get message thread
$thread_id = get_post_meta($order_meta->order_id, '_ctos_message_thread_id', true);
$message_url = '';
if ($thread_id && function_exists('marketking_get_message_url')) {
    $message_url = marketking_get_message_url($thread_id);
}
?>

<div class="ctos-order-details">
    <h2><?php echo sprintf(__('Track Order #%s Details', 'custom-track-ordering-system'), $order_meta->order_id); ?></h2>
    
    <div class="ctos-order-status-bar">
        <div class="ctos-order-status <?php echo esc_attr($status_class); ?>">
            <?php echo esc_html($status_label); ?>
        </div>
        
        <?php if ($order_meta->status === 'pending_demo_submission' && $order_meta->deposit_paid) : ?>
            <button type="button" class="<?php echo $is_marketking_dashboard ? 'btn btn-success' : 'ctos-button'; ?>" onclick="document.getElementById('ctos-demo-upload-detail-<?php echo $order_meta->order_id; ?>').click();">
                <?php _e('Upload Demo', 'custom-track-ordering-system'); ?>
            </button>
            <input type="file" id="ctos-demo-upload-detail-<?php echo $order_meta->order_id; ?>" class="ctos-demo-upload" data-order-id="<?php echo $order_meta->order_id; ?>" accept=".mp3" style="display: none;">
        <?php endif; ?>
        
        <?php if ($order_meta->status === 'awaiting_final_delivery' && $order_meta->final_paid) : ?>
            <button type="button" class="<?php echo $is_marketking_dashboard ? 'btn btn-success' : 'ctos-button'; ?>" onclick="document.getElementById('ctos-final-files-upload-detail-<?php echo $order_meta->order_id; ?>').click();">
                <?php _e('Upload Final Files', 'custom-track-ordering-system'); ?>
            </button>
            <input type="file" id="ctos-final-files-upload-detail-<?php echo $order_meta->order_id; ?>" class="ctos-final-files-upload" data-order-id="<?php echo $order_meta->order_id; ?>" multiple accept=".mp3,.wav,.zip" style="display: none;">
        <?php endif; ?>
        
        <?php if (!empty($message_url)) : ?>
            <a href="<?php echo esc_url($message_url); ?>" class="<?php echo $is_marketking_dashboard ? 'btn btn-warning' : 'ctos-button ctos-button-secondary'; ?>">
                <?php _e('Messages', 'custom-track-ordering-system'); ?>
            </a>
        <?php endif; ?>
    </div>
    
    <div class="ctos-order-meta">
        <div class="ctos-order-meta-item">
            <strong><?php _e('Date:', 'custom-track-ordering-system'); ?></strong>
            <span><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($order_meta->created_at)); ?></span>
        </div>
        
        <div class="ctos-order-meta-item">
            <strong><?php _e('Customer:', 'custom-track-ordering-system'); ?></strong>
            <span><?php echo esc_html($customer_name); ?></span>
        </div>
        
        <div class="ctos-order-meta-item">
            <strong><?php _e('Producer:', 'custom-track-ordering-system'); ?></strong>
            <span><?php echo esc_html($producer_name); ?></span>
        </div>
        
        <div class="ctos-order-meta-item">
            <strong><?php _e('Service Type:', 'custom-track-ordering-system'); ?></strong>
            <span><?php echo esc_html(ucfirst(str_replace('_', ' ', $order_meta->service_type))); ?></span>
        </div>
        
        <?php if ($deposit_order) : ?>
            <div class="ctos-order-meta-item">
                <strong><?php _e('Deposit Order:', 'custom-track-ordering-system'); ?></strong>
                <span><a href="<?php echo esc_url($deposit_order->get_edit_order_url()); ?>" target="_blank">#<?php echo $deposit_order->get_order_number(); ?></a> - <?php echo $deposit_order->get_formatted_order_total(); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($final_order) : ?>
            <div class="ctos-order-meta-item">
                <strong><?php _e('Final Payment Order:', 'custom-track-ordering-system'); ?></strong>
                <span><a href="<?php echo esc_url($final_order->get_edit_order_url()); ?>" target="_blank">#<?php echo $final_order->get_order_number(); ?></a> - <?php echo $final_order->get_formatted_order_total(); ?></span>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="ctos-order-details-section">
        <h3><?php _e('Track Details', 'custom-track-ordering-system'); ?></h3>
        
        <div class="ctos-order-details-content">
            <div class="ctos-order-detail-item">
                <strong><?php _e('Track Name:', 'custom-track-ordering-system'); ?></strong>
                <span><?php echo !empty($order_details['track_name']) ? esc_html($order_details['track_name']) : '-'; ?></span>
            </div>
            
            <div class="ctos-order-detail-item">
                <strong><?php _e('Genre:', 'custom-track-ordering-system'); ?></strong>
                <span><?php echo !empty($order_details['genre']) ? esc_html($order_details['genre']) : '-'; ?></span>
            </div>
            
            <div class="ctos-order-detail-item">
                <strong><?php _e('BPM:', 'custom-track-ordering-system'); ?></strong>
                <span><?php echo !empty($order_details['bpm']) ? esc_html($order_details['bpm']) : '-'; ?></span>
            </div>
            
            <?php if (!empty($order_details['description'])) : ?>
                <div class="ctos-order-detail-item ctos-full-width">
                    <strong><?php _e('Description:', 'custom-track-ordering-system'); ?></strong>
                    <div class="ctos-description"><?php echo nl2br(esc_html($order_details['description'])); ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($reference_tracks)) : ?>
                <div class="ctos-order-detail-item ctos-full-width">
                    <strong><?php _e('Reference Tracks:', 'custom-track-ordering-system'); ?></strong>
                    <ul class="ctos-reference-tracks">
                        <?php foreach ($reference_tracks as $track) : ?>
                            <li><?php echo esc_html($track); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($selected_addons)) : ?>
                <div class="ctos-order-detail-item ctos-full-width">
                    <strong><?php _e('Additional Services:', 'custom-track-ordering-system'); ?></strong>
                    <ul class="ctos-addons">
                        <?php foreach ($selected_addons as $addon) : ?>
                            <li><?php echo esc_html($addon['name']) . ' (' . wc_price($addon['price']) . ')'; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="ctos-files-section">
        <h3><?php _e('Files', 'custom-track-ordering-system'); ?></h3>
        
        <?php if ($order_meta->demo_file) : ?>
            <div class="ctos-file-item">
                <strong><?php _e('Demo Track:', 'custom-track-ordering-system'); ?></strong>
                <audio controls>
                    <source src="<?php echo esc_url(CTOS_File_Handler::get_file_url($order_meta->demo_file)); ?>" type="audio/mpeg">
                    <?php _e('Your browser does not support the audio element.', 'custom-track-ordering-system'); ?>
                </audio>
                <a href="<?php echo esc_url(CTOS_File_Handler::get_download_url('demo', $order_meta->order_id)); ?>" class="<?php echo $is_marketking_dashboard ? 'btn btn-sm btn-primary' : 'ctos-button ctos-button-secondary'; ?>">
                    <?php _e('Download', 'custom-track-ordering-system'); ?>
                </a>
            </div>
        <?php else : ?>
            <p><?php _e('No demo file has been uploaded yet.', 'custom-track-ordering-system'); ?></p>
        <?php endif; ?>
        
        <?php if ($order_meta->final_files) : 
            $final_files = explode(',', $order_meta->final_files);
        ?>
            <div class="ctos-file-item">
                <strong><?php _e('Final Files:', 'custom-track-ordering-system'); ?></strong>
                <ul class="ctos-final-files">
                    <?php foreach ($final_files as $index => $file) : 
                        $file_url = CTOS_File_Handler::get_file_url($file);
                        $file_ext = pathinfo($file, PATHINFO_EXTENSION);
                        $is_audio = in_array($file_ext, array('mp3', 'wav'));
                    ?>
                        <li>
                            <?php if ($is_audio) : ?>
                                <audio controls>
                                    <source src="<?php echo esc_url($file_url); ?>" type="audio/<?php echo $file_ext; ?>">
                                    <?php _e('Your browser does not support the audio element.', 'custom-track-ordering-system'); ?>
                                </audio>
                            <?php else : ?>
                                <span class="ctos-file-name"><?php echo esc_html(basename($file)); ?></span>
                            <?php endif; ?>
                            <a href="<?php echo esc_url(CTOS_File_Handler::get_download_url('final', $order_meta->order_id, $index)); ?>" class="<?php echo $is_marketking_dashboard ? 'btn btn-sm btn-primary' : 'ctos-button ctos-button-secondary'; ?>">
                                <?php _e('Download', 'custom-track-ordering-system'); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif ($order_meta->status === 'completed') : ?>
            <p><?php _e('No final files have been uploaded yet.', 'custom-track-ordering-system'); ?></p>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Handle demo upload (same as in producer-orders.php)
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
        var $button = $(this).prev('button');
        var originalText = $button.text();
        $button.text('<?php _e('Uploading...', 'custom-track-ordering-system'); ?>').prop('disabled', true);
        
        // Submit via AJAX
        $.ajax({
            url: ctos_vars.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('<?php _e('Demo uploaded successfully. The customer will be notified.', 'custom-track-ordering-system'); ?>');
                    window.location.reload();
                } else {
                    alert('<?php _e('Error:', 'custom-track-ordering-system'); ?> ' + (response.data || '<?php _e('Unknown error', 'custom-track-ordering-system'); ?>'));
                    $button.text(originalText).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                alert('<?php _e('Error:', 'custom-track-ordering-system'); ?> ' + error);
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Handle final files upload (same as in producer-orders.php)
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
        var $button = $(this).prev('button');
        var originalText = $button.text();
        $button.text('<?php _e('Uploading...', 'custom-track-ordering-system'); ?>').prop('disabled', true);
        
        // Submit via AJAX
        $.ajax({
            url: ctos_vars.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('<?php _e('Final files uploaded successfully. The customer will be notified.', 'custom-track-ordering-system'); ?>');
                    window.location.reload();
                } else {
                    alert('<?php _e('Error:', 'custom-track-ordering-system'); ?> ' + (response.data || '<?php _e('Unknown error', 'custom-track-ordering-system'); ?>'));
                    $button.text(originalText).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                alert('<?php _e('Error:', 'custom-track-ordering-system'); ?> ' + error);
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
});
</script>
