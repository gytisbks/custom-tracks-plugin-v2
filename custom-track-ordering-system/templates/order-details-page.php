<?php
/**
 * Template for displaying comprehensive order details with chat
 */defined('ABSPATH') || exit;

// Get order ID
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$order_id) {
    wp_die(__('Invalid order ID', 'custom-track-ordering-system'));
}

// Get order meta
global $wpdb;
$meta_table = $wpdb->prefix . 'ctos_order_meta';
$order_meta = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $meta_table WHERE order_id = %d",
    $order_id
));

if (!$order_meta) {
    wp_die(__('Order not found', 'custom-track-ordering-system'));
}

// Check if user has access to this order
$current_user_id = get_current_user_id();
if ($current_user_id != $order_meta->customer_id && $current_user_id != $order_meta->producer_id && !current_user_can('manage_options')) {
    wp_die(__('You do not have permission to view this order', 'custom-track-ordering-system'));
}

// Get user information
$producer = get_user_by('id', $order_meta->producer_id);
$customer = get_user_by('id', $order_meta->customer_id);
$producer_name = $producer ? $producer->display_name : __('Unknown Producer', 'custom-track-ordering-system');
$customer_name = $customer ? $customer->display_name : __('Unknown Customer', 'custom-track-ordering-system');

// Get original order details from WooCommerce
$deposit_order = $order_meta->deposit_order_id ? wc_get_order($order_meta->deposit_order_id) : false;
$final_order = $order_meta->final_order_id ? wc_get_order($order_meta->final_order_id) : false;

// Check if deposit is actually paid (important for accurate timeline)
$deposit_paid = $order_meta->deposit_paid;
if ($deposit_order && !$deposit_paid) {
    // Double-check if the order status indicates payment
    if ($deposit_order->is_paid()) {
        $deposit_paid = true;
        // Update the database
        $wpdb->update(
            $meta_table,
            array('deposit_paid' => 1),
            array('order_id' => $order_id)
        );
    }
}

// Get order data
$order_details = maybe_unserialize($order_meta->order_data);
$reference_tracks = !empty($order_details['reference_tracks']) ? explode(',', $order_details['reference_tracks']) : array();
$selected_addons = !empty($order_details['addons']) ? $order_details['addons'] : array();

// Get status info
$status_label = CTOS_MarketKing_Integration::get_status_label($order_meta->status);
$status_class = CTOS_MarketKing_Integration::get_status_class($order_meta->status);

// Get message thread
$thread_id = get_post_meta($order_id, '_ctos_message_thread_id', true);
if (!$thread_id) {
    // Create message thread if it doesn't exist
    $thread_id = CTOS_Order_Workflow::create_message_thread($order_id, $order_meta->customer_id, $order_meta->producer_id);
}

// Get messages
$messages = array();
if ($thread_id) {
    if (function_exists('marketking_get_messages')) {
        $messages = marketking_get_messages($thread_id);
    } else {
        // Fallback to our custom message system
        $messages = CTOS_Order_Workflow::get_messages($thread_id);
    }
}

// Get demo and final files
$demo_file = $order_meta->demo_file;
$final_files = $order_meta->final_files ? json_decode($order_meta->final_files, true) : array();

get_header('shop');
?>

