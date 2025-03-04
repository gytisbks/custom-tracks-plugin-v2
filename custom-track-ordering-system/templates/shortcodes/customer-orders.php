<?php
/**
 * Shortcode template for displaying customer's track orders
 */
defined('ABSPATH') || exit;

$customer_id = get_current_user_id();

// Get customer's track orders
$orders = CTOS_MarketKing_Integration::get_customer_orders($customer_id);
?>

<div class="ctos-orders-container">
    <h2><?php _e('Your Custom Track Orders', 'custom-track-ordering-system'); ?></h2>
    
    <?php if (empty($orders)) : ?>
        <p><?php _e('You have no custom track orders yet.', 'custom-track-ordering-system'); ?></p>
    <?php else : ?>
        <table class="ctos-orders-table">
            <thead>
                <tr>
                    <th><?php _e('Order', 'custom-track-ordering-system'); ?></th>
                    <th><?php _e('Producer', 'custom-track-ordering-system'); ?></th>
                    <th><?php _e('Service', 'custom-track-ordering-system'); ?></th>
                    <th><?php _e('Status', 'custom-track-ordering-system'); ?></th>
                    <th><?php _e('Date', 'custom-track-ordering-system'); ?></th>
                    <th><?php _e('Actions', 'custom-track-ordering-system'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order) : 
                    $producer = get_user_by('id', $order->producer_id);
                    $producer_name = $producer ? $producer->display_name : __('Unknown Producer', 'custom-track-ordering-system');
                    $status_label = CTOS_MarketKing_Integration::get_status_label($order->status);
                    $status_class = CTOS_MarketKing_Integration::get_status_class($order->status);
                    ?>
                    <tr>
                        <td>#<?php echo $order->order_id; ?></td>
                        <td><?php echo esc_html($producer_name); ?></td>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $order->service_type))); ?></td>
                        <td><span class="ctos-order-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
                        <td><?php echo date_i18n(get_option('date_format'), strtotime($order->created_at)); ?></td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg('order_id', $order->order_id, get_permalink())); ?>" class="ctos-button ctos-button-secondary"><?php _e('View Details', 'custom-track-ordering-system'); ?></a>
                            
                            <?php if ($order->status === 'awaiting_customer_approval') : ?>
                                <a href="#" class="ctos-button ctos-approve-demo" data-order-id="<?php echo $order->order_id; ?>"><?php _e('Approve Demo', 'custom-track-ordering-system'); ?></a>
                                <a href="#" class="ctos-button ctos-button-secondary ctos-request-revision" data-order-id="<?php echo $order->order_id; ?>"><?php _e('Request Revision', 'custom-track-ordering-system'); ?></a>
                            <?php endif; ?>
                            
                            <?php if ($order->status === 'awaiting_final_payment') : 
                                // Find final payment order
                                $args = array(
                                    'meta_key' => '_ctos_original_order_id',
                                    'meta_value' => $order->order_id,
                                    'post_type' => 'shop_order',
                                    'post_status' => 'any',
                                    'posts_per_page' => 1,
                                );
                                
                                $orders_query = new WP_Query($args);
                                if ($orders_query->have_posts()) {
                                    $final_order_id = $orders_query->posts[0]->ID;
                                    $final_order = wc_get_order($final_order_id);
                                    
                                    if ($final_order && $final_order->needs_payment()) {
                                        ?>
                                        <a href="<?php echo esc_url($final_order->get_checkout_payment_url()); ?>" class="ctos-button"><?php _e('Pay Balance', 'custom-track-ordering-system'); ?></a>
                                        <?php
                                    }
                                }
                            endif; ?>
                            
                            <?php if ($order->status === 'completed') : ?>
                                <a href="<?php echo esc_url(CTOS_File_Handler::get_download_url('final', $order->order_id, 'final')); ?>" class="ctos-button"><?php _e('Download Files', 'custom-track-ordering-system'); ?></a>
                            <?php endif; ?>
                            
                            <?php
                            // Show link to conversation if MarketKing Messages is active
                            $thread_id = get_post_meta($order->order_id, '_ctos_message_thread_id', true);
                            if ($thread_id && function_exists('marketking_get_message_url')) {
                                $message_url = marketking_get_message_url($thread_id);
                                ?>
                                <a href="<?php echo esc_url($message_url); ?>" class="ctos-button ctos-button-secondary"><?php _e('Messages', 'custom-track-ordering-system'); ?></a>
                                <?php
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php
        // Show single order details if order_id is provided in URL
        if (isset($_GET['order_id'])) {
            $order_id = intval($_GET['order_id']);
            
            global $wpdb;
            $meta_table = $wpdb->prefix . 'ctos_order_meta';
            $order_meta = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $meta_table WHERE order_id = %d AND customer_id = %d",
                $order_id, $customer_id
            ));
            
            if ($order_meta) {
                include(plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/track-order-details.php');
            } else {
                echo '<p>' . __('Order not found or you do not have permission to view it.', 'custom-track-ordering-system') . '</p>';
            }
        }
        ?>
        
        <div id="ctos-revision-form-container" style="display: none; margin-top: 30px;">
            <h3><?php _e('Request Revision', 'custom-track-ordering-system'); ?></h3>
            <form id="ctos-revision-form" class="ctos-form">
                <input type="hidden" id="ctos-revision-order-id" name="order_id" value="">
                <div class="ctos-form-row">
                    <label for="ctos-revision-notes" class="ctos-form-label"><?php _e('Revision Notes', 'custom-track-ordering-system'); ?></label>
                    <textarea id="ctos-revision-notes" name="revision_notes" class="ctos-textarea" required></textarea>
                    <p class="ctos-form-help"><?php _e('Describe in detail what changes you would like the producer to make.', 'custom-track-ordering-system'); ?></p>
                </div>
                <div class="ctos-form-row">
                    <button type="submit" class="ctos-button"><?php _e('Submit Revision Request', 'custom-track-ordering-system'); ?></button>
                    <button type="button" class="ctos-button ctos-button-secondary" onclick="document.getElementById('ctos-revision-form-container').style.display='none';"><?php _e('Cancel', 'custom-track-ordering-system'); ?></button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Show revision form
    $('.ctos-request-revision').on('click', function(e) {
        e.preventDefault();
        var orderId = $(this).data('order-id');
        $('#ctos-revision-order-id').val(orderId);
        $('#ctos-revision-form-container').show();
        $('html, body').animate({
            scrollTop: $('#ctos-revision-form-container').offset().top - 50
        }, 500);
    });
    
    // Submit revision request
    $('#ctos-revision-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'ctos_request_revision');
        formData.append('nonce', ctos_vars.nonce);
        
        var $submitButton = $(this).find('button[type="submit"]');
        $submitButton.text('Submitting...').prop('disabled', true);
        
        $.ajax({
            url: ctos_vars.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Revision request submitted successfully.');
                    window.location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                    $submitButton.text('Submit Revision Request').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
                $submitButton.text('Submit Revision Request').prop('disabled', false);
            }
        });
    });
    
    // Approve demo
    $('.ctos-approve-demo').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to approve this demo? This will trigger the final payment process.')) {
            return;
        }
        
        var orderId = $(this).data('order-id');
        var $button = $(this);
        
        $button.text('Processing...').prop('disabled', true);
        
        $.ajax({
            url: ctos_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'ctos_approve_demo',
                order_id: orderId,
                nonce: ctos_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.payment_url) {
                        if (confirm('Demo approved! Would you like to proceed to payment now?')) {
                            window.location.href = response.data.payment_url;
                        } else {
                            window.location.reload();
                        }
                    } else {
                        alert('Demo approved successfully!');
                        window.location.reload();
                    }
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                    $button.text('Approve Demo').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
                $button.text('Approve Demo').prop('disabled', false);
            }
        });
    });
    
    // Complete order
    $('.ctos-complete-order').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to mark this order as complete? This confirms you have received the final files.')) {
            return;
        }
        
        var orderId = $(this).data('order-id');
        var $button = $(this);
        
        $button.text('Processing...').prop('disabled', true);
        
        $.ajax({
            url: ctos_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'ctos_complete_order',
                order_id: orderId,
                nonce: ctos_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Order completed successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                    $button.text('Confirm Receipt').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
                $button.text('Confirm Receipt').prop('disabled', false);
            }
        });
    });
});
</script>
