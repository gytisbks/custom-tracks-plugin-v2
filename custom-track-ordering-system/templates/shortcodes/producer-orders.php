<?php
/**
 * Template for displaying producer orders via shortcode
 */
defined('ABSPATH') || exit;

// Get the order ID from the parameter, if available
$show_order_id = isset($atts['order_id']) ? intval($atts['order_id']) : 0;

// Get the current user as producer
$producer_id = get_current_user_id();

// Check if we're in MarketKing dashboard
$is_marketking_dashboard = isset($_GET['page']) && $_GET['page'] === 'custom-tracks';

if ($show_order_id > 0) {
    // Show single order details
    global $wpdb;
    $meta_table = $wpdb->prefix . 'ctos_order_meta';
    
    $order_meta = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $meta_table WHERE order_id = %d AND producer_id = %d",
        $show_order_id, $producer_id
    ));
    
    if ($order_meta) {
        include(CTOS_PLUGIN_DIR . 'templates/track-order-details.php');
    } else {
        echo '<p>' . esc_html__('Order not found.', 'custom-track-ordering-system') . '</p>';
    }
} else {
    // Show list of orders
    $orders = CTOS_MarketKing_Integration::get_producer_orders($producer_id);
    
    if (empty($orders)) {
        echo '<p>' . esc_html__('You have no custom track orders yet.', 'custom-track-ordering-system') . '</p>';
    } else {
        ?>
        <div class="ctos-orders-container">
            <table class="ctos-orders-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Order', 'custom-track-ordering-system'); ?></th>
                        <th><?php esc_html_e('Customer', 'custom-track-ordering-system'); ?></th>
                        <th><?php esc_html_e('Service', 'custom-track-ordering-system'); ?></th>
                        <th><?php esc_html_e('Status', 'custom-track-ordering-system'); ?></th>
                        <th><?php esc_html_e('Date', 'custom-track-ordering-system'); ?></th>
                        <th><?php esc_html_e('Actions', 'custom-track-ordering-system'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order) :
                        $customer = get_user_by('id', $order->customer_id);
                        $customer_name = $customer ? $customer->display_name : esc_html__('Unknown Customer', 'custom-track-ordering-system');
                        $status_label = CTOS_MarketKing_Integration::get_status_label($order->status);
                        $status_class = CTOS_MarketKing_Integration::get_status_class($order->status);
                        
                        // FIXED: Get the correct view URL that preserves the page parameter in dashboard
                        if ($is_marketking_dashboard) {
                            $view_url = add_query_arg(array(
                                'page' => 'custom-tracks',
                                'order_id' => $order->order_id
                            ), home_url('/seller-dashboard/'));
                        } else {
                            $view_url = add_query_arg('order_id', $order->order_id);
                        }
                        
                        // Get thread ID for messaging
                        $thread_id = get_post_meta($order->order_id, '_ctos_message_thread_id', true);
                        $has_message_thread = $thread_id && function_exists('marketking_get_message_url');
                        ?>
                        <tr>
                            <td>#<?php echo esc_html($order->order_id); ?></td>
                            <td><?php echo esc_html($customer_name); ?></td>
                            <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $order->service_type))); ?></td>
                            <td>
                                <span class="ctos-order-status <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_label); ?>
                                </span>
                            </td>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($order->created_at)); ?></td>
                            <td class="ctos-order-actions">
                                <!-- View button -->
                                <a href="<?php echo esc_url($view_url); ?>" class="<?php echo $is_marketking_dashboard ? 'btn btn-sm btn-primary' : 'ctos-button ctos-button-secondary'; ?>">
                                    <?php esc_html_e('View', 'custom-track-ordering-system'); ?>
                                </a>
                                
                                <!-- Demo upload button (only show when in pending_demo_submission status) -->
                                <?php if (($order->status === 'pending_demo_submission' || $order->status === 'awaiting_demo') && $order->deposit_paid) : ?>
                                    <button type="button" class="<?php echo $is_marketking_dashboard ? 'btn btn-sm btn-success' : 'ctos-button'; ?>" onclick="document.getElementById('ctos-demo-upload-<?php echo esc_attr($order->order_id); ?>').click();">
                                        <?php esc_html_e('Upload Demo', 'custom-track-ordering-system'); ?>
                                    </button>
                                    <input type="file" id="ctos-demo-upload-<?php echo esc_attr($order->order_id); ?>" class="ctos-demo-upload" data-order-id="<?php echo esc_attr($order->order_id); ?>" accept=".mp3" style="display: none;">
                                <?php endif; ?>
                                
                                <!-- Final files upload button (only show when in awaiting_final_delivery status) -->
                                <?php if ($order->status === 'awaiting_final_delivery' && $order->final_paid) : ?>
                                    <button type="button" class="<?php echo $is_marketking_dashboard ? 'btn btn-sm btn-success' : 'ctos-button'; ?>" onclick="document.getElementById('ctos-final-files-upload-<?php echo esc_attr($order->order_id); ?>').click();">
                                        <?php esc_html_e('Upload Final Files', 'custom-track-ordering-system'); ?>
                                    </button>
                                    <input type="file" id="ctos-final-files-upload-<?php echo esc_attr($order->order_id); ?>" class="ctos-final-files-upload" data-order-id="<?php echo esc_attr($order->order_id); ?>" multiple accept=".mp3,.wav,.zip" style="display: none;">
                                <?php endif; ?>
                                
                                <!-- Messages button (only show if thread exists) -->
                                <?php if ($has_message_thread) : 
                                    $message_url = marketking_get_message_url($thread_id);
                                ?>
                                    <a href="<?php echo esc_url($message_url); ?>" class="<?php echo $is_marketking_dashboard ? 'btn btn-sm btn-warning' : 'ctos-button ctos-button-secondary'; ?>">
                                        <?php esc_html_e('Messages', 'custom-track-ordering-system'); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- JavaScript for handling file uploads -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
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
                var $button = $(this).prev('button');
                var originalText = $button.text();
                $button.text('<?php esc_html_e('Uploading...', 'custom-track-ordering-system'); ?>').prop('disabled', true);
                
                // Submit via AJAX
                $.ajax({
                    url: ctos_vars.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    content                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            alert('<?php esc_html_e('Demo uploaded successfully. The customer will be notified.', 'custom-track-ordering-system'); ?>');
                            window.location.reload();
                        } else {
                            alert('<?php esc_html_e('Error:', 'custom-track-ordering-system'); ?> ' + (response.data || '<?php esc_html_e('Unknown error', 'custom-track-ordering-system'); ?>'));
                            $button.text(originalText).prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('<?php esc_html_e('Error:', 'custom-track-ordering-system'); ?> ' + error);
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
                var $button = $(this).prev('button');
                var originalText = $button.text();
                $button.text('<?php esc_html_e('Uploading...', 'custom-track-ordering-system'); ?>').prop('disabled', true);
                
                // Submit via AJAX
                $.ajax({
                    url: ctos_vars.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            alert('<?php esc_html_e('Final files uploaded successfully. The customer will be notified.', 'custom-track-ordering-system'); ?>');
                            window.location.reload();
                        } else {
                            alert('<?php esc_html_e('Error:', 'custom-track-ordering-system'); ?> ' + (response.data || '<?php esc_html_e('Unknown error', 'custom-track-ordering-system'); ?>'));
                            $button.text(originalText).prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('<?php esc_html_e('Error:', 'custom-track-ordering-system'); ?> ' + error);
                        $button.text(originalText).prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
}
?>