<div class="ctos-order-details-page">
    <div class="ctos-container">
        <div class="ctos-order-header">
            <h1><?php echo sprintf(__('Order #%s - %s', 'custom-track-ordering-system'), $order_id, $order_details['track_name'] ?? ''); ?></h1>
            <div class="ctos-status-badge <?php echo esc_attr($status_class); ?>">
                <?php echo esc_html($status_label); ?>
            </div>
        </div>

        <div class="ctos-order-layout">
            <!-- Order Timeline and Details Section -->
            <div class="ctos-order-sidebar">
                <div class="ctos-panel">
                    <div class="ctos-panel-header">
                        <h3><?php _e('Order Details', 'custom-track-ordering-system'); ?></h3>
                    </div>
                    <div class="ctos-panel-body">
                        <div class="ctos-order-meta-item">
                            <strong><?php _e('Date:', 'custom-track-ordering-system'); ?></strong>
                            <span><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($order_meta->created_at)); ?></span>
                        </div>

                        <div class="ctos-order-meta-item">
                            <strong><?php _e('Service Type:', 'custom-track-ordering-system'); ?></strong>
                            <span><?php echo esc_html(ucfirst(str_replace('_', ' ', $order_meta->service_type))); ?></span>
                        </div>

                        <div class="ctos-order-meta-item">
                            <strong><?php _e('Customer:', 'custom-track-ordering-system'); ?></strong>
                            <span><?php echo esc_html($customer_name); ?></span>
                        </div>

                        <div class="ctos-order-meta-item">
                            <strong><?php _e('Producer:', 'custom-track-ordering-system'); ?></strong>
                            <span><?php echo esc_html($producer_name); ?></span>
                        </div>

                        <?php if ($deposit_order) : ?>
                            <div class="ctos-order-meta-item">
                                <strong><?php _e('Deposit Order:', 'custom-track-ordering-system'); ?></strong>
                                <span>
                                    <a href="<?php echo esc_url($deposit_order->get_view_order_url()); ?>" target="_blank">#<?php echo $deposit_order->get_order_number(); ?></a> - 
                                    <?php echo $deposit_order->get_formatted_order_total(); ?>
                                    <span class="ctos-payment-status <?php echo $deposit_paid ? 'paid' : 'unpaid'; ?>">
                                        (<?php echo $deposit_paid ? __('Paid', 'custom-track-ordering-system') : __('Pending', 'custom-track-ordering-system'); ?>)
                                    </span>
                                </span>
                            </div>
                        <?php endif; ?>

                        <?php if ($final_order) : ?>
                            <div class="ctos-order-meta-item">
                                <strong><?php _e('Final Payment Order:', 'custom-track-ordering-system'); ?></strong>
                                <span>
                                    <a href="<?php echo esc_url($final_order->get_view_order_url()); ?>" target="_blank">#<?php echo $final_order->get_order_number(); ?></a> - 
                                    <?php echo $final_order->get_formatted_order_total(); ?>
                                    <span class="ctos-payment-status <?php echo $order_meta->final_paid ? 'paid' : 'unpaid'; ?>">
                                        (<?php echo $order_meta->final_paid ? __('Paid', 'custom-track-ordering-system') : __('Pending', 'custom-track-ordering-system'); ?>)
                                    </span>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="ctos-panel">
                    <div class="ctos-panel-header">
                        <h3><?php _e('Track Details', 'custom-track-ordering-system'); ?></h3>
                    </div>
                    <div class="ctos-panel-body">
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

                <!-- Timeline section - FIXED to correctly show deposit paid status -->
                <div class="ctos-panel">
                    <div class="ctos-panel-header">
                        <h3><?php _e('Order Timeline', 'custom-track-ordering-system'); ?></h3>
                    </div>
                    <div class="ctos-panel-body">
                        <ul class="ctos-timeline">
                            <li class="ctos-timeline-item ctos-completed">
                                <div class="ctos-timeline-marker"></div>
                                <div class="ctos-timeline-content">
                                    <h4><?php _e('Order Placed', 'custom-track-ordering-system'); ?></h4>
                                    <p><?php echo date_i18n(get_option('date_format'), strtotime($order_meta->created_at)); ?></p>
                                </div>
                            </li>

                            <?php if ($deposit_paid) : ?>
                                <li class="ctos-timeline-item ctos-completed">
                                    <div class="ctos-timeline-marker"></div>
                                    <div class="ctos-timeline-content">
                                        <h4><?php _e('Deposit Paid', 'custom-track-ordering-system'); ?></h4>
                                        <p><?php echo $deposit_order ? date_i18n(get_option('date_format'), strtotime($deposit_order->get_date_paid() ? $deposit_order->get_date_paid()->date('Y-m-d H:i:s') : $deposit_order->get_date_created()->date('Y-m-d H:i:s'))) : ''; ?></p>
                                    </div>
                                </li>
                            <?php else : ?>
                                <li class="ctos-timeline-item active">
                                    <div class="ctos-timeline-marker"></div>
                                    <div class="ctos-timeline-content">
                                        <h4><?php _e('Waiting for Deposit', 'custom-track-ordering-system'); ?></h4>
                                        <?php if ($deposit_order) : ?>
                                            <a href="<?php echo esc_url($deposit_order->get_checkout_payment_url()); ?>" class="ctos-button ctos-button-secondary">
                                                <?php _e('Pay Deposit', 'custom-track-ordering-system'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endif; ?>

                            <?php if ($demo_file) : ?>
                                <li class="ctos-timeline-item ctos-completed">
                                    <div class="ctos-timeline-marker"></div>
                                    <div class="ctos-timeline-content">
                                        <h4><?php _e('Demo Delivered', 'custom-track-ordering-system'); ?></h4>
                                    </div>
                                </li>
                            <?php elseif ($deposit_paid) : ?>
                                <li class="ctos-timeline-item active">
                                    <div class="ctos-timeline-marker"></div>
                                    <div class="ctos-timeline-content">
                                        <h4><?php _e('Waiting for Demo', 'custom-track-ordering-system'); ?></h4>
                                    </div>
                                </li>
                            <?php else : ?>
                                <li class="ctos-timeline-item">
                                    <div class="ctos-timeline-marker"></div>
                                    <div class="ctos-timeline-content">
                                        <h4><?php _e('Demo Delivery', 'custom-track-ordering-system'); ?></h4>
                                    </div>
                                </li>
                            <?php endif; ?>

                            <?php if ($order_meta->final_paid) : ?>
                                <li class="ctos-timeline-item ctos-completed">
                                    <div class="ctos-timeline-marker"></div>
                                    <div class="ctos-timeline-content">
                                        <h4><?php _e('Final Payment', 'custom-track-ordering-system'); ?></h4>
                                        <p><?php echo $final_order ? date_i18n(get_option('date_format'), strtotime($final_order->get_date_paid() ? $final_order->get_date_paid()->date('Y-m-d H:i:s') : $final_order->get_date_created()->date('Y-m-d H:i:s'))) : ''; ?></p>
                                    </div>
                                </li>
                            <?php elseif ($demo_file) : ?>
                                <li class="ctos-timeline-item active">
                                    <div class="ctos-timeline-marker"></div>
                                    <div class="ctos-timeline-content">
                                        <h4><?php _e('Waiting for Final Payment', 'custom-track-ordering-system'); ?></h4>
                                        <?php if ($final_order && $current_user_id == $order_meta->customer_id) : ?>
                                            <a href="<?php echo esc_url($final_order->get_checkout_payment_url()); ?>" class="ctos-button ctos-button-secondary">
                                                <?php _e('Make Final Payment', 'custom-track-ordering-system'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php else : ?>
                                <li class="ctos-timeline-item">
                                    <div class="ctos-timeline-marker"></div>
                                    <div class="ctos-timeline-content">
                                        <h4><?php _e('Final Payment', 'custom-track-ordering-system'); ?></h4>
                                    </div>
                                </li>
                            <?php endif; ?>

                            <?php if (!empty($final_files)) : ?>
                                <li class="ctos-timeline-item ctos-completed">
                                    <div class="ctos-timeline-marker"></div>
                                    <div class="ctos-timeline-content">
                                        <h4><?php _e('Final Delivery', 'custom-track-ordering-system'); ?></h4>
                                    </div>
                                </li>
                            <?php elseif ($order_meta->final_paid) : ?>
                                <li class="ctos-timeline-item active">
                                    <div class="ctos-timeline-marker"></div>
                                    <div class="ctos-timeline-content">
                                        <h4><?php _e('Waiting for Final Delivery', 'custom-track-ordering-system'); ?></h4>
                                    </div>
                                </li>
                            <?php else : ?>
                                <li class="ctos-timeline-item">
                                    <div class="ctos-timeline-marker"></div>
                                    <div class="ctos-timeline-content">
                                        <h4><?php _e('Final Delivery', 'custom-track-ordering-system'); ?></h4>
                                    </div>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Message and Files Section -->
            <div class="ctos-main-content">
                <!-- Files section -->
                <div class="ctos-panel">
                    <div class="ctos-panel-header">
                        <h3><?php _e('Files', 'custom-track-ordering-system'); ?></h3>
                        
                        <?php if (($order_meta->status === 'pending_demo_submission' || $order_meta->status === 'awaiting_demo') && 
                                  $deposit_paid && $current_user_id == $order_meta->producer_id) : ?>
                            <button type="button" id="ctos-upload-demo-btn" class="ctos-button ctos-button-primary">
                                <?php _e('Upload Demo', 'custom-track-ordering-system'); ?>
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($order_meta->status === 'awaiting_final_delivery' && 
                                  $order_meta->final_paid && $current_user_id == $order_meta->producer_id) : ?>
                            <button type="button" id="ctos-upload-final-btn" class="ctos-button ctos-button-primary">
                                <?php _e('Upload Final Files', 'custom-track-ordering-system'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="ctos-panel-body">
                        <div class="ctos-files-tabs">
                            <div class="ctos-files-tab active" data-tab="demo"><?php _e('Demo Files', 'custom-track-ordering-system'); ?></div>
                            <div class="ctos-files-tab" data-tab="final"><?php _e('Final Files', 'custom-track-ordering-system'); ?></div>
                        </div>
                        
                        <div class="ctos-files-content">
                            <div class="ctos-files-panel active" id="ctos-demo-files">
                                <?php if ($demo_file) : ?>
                                    <div class="ctos-file-item">
                                        <div class="ctos-file-info">
                                            <span class="ctos-file-name"><?php echo esc_html(basename($demo_file)); ?></span>
                                            <span class="ctos-file-type">Demo Track</span>
                                        </div>
                                        <div class="ctos-file-actions">
                                            <audio controls>
                                                <source src="<?php echo esc_url(CTOS_File_Handler::get_file_url($demo_file, $order_id)); ?>" type="audio/mpeg">
                                                <?php _e('Your browser does not support the audio element.', 'custom-track-ordering-system'); ?>
                                            </audio>
                                            <a href="<?php echo esc_url(CTOS_File_Handler::get_download_url('demo', $order_id)); ?>" class="ctos-button ctos-button-secondary">
                                                <?php _e('Download', 'custom-track-ordering-system'); ?>
                                            </a>
                                        </div>
                                    </div>
                                <?php else : ?>
                                    <p class="ctos-no-files"><?php _e('No demo files have been uploaded yet.', 'custom-track-ordering-system'); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="ctos-files-panel" id="ctos-final-files">
                                <?php if (!empty($final_files)) : ?>
                                    <?php foreach ($final_files as $index => $file) : 
                                        $file_url = isset($file['url']) ? $file['url'] : CTOS_File_Handler::get_file_url($file, $order_id, 'final');
                                        $file_name = isset($file['name']) ? $file['name'] : basename($file);
                                        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                                        $is_audio = in_array($file_ext, array('mp3', 'wav'));
                                    ?>
                                        <div class="ctos-file-item">
                                            <div class="ctos-file-info">
                                                <span class="ctos-file-name"><?php echo esc_html($file_name); ?></span>
                                                <span class="ctos-file-type"><?php echo esc_html(strtoupper($file_ext)); ?></span>
                                            </div>
                                            <div class="ctos-file-actions">
                                                <?php if ($is_audio) : ?>
                                                    <audio controls>
                                                        <source src="<?php echo esc_url($file_url); ?>" type="audio/<?php echo $file_ext; ?>">
                                                        <?php _e('Your browser does not support the audio element.', 'custom-track-ordering-system'); ?>
                                                    </audio>
                                                <?php endif; ?>
                                                <a href="<?php echo esc_url(CTOS_File_Handler::get_download_url('final', $order_id, $index)); ?>" class="ctos-button ctos-button-secondary">
                                                    <?php _e('Download', 'custom-track-ordering-system'); ?>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <p class="ctos-no-files"><?php _e('No final files have been uploaded yet.', 'custom-track-ordering-system'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chat section -->
                <div class="ctos-panel ctos-chat-panel">
                    <div class="ctos-panel-header">
                        <h3><?php _e('Messages', 'custom-track-ordering-system'); ?></h3>
                    </div>
                    <div class="ctos-panel-body">
                        <div class="ctos-chat-messages" id="ctos-chat-messages">
                            <?php if (!empty($messages)) : ?>
                                <?php foreach ($messages as $message) : 
                                    $sender_id = isset($message->sender_id) ? $message->sender_id : $message->user_id;
                                    $is_current_user = ($sender_id == $current_user_id);
                                    $sender = get_user_by('id', $sender_id);
                                    $sender_name = $sender ? $sender->display_name : __('Unknown User', 'custom-track-ordering-system');
                                    $timestamp = strtotime(isset($message->date) ? $message->date : $message->created_at);
                                    $message_content = isset($message->message) ? $message->message : $message->content;
                                ?>
                                    <div class="ctos-message <?php echo $is_current_user ? 'ctos-message-sent' : 'ctos-message-received'; ?>">
                                        <div class="ctos-message-header">
                                            <span class="ctos-message-sender"><?php echo esc_html($sender_name); ?></span>
                                            <span class="ctos-message-time"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp); ?></span>
                                        </div>
                                        <div class="ctos-message-content">
                                            <?php echo wp_kses_post(wpautop($message_content)); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <div class="ctos-no-messages">
                                    <?php _e('No messages yet. Start the conversation!', 'custom-track-ordering-system'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="ctos-chat-form">
                            <textarea id="ctos-message-input" placeholder="<?php esc_attr_e('Type your message here...', 'custom-track-ordering-system'); ?>"></textarea>
                            <button type="button" id="ctos-send-message" class="ctos-button ctos-button-primary" data-thread-id="<?php echo esc_attr($thread_id); ?>">
                                <?php _e('Send', 'custom-track-ordering-system'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modals -->
<div class="ctos-modal" id="ctos-demo-modal">
    <div class="ctos-modal-content">
        <div class="ctos-modal-header">
            <h3><?php esc_html_e('Upload Demo Track', 'custom-track-ordering-system'); ?></h3>
            <span class="ctos-modal-close">&times;</span>
        </div>
        <div class="ctos-modal-body">
            <p><?php esc_html_e('Select an MP3 or WAV file to upload as your demo track.', 'custom-track-ordering-system'); ?></p>
            <input type="file" id="ctos-demo-file-input" accept=".mp3,.wav">
            <div class="ctos-progress">
                <div class="ctos-progress-bar"></div>
            </div>
        </div>
        <div class="ctos-modal-footer">
            <button type="button" class="ctos-button ctos-button-secondary ctos-modal-cancel"><?php esc_html_e('Cancel', 'custom-track-ordering-system'); ?></button>
            <button type="button" class="ctos-button ctos-button-primary ctos-modal-upload" data-order-id="<?php echo esc_attr($order_id); ?>"><?php esc_html_e('Upload', 'custom-track-ordering-system'); ?></button>
        </div>
    </div>
</div>

<div class="ctos-modal" id="ctos-final-modal">
    <div class="ctos-modal-content">
        <div class="ctos-modal-header">
            <h3><?php esc_html_e('Upload Final Files', 'custom-track-ordering-system'); ?></h3>
            <span class="ctos-modal-close">&times;</span>
        </div>
        <div class="ctos-modal-body">
            <p><?php esc_html_e('Select files to upload as your final delivery.', 'custom-track-ordering-system'); ?></p>
            <input type="file" id="ctos-final-files-input" multiple accept=".mp3,.wav,.zip">
            <div class="ctos-progress">
                <div class="ctos-progress-bar"></div>
            </div>
        </div>
        <div class="ctos-modal-footer">
            <button type="button" class="ctos-button ctos-button-secondary ctos-modal-cancel"><?php esc_html_e('Cancel', 'custom-track-ordering-system'); ?></button>
            <button type="button" class="ctos-button ctos-button-primary ctos-modal-upload" data-order-id="<?php echo esc_attr($order_id); ?>"><?php esc_html_e('Upload', 'custom-track-ordering-system'); ?></button>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Variables
    const orderId = <?php echo $order_id; ?>;
    const threadId = <?php echo $thread_id ? $thread_id : 0; ?>;
    const currentUserId = <?php echo $current_user_id; ?>;
    const currentUserName = '<?php echo esc_js($current_user_id == $order_meta->producer_id ? $producer_name : $customer_name); ?>';
    
    // Initialize tabs
    $('.ctos-files-tab').on('click', function() {
        const tabName = $(this).data('tab');
        
        // Update active tab
        $('.ctos-files-tab').removeClass('active');
        $(this).addClass('active');
        
        // Show selected panel
        $('.ctos-files-panel').removeClass('active');
        $(`#ctos-${tabName}-files`).addClass('active');
    });
    
    // Handle sending messages
    $('#ctos-send-message').on('click', function() {
        const message = $('#ctos-message-input').val().trim();
        
        if (!message) {
            return;
        }
        
        $(this).prop('disabled', true).text('<?php _e('Sending...', 'custom-track-ordering-system'); ?>');
        
        // Send message via AJAX
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'ctos_send_message',
                thread_id: threadId,
                message: message,
                nonce: '<?php echo wp_create_nonce('ctos-message-nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Clear input
                    $('#ctos-message-input').val('');
                    
                    // Add message to chat
                    const now = new Date();
                    const timeString = now.toLocaleString();
                    
                    const messageHtml = `
                        <div class="ctos-message ctos-message-sent">
                            <div class="ctos-message-header">
                                <span class="ctos-message-sender">${currentUserName}</span>
                                <span class="ctos-message-time">${timeString}</span>
                            </div>
                            <div class="ctos-message-content">
                                <p>${message.replace(/\n/g, '<br>')}</p>
                            </div>
                        </div>
                    `;
                    
                    $('#ctos-chat-messages').append(messageHtml);
                    
                    // If this was the first message, remove the no messages notice
                    $('.ctos-no-messages').remove();
                    
                    // Scroll to bottom
                    $('#ctos-chat-messages').scrollTop($('#ctos-chat-messages')[0].scrollHeight);
                } else {
                    alert('<?php _e('Error sending message. Please try again.', 'custom-track-ordering-system'); ?>: ' + response.data);
                }
                
                // Re-enable button
                $('#ctos-send-message').prop('disabled', false).text('<?php _e('Send', 'custom-track-ordering-system'); ?>');
            },
            error: function() {
                alert('<?php _e('Error sending message. Please try again.', 'custom-track-ordering-system'); ?>');
                $('#ctos-send-message').prop('disabled', false).text('<?php _e('Send', 'custom-track-ordering-system'); ?>');
            }
        });
    });
    
    // Allow sending message with Enter key (Shift+Enter for new line)
    $('#ctos-message-input').on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            $('#ctos-send-message').click();
        }
    });
    
    // Scroll chat to bottom initially
    $('#ctos-chat-messages').scrollTop($('#ctos-chat-messages')[0].scrollHeight);
    
    // Demo upload button
    $('#ctos-upload-demo-btn').on('click', function() {
        $('#ctos-demo-modal').show();
    });
    
    // Final upload button
    $('#ctos-upload-final-btn').on('click', function() {
        $('#ctos-final-modal').show();
    });
    
    // Close modals
    $('.ctos-modal-close, .ctos-modal-cancel').on('click', function() {
        $(this).closest('.ctos-modal').hide();
    });
    
    // Close modal when clicking outside content
    $('.ctos-modal').on('click', function(e) {
        if ($(e.target).hasClass('ctos-modal')) {
            $(this).hide();
        }
    });
    
    // Handle demo upload
    $('#ctos-demo-modal .ctos-modal-upload').on('click', function() {
        const fileInput = $('#ctos-demo-file-input')[0];
        
        if (fileInput.files.length === 0) {
            alert('<?php _e('Please select a file to upload', 'custom-track-ordering-system'); ?>');
            return;
        }
        
        const file = fileInput.files[0];
        const formData = new FormData();
        formData.append('action', 'ctos_upload_demo');
        formData.append('order_id', orderId);
        formData.append('demo_file', file);
        formData.append('nonce', '<?php echo wp_create_nonce('ctos-marketking-nonce'); ?>');
        
        // Show progress bar
        const $progressBar = $('#ctos-demo-modal .ctos-progress');
        const $progressBarInner = $('#ctos-demo-modal .ctos-progress-bar');
        $progressBar.show();
        $progressBarInner.width('0%');
        
        // Disable upload button
        const $uploadBtn = $(this);
        $uploadBtn.text('<?php _e('Uploading...', 'custom-track-ordering-system'); ?>').prop('disabled', true);
        
        // Upload file
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        $progressBarInner.width(percent + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    $progressBarInner.width('100%');
                    alert('<?php _e('Demo uploaded successfully', 'custom-track-ordering-system'); ?>');
                    
                    // Reload page after a short delay
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                } else {
                    alert('<?php _e('Error uploading demo', 'custom-track-ordering-system'); ?>: ' + response.data);
                    $uploadBtn.text('<?php _e('Upload', 'custom-track-ordering-system'); ?>').prop('disabled', false);
                }
            },
            error: function() {
                alert('<?php _e('Error uploading demo. Please try again.', 'custom-track-ordering-system'); ?>');
                $uploadBtn.text('<?php _e('Upload', 'custom-track-ordering-system'); ?>').prop('disabled', false);
            }
        });
    });
    
    // Handle final files upload
    $('#ctos-final-modal .ctos-modal-upload').on('click', function() {
        const fileInput = $('#ctos-final-files-input')[0];
        
        if (fileInput.files.length === 0) {
            alert('<?php _e('Please select at least one file to upload', 'custom-track-ordering-system'); ?>');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'ctos_upload_final_files');
        formData.append('order_id', orderId);
        formData.append('nonce', '<?php echo wp_create_nonce('ctos-marketking-nonce'); ?>');
        
        // Add all files
        for (let i = 0; i < fileInput.files.length; i++) {
            formData.append('file_' + i, fileInput.files[i]);
        }
        
        // Show progress bar
        const $progressBar = $('#ctos-final-modal .ctos-progress');
        const $progressBarInner = $('#ctos-final-modal .ctos-progress-bar');
        $progressBar.show();
        $progressBarInner.width('0%');
        
        // Disable upload button
        const $uploadBtn = $(this);
        $uploadBtn.text('<?php _e('Uploading...', 'custom-track-ordering-system'); ?>').prop('disabled', true);
        
        // Upload files
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        $progressBarInner.width(percent + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    $progressBarInner.width('100%');
                    alert('<?php _e('Files uploaded successfully', 'custom-track-ordering-system'); ?>');
                    
                    // Reload page after a short delay
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                } else {
                    alert('<?php _e('Error uploading files', 'custom-track-ordering-system'); ?>: ' + response.data);
                    $uploadBtn.text('<?php _e('Upload', 'custom-track-ordering-system'); ?>').prop('disabled', false);
                }
            },
            error: function() {
                alert('<?php _e('Error uploading files. Please try again.', 'custom-track-ordering-system'); ?>');
                $uploadBtn.text('<?php _e('Upload', 'custom-track-ordering-system'); ?>').prop('disabled', false);
            }
        });
    });
    
    // Check for order status updates
    function checkOrderStatus() {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'ctos_check_order_status',
                order_id: orderId,
                nonce: '<?php echo wp_create_nonce('ctos-order-nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data) {
                    const data = response.data;
                    
                    // If status has changed, reload the page
                    if (data.status !== '<?php echo $order_meta->status; ?>' ||
                        data.deposit_paid !== <?php echo $deposit_paid ? 'true' : 'false'; ?> ||
                        data.final_paid !== <?php echo $order_meta->final_paid ? 'true' : 'false'; ?> ||
                        data.demo_file !== <?php echo $demo_file ? 'true' : 'false'; ?> ||
                        data.final_files !== <?php echo !empty($final_files) ? 'true' : 'false'; ?>) {
                        window.location.reload();
                    }
                }
            }
        });
    }
    
    // Check status every 30 seconds
    setInterval(checkOrderStatus, 30000);
});
</script>

<?php
get_footer('shop');
