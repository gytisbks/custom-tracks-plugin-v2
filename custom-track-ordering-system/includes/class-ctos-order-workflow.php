<?php
/**
 * Handles order workflow for the Custom Track Ordering System.
 */
class CTOS_Order_Workflow {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add AJAX handlers
        add_action('wp_ajax_ctos_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_ctos_check_order_status', array($this, 'ajax_check_order_status'));
    }
    
    /**
     * AJAX handler for sending messages
     */
    public function ajax_send_message() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ctos-message-nonce')) {
            wp_send_json_error(__('Security check failed', 'custom-track-ordering-system'));
        }
        
        // Check required data
        if (!isset($_POST['thread_id']) || !isset($_POST['message']) || empty($_POST['message'])) {
            wp_send_json_error(__('Missing required data', 'custom-track-ordering-system'));
        }
        
        $thread_id = intval($_POST['thread_id']);
        $message = sanitize_textarea_field($_POST['message']);
        $user_id = get_current_user_id();
        
        // Check if user is logged in
        if (!$user_id) {
            wp_send_json_error(__('You must be logged in to send messages', 'custom-track-ordering-system'));
        }
        
        // Check if MarketKing functions are available
        if (!function_exists('marketking_add_message_to_thread')) {
            // Log the error
            error_log('MarketKing messaging functions not available in CTOS');
            
            // Fallback - store message in custom table
            global $wpdb;
            $messages_table = $wpdb->prefix . 'ctos_messages';
            
            // Create table if it doesn't exist
            if ($wpdb->get_var("SHOW TABLES LIKE '$messages_table'") != $messages_table) {
                $charset_collate = $wpdb->get_charset_collate();
                $sql = "CREATE TABLE $messages_table (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    thread_id mediumint(9) NOT NULL,
                    user_id mediumint(9) NOT NULL,
                    message text NOT NULL,
                    created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                    PRIMARY KEY  (id)
                ) $charset_collate;";
                
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);
            }
            
            // Insert message
            $result = $wpdb->insert(
                $messages_table,
                array(
                    'thread_id' => $thread_id,
                    'user_id' => $user_id,
                    'message' => $message,
                    'created_at' => current_time('mysql')
                )
            );
            
            if ($result) {
                wp_send_json_success(array('message_id' => $wpdb->insert_id));
            } else {
                wp_send_json_error(__('Failed to send message', 'custom-track-ordering-system'));
            }
        } else {
            // Use MarketKing messaging
            $message_id = marketking_add_message_to_thread($thread_id, $user_id, $message);
            
            if ($message_id) {
                wp_send_json_success(array('message_id' => $message_id));
            } else {
                wp_send_json_error(__('Failed to send message', 'custom-track-ordering-system'));
            }
        }
    }
    
    /**
     * AJAX handler for checking order status
     */
    public function ajax_check_order_status() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ctos-order-nonce')) {
            wp_send_json_error(__('Security check failed', 'custom-track-ordering-system'));
        }
        
        // Check required data
        if (!isset($_POST['order_id'])) {
            wp_send_json_error(__('Missing required data', 'custom-track-ordering-system'));
        }
        
        $order_id = intval($_POST['order_id']);
        
        // Get order metadata
        $order_meta = self::get_order_meta($order_id);
        
        if (!$order_meta) {
            wp_send_json_error(__('Order not found', 'custom-track-ordering-system'));
        }
        
        // Get deposit order
        $deposit_paid = false;
        $deposit_order = false;
        
        if ($order_meta->deposit_order_id) {
            $deposit_order = wc_get_order($order_meta->deposit_order_id);
            if ($deposit_order && $deposit_order->is_paid()) {
                $deposit_paid = true;
            }
        }
        
        // Return status information
        wp_send_json_success(array(
            'status' => $order_meta->status,
            'deposit_paid' => $deposit_paid,
            'final_paid' => $order_meta->final_paid,
            'demo_file' => !empty($order_meta->demo_file),
            'final_files' => !empty($order_meta->final_files)
        ));
    }
    
    /**
     * Get custom track order metadata
     */
    public static function get_order_meta($order_id) {
        global $wpdb;
        $meta_table = $wpdb->prefix . 'ctos_order_meta';
        
        $order_meta = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $meta_table WHERE order_id = %d",
            $order_id
        ));
        
        return $order_meta;
    }
    
    /**
     * Create a message thread for a custom track order
     */
    public static function create_message_thread($order_id, $customer_id, $producer_id) {
        // Check if MarketKing messaging is available
        if (!function_exists('marketking_create_message_thread')) {
            // Create custom thread
            global $wpdb;
            $threads_table = $wpdb->prefix . 'ctos_message_threads';
            
            // Create table if it doesn't exist
            if ($wpdb->get_var("SHOW TABLES LIKE '$threads_table'") != $threads_table) {
                $charset_collate = $wpdb->get_charset_collate();
                $sql = "CREATE TABLE $threads_table (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    customer_id mediumint(9) NOT NULL,
                    producer_id mediumint(9) NOT NULL,
                    order_id mediumint(9) NOT NULL,
                    title varchar(255) NOT NULL,
                    created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                    PRIMARY KEY  (id)
                ) $charset_collate;";
                
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);
            }
            
            // Thread title
            $thread_title = sprintf(__('Custom Track Order #%s', 'custom-track-ordering-system'), $order_id);
            
            // Insert thread
            $result = $wpdb->insert(
                $threads_table,
                array(
                    'customer_id' => $customer_id,
                    'producer_id' => $producer_id,
                    'order_id' => $order_id,
                    'title' => $thread_title,
                    'created_at' => current_time('mysql')
                )
            );
            
            if ($result) {
                $thread_id = $wpdb->insert_id;
                update_post_meta($order_id, '_ctos_message_thread_id', $thread_id);
                return $thread_id;
            }
            
            return false;
        }
        
        // Get customer and producer info
        $customer = get_user_by('id', $customer_id);
        $producer = get_user_by('id', $producer_id);
        
        if (!$customer || !$producer) {
            return false;
        }
        
        // Create a thread title
        $thread_title = sprintf(__('Custom Track Order #%s', 'custom-track-ordering-system'), $order_id);
        
        // Create the thread
        $thread_id = marketking_create_message_thread(
            $customer_id,
            $producer_id,
            $thread_title,
            sprintf(__('This thread is for communication about your custom track order #%s. Please use this to discuss requirements and provide feedback.', 'custom-track-ordering-system'), $order_id)
        );
        
        if ($thread_id) {
            // Save thread ID to order meta
            update_post_meta($order_id, '_ctos_message_thread_id', $thread_id);
            return $thread_id;
        }
        
        return false;
    }
    
    /**
     * Update order status
     */
    public static function update_order_status($order_id, $status, $additional_data = array()) {
        global $wpdb;
        $meta_table = $wpdb->prefix . 'ctos_order_meta';
        
        $data = array('status' => $status);
        
        // Merge additional data
        if (!empty($additional_data) && is_array($additional_data)) {
            $data = array_merge($data, $additional_data);
        }
        
        // Update the order status
        $result = $wpdb->update(
            $meta_table,
            $data,
            array('order_id' => $order_id)
        );
        
        return $result;
    }
    
    /**
     * Get URL for order details page
     */
    public static function get_order_details_url($order_id) {
        $url = home_url('/track-order-details/?order_id=' . $order_id);
        return $url;
    }
    
    /**
     * Get messages for a thread (fallback function if MarketKing is not available)
     */
    public static function get_messages($thread_id) {
        global $wpdb;
        $messages_table = $wpdb->prefix . 'ctos_messages';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$messages_table'") != $messages_table) {
            return array();
        }
        
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $messages_table WHERE thread_id = %d ORDER BY created_at ASC",
            $thread_id
        ));
        
        return $messages;
    }
}
