<?php
/**
 * Template for the order details modal in the producer dashboard
 */
defined('ABSPATH') || exit;

// Get the order details
global $wpdb;
$meta_table = $wpdb->prefix . 'ctos_order_meta';
$order_meta = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $meta_table WHERE order_id = %d",
    $order_id
));

if (!$order_meta) {
    echo '<p class="ctos-error">' . esc_html__('Order not found', 'custom-track-ordering-system') . '</p>';
    return;
}

// Get user info
$producer = get_user_by('id', $order_meta->producer_id);
$customer = get_user_by('id', $order_meta->customer_id);
$producer_name = $producer ? $producer->display_name : __('Unknown Producer', 'custom-track-ordering-system');
$customer_name = $customer ? $customer->display_name : __('Unknown Customer', 'custom-track-ordering-system');

// Get order status info
$status_label = CTOS_MarketKing_Integration::get_status_label($order_meta->status);
$status_class = CTOS_MarketKing_Integration::get_status_class($order_meta->status);

// Get demo and final files
$demo_file = $order_meta->demo_file;
$final_files = !empty($order_meta->final_files) ? json_decode($order_meta->final_files, true) : array();

// Get message thread
$thread_id = get_post_meta($order_id, '_ctos_message_thread_id', true);
$current_user_id = get_current_user_id();
$is_producer = ($current_user_id == $order_meta->producer_id);
$is_customer = ($current_user_id == $order_meta->customer_id);

// Determine permissions
$can_upload_demo = $is_producer && ($order_meta->status == 'pending_demo_submission' || $order_meta->status == 'awaiting_demo') && $order_meta->deposit_paid;
$can_upload_final = $is_producer && $order_meta->status == 'awaiting_final_delivery' && $order_meta->final_paid;
$can_approve_demo = $is_customer && $order_meta->status == 'awaiting_customer_approval';
$can_request_revision = $is_customer && $order_meta->status == 'awaiting_customer_approval';
?>

<div class="ctos-modern-modal">
    <div class="ctos-modal-header">
        <h2>Order #<?php echo esc_html($order_id); ?> - <?php echo esc_html($order_meta->track_title); ?></h2>
        <span class="ctos-status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
        <button class="ctos-close-modal">&times;</button>
    </div>
    
    <div class="ctos-modal-content">
        <div class="ctos-content-columns">
            <!-- Left Column - Order Details -->
            <div class="ctos-left-column">
                <div class="ctos-panel">
                    <h3 class="ctos-panel-title">Order Details</h3>
                    <div class="ctos-detail-row">
                        <span class="ctos-detail-label">Date:</span>
                        <span class="ctos-detail-value"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($order_meta->created_at)); ?></span>
                    </div>
                    <div class="ctos-detail-row">
                        <span class="ctos-detail-label">Service Type:</span>
                        <span class="ctos-detail-value"><?php echo esc_html(ucfirst(str_replace('_', ' ', $order_meta->service_type))); ?></span>
                    </div>
                    <div class="ctos-detail-row">
                        <span class="ctos-detail-label">Customer:</span>
                        <span class="ctos-detail-value"><?php echo esc_html($customer_name); ?></span>
                    </div>
                    <div class="ctos-detail-row">
                        <span class="ctos-detail-label">Producer:</span>
                        <span class="ctos-detail-value"><?php echo esc_html($producer_name); ?></span>
                    </div>
                </div>
                
                <div class="ctos-panel">
                    <h3 class="ctos-panel-title">Track Details</h3>
                    <div class="ctos-detail-row">
                        <span class="ctos-detail-label">Track Name:</span>
                        <span class="ctos-detail-value"><?php echo !empty($order_meta->track_title) ? esc_html($order_meta->track_title) : '-'; ?></span>
                    </div>
                    <div class="ctos-detail-row">
                        <span class="ctos-detail-label">Genre:</span>
                        <span class="ctos-detail-value"><?php echo !empty($order_meta->genre) ? esc_html($order_meta->genre) : '-'; ?></span>
                    </div>
                    <div class="ctos-detail-row">
                        <span class="ctos-detail-label">BPM:</span>
                        <span class="ctos-detail-value"><?php echo !empty($order_meta->bpm) ? esc_html($order_meta->bpm) : '-'; ?></span>
                    </div>
                </div>
                
                <div class="ctos-panel">
                    <h3 class="ctos-panel-title">Order Timeline</h3>
                    <div class="ctos-timeline">
                        <div class="ctos-timeline-item <?php echo !empty($order_meta->created_at) ? 'completed' : ''; ?>">
                            <div class="ctos-timeline-marker"></div>
                            <div class="ctos-timeline-content">
                                <h4>Order Placed</h4>
                                <p><?php echo date_i18n(get_option('date_format'), strtotime($order_meta->created_at)); ?></p>
                            </div>
                        </div>
                        
                        <div class="ctos-timeline-item <?php echo $order_meta->deposit_paid ? 'completed' : ''; ?>">
                            <div class="ctos-timeline-marker"></div>
                            <div class="ctos-timeline-content">
                                <h4>Waiting for Deposit</h4>
                                <p><?php echo $order_meta->deposit_paid ? 'Completed' : 'In Progress'; ?></p>
                            </div>
                        </div>
                        
                        <div class="ctos-timeline-item <?php echo !empty($order_meta->demo_file) ? 'completed' : ''; ?>">
                            <div class="ctos-timeline-marker"></div>
                            <div class="ctos-timeline-content">
                                <h4>Demo Delivery</h4>
                                <p><?php echo !empty($order_meta->demo_file) ? 'Completed' : 'In Progress'; ?></p>
                            </div>
                        </div>
                        
                        <div class="ctos-timeline-item <?php echo $order_meta->final_paid ? 'completed' : ''; ?>">
                            <div class="ctos-timeline-marker"></div>
                            <div class="ctos-timeline-content">
                                <h4>Final Payment</h4>
                                <p><?php echo $order_meta->final_paid ? 'Completed' : 'Waiting'; ?></p>
                            </div>
                        </div>
                        
                        <div class="ctos-timeline-item <?php echo !empty($order_meta->final_files) ? 'completed' : ''; ?>">
                            <div class="ctos-timeline-marker"></div>
                            <div class="ctos-timeline-content">
                                <h4>Final Delivery</h4>
                                <p><?php echo !empty($order_meta->final_files) ? 'Completed' : 'Waiting'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Files and Messages -->
            <div class="ctos-right-column">
                <!-- Files Section -->
                <div class="ctos-panel ctos-files-panel">
                    <div class="ctos-tabs">
                        <div class="ctos-tab active" data-tab="demo-files">Demo Files</div>
                        <div class="ctos-tab" data-tab="final-files">Final Files</div>
                    </div>
                    
                    <div class="ctos-tab-content active" id="ctos-demo-files">
                        <?php if (!empty($demo_file)): ?>
                            <div class="ctos-file-item">
                                <span class="ctos-file-name"><?php echo esc_html($demo_file); ?></span>
                                <div class="ctos-audio-player">
                                    <audio controls>
                                        <source src="<?php echo esc_url(CTOS_File_Handler::get_file_url($demo_file, $order_id, 'demo')); ?>" type="audio/mpeg">
                                        <?php esc_html_e('Your browser does not support the audio element.', 'custom-track-ordering-system'); ?>
                                    </audio>
                                </div>
                                <a href="<?php echo esc_url(CTOS_File_Handler::get_download_url('demo', $order_id)); ?>" class="ctos-button ctos-download-button" download>
                                    <?php esc_html_e('Download', 'custom-track-ordering-system'); ?>
                                </a>
                            </div>
                            
                            <?php if ($can_approve_demo): ?>
                            <div class="ctos-demo-actions">
                                <button type="button" class="ctos-button ctos-primary-button ctos-approve-demo" data-order-id="<?php echo esc_attr($order_id); ?>">
                                    <?php esc_html_e('Approve Demo', 'custom-track-ordering-system'); ?>
                                </button>
                                <button type="button" class="ctos-button ctos-request-revision" data-order-id="<?php echo esc_attr($order_id); ?>">
                                    <?php esc_html_e('Request Revision', 'custom-track-ordering-system'); ?>
                                </button>
                            </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <p class="ctos-no-files">No demo files have been uploaded yet.</p>
                            
                            <?php if ($can_upload_demo): ?>
                            <div class="ctos-upload-section">
                                <button type="button" id="ctos-upload-demo-btn" class="ctos-button ctos-primary-button" data-order-id="<?php echo esc_attr($order_id); ?>">
                                    <?php esc_html_e('Upload Demo', 'custom-track-ordering-system'); ?>
                                </button>
                                <input type="file" id="ctos-demo-file-input" accept=".mp3" style="display: none;" data-order-id="<?php echo esc_attr($order_id); ?>">
                                <div class="ctos-progress" style="display: none;">
                                    <div class="ctos-progress-bar"></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="ctos-tab-content" id="ctos-final-files">
                        <?php if (!empty($final_files)): ?>
                            <?php foreach ($final_files as $index => $file): 
                                $file_name = is_array($file) ? $file['name'] : basename($file);
                                $file_url = is_array($file) ? $file['url'] : CTOS_File_Handler::get_file_url($file, $order_id, 'final');
                            ?>
                                <div class="ctos-file-item">
                                    <span class="ctos-file-name"><?php echo esc_html($file_name); ?></span>
                                    <?php if (strtolower(pathinfo($file_name, PATHINFO_EXTENSION)) === 'mp3'): ?>
                                        <div class="ctos-audio-player">
                                            <audio controls>
                                                <source src="<?php echo esc_url($file_url); ?>" type="audio/mpeg">
                                                <?php esc_html_e('Your browser does not support the audio element.', 'custom-track-ordering-system'); ?>
                                            </audio>
                                        </div>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url(CTOS_File_Handler::get_download_url('final', $order_id, $index)); ?>" class="ctos-button ctos-download-button" download>
                                        <?php esc_html_e('Download', 'custom-track-ordering-system'); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="ctos-no-files">No final files have been uploaded yet.</p>
                            
                            <?php if ($can_upload_final): ?>
                            <div class="ctos-upload-section">
                                <button type="button" id="ctos-upload-final-btn" class="ctos-button ctos-primary-button" data-order-id="<?php echo esc_attr($order_id); ?>">
                                    <?php esc_html_e('Upload Final Files', 'custom-track-ordering-system'); ?>
                                </button>
                                <input type="file" id="ctos-final-files-input" multiple accept=".mp3,.wav,.zip" style="display: none;" data-order-id="<?php echo esc_attr($order_id); ?>">
                                <div class="ctos-progress" style="display: none;">
                                    <div class="ctos-progress-bar"></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Messages Section -->
                <div class="ctos-panel ctos-messages-panel">
                    <h3 class="ctos-panel-title">Messages</h3>
                    
                    <?php if ($thread_id && function_exists('marketking_get_thread_messages')): 
                        $messages = marketking_get_thread_messages($thread_id);
                    ?>
                        <div id="ctos-chat-messages" class="ctos-chat-messages">
                            <?php if (empty($messages)): ?>
                                <p class="ctos-no-messages">No messages yet. Start the conversation!</p>
                            <?php else: ?>
                                <?php foreach ($messages as $message): 
                                    $sender = get_user_by('id', $message->message_author);
                                    $sender_name = $sender ? $sender->display_name : __('System', 'custom-track-ordering-system');
                                    $message_class = $message->message_author == $current_user_id ? 'ctos-message-sent' : 'ctos-message-received';
                                ?>
                                    <div class="ctos-message <?php echo esc_attr($message_class); ?>">
                                        <div class="ctos-message-header">
                                            <span class="ctos-message-sender"><?php echo esc_html($sender_name); ?></span>
                                            <span class="ctos-message-time"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($message->message_date))); ?></span>
                                        </div>
                                        <div class="ctos-message-content">
                                            <p><?php echo nl2br(esc_html($message->message_content)); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <form id="ctos-message-form" class="ctos-message-form" data-thread-id="<?php echo esc_attr($thread_id); ?>" data-order-id="<?php echo esc_attr($order_id); ?>">
                            <textarea id="ctos-message-input" class="ctos-message-input" placeholder="<?php esc_attr_e('Type your message here...', 'custom-track-ordering-system'); ?>"></textarea>
                            <button type="submit" id="ctos-send-message" class="ctos-button ctos-primary-button">
                                <?php esc_html_e('Sending...', 'custom-track-ordering-system'); ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="ctos-no-thread">Messaging is not available for this order.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
