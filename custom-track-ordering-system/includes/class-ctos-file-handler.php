<?php
/**
 * Handles file uploads and downloads for the Custom Track Ordering System.
 */
class CTOS_File_Handler {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Ajax handlers for file operations
        add_action('wp_ajax_ctos_upload_demo', array($this, 'ajax_upload_demo'));
        add_action('wp_ajax_ctos_upload_final_files', array($this, 'ajax_upload_final_files'));
        add_action('wp_ajax_ctos_download_file', array($this, 'ajax_download_file'));
        add_action('wp_ajax_nopriv_ctos_download_file', array($this, 'ajax_download_file')); // Allow non-logged in users to download
        
        // Add endpoint for file downloads
        add_action('init', array($this, 'add_download_endpoint'));
        add_action('parse_request', array($this, 'handle_download_request'));
    }
    
    /**
     * Add download endpoint
     */
    public function add_download_endpoint() {
        add_rewrite_rule(
            'ctos-download/([^/]+)/([0-9]+)/?([0-9]*)/?$',
            'index.php?ctos_file_type=$matches[1]&ctos_order_id=$matches[2]&ctos_file_index=$matches[3]',
            'top'
        );
        
        add_rewrite_tag('%ctos_file_type%', '([^/]+)');
        add_rewrite_tag('%ctos_order_id%', '([0-9]+)');
        add_rewrite_tag('%ctos_file_index%', '([0-9]*)');
    }
    
    /**
     * Handle download request
     */
    public function handle_download_request($wp) {
        if (isset($wp->query_vars['ctos_file_type']) && isset($wp->query_vars['ctos_order_id'])) {
            $file_type = sanitize_text_field($wp->query_vars['ctos_file_type']);
            $order_id = intval($wp->query_vars['ctos_order_id']);
            $file_index = isset($wp->query_vars['ctos_file_index']) ? intval($wp->query_vars['ctos_file_index']) : 0;
            
            $this->process_download($file_type, $order_id, $file_index);
            exit;
        }
    }
    
    /**
     * Process file download
     */
    public function process_download($file_type, $order_id, $file_index = 0) {
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
        
        // Allow admins to download
        if ($current_user_id != $order_meta->customer_id && 
            $current_user_id != $order_meta->producer_id && 
            !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to download this file', 'custom-track-ordering-system'));
        }
        
        // Get file path based on type
        $file_path = '';
        $file_name = '';
        
        if ($file_type === 'demo') {
            // Get demo file
            if (empty($order_meta->demo_file)) {
                wp_die(__('No demo file found', 'custom-track-ordering-system'));
            }
            
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/ctos_files/' . $order_id . '/demos/' . $order_meta->demo_file;
            $file_name = $order_meta->demo_file;
        } elseif ($file_type === 'final') {
            // Get final files
            $final_files = !empty($order_meta->final_files) ? json_decode($order_meta->final_files, true) : array();
            
            if (empty($final_files) || !isset($final_files[$file_index])) {
                wp_die(__('No final file found', 'custom-track-ordering-system'));
            }
            
            $file_info = $final_files[$file_index];
            $file_name = is_array($file_info) ? $file_info['name'] : basename($file_info);
            
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/ctos_files/' . $order_id . '/final/' . $file_name;
        } else {
            wp_die(__('Invalid file type', 'custom-track-ordering-system'));
        }
        
        // Check if file exists
        if (!file_exists($file_path)) {
            wp_die(__('File not found', 'custom-track-ordering-system'));
        }
        
        // Set headers and serve file
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    }
    
    /**
     * Generate download URL for a file
     */
    public static function get_download_url($file_type, $order_id, $file_index = 0) {
        return home_url('ctos-download/' . $file_type . '/' . $order_id . '/' . $file_index);
    }
    
    /**
     * Get URL for a file
     */
    public static function get_file_url($file_name, $order_id = null, $file_type = 'demo') {
        // If it's already a URL, return it
        if (filter_var($file_name, FILTER_VALIDATE_URL)) {
            return $file_name;
        }
        
        if (!$order_id) {
            // Try to extract order ID from path
            if (preg_match('/\/ctos_files\/(\d+)\//', $file_name, $matches)) {
                $order_id = $matches[1];
            } else {
                return '';
            }
        }
        
        $upload_dir = wp_upload_dir();
        
        // If it's a demo file
        if ($file_type === 'demo') {
            return $upload_dir['baseurl'] . '/ctos_files/' . $order_id . '/demos/' . basename($file_name);
        }
        
        // If it's a final file
        return $upload_dir['baseurl'] . '/ctos_files/' . $order_id . '/final/' . basename($file_name);
    }
    
    /**
     * AJAX handler for demo upload
     */
    public function ajax_upload_demo() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ctos-marketking-nonce')) {
            wp_send_json_error(__('Security check failed', 'custom-track-ordering-system'));
        }
        
        // Check file and order ID
        if (!isset($_FILES['demo_file']) || !isset($_POST['order_id'])) {
            wp_send_json_error(__('Missing required data', 'custom-track-ordering-system'));
        }
        
        $order_id = intval($_POST['order_id']);
        $producer_id = get_current_user_id();
        
        // Verify producer has access to this order
        global $wpdb;
        $meta_table = $wpdb->prefix . 'ctos_order_meta';
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $meta_table WHERE order_id = %d AND producer_id = %d",
            $order_id, $producer_id
        ));
        
        if (!$order) {
            wp_send_json_error(__('You do not have permission to upload to this order', 'custom-track-ordering-system'));
        }
        
        // Check if deposit is paid
        if (!$order->deposit_paid) {
            wp_send_json_error(__('Cannot upload demo until deposit is paid', 'custom-track-ordering-system'));
        }
        
        // Upload the file
        $upload_dir = wp_upload_dir();
        $ctos_dir = $upload_dir['basedir'] . '/ctos_files/' . $order_id . '/demos/';
        
        // Create directory if it doesn't exist
        if (!file_exists($ctos_dir)) {
            wp_mkdir_p($ctos_dir);
        }
        
        $file = $_FILES['demo_file'];
        $filename = sanitize_file_name($file['name']);
        $target_path = $ctos_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            // Update order status
            $demo_url = $upload_dir['baseurl'] . '/ctos_files/' . $order_id . '/demos/' . $filename;
            
            $wpdb->update(
                $meta_table,
                array(
                    'status' => 'awaiting_customer_approval',
                    'demo_url' => $demo_url,
                    'demo_file' => $filename,
                ),
                array('order_id' => $order_id)
            );
            
            // Send email notification to customer
            $customer_id = $order->customer_id;
            $customer = get_user_by('id', $customer_id);
            if ($customer) {
                $order_url = CTOS_Order_Workflow::get_order_details_url($order_id);
                $subject = sprintf(__('Demo Ready for Review - Order #%s', 'custom-track-ordering-system'), $order_id);
                $message = sprintf(__('The producer has submitted a demo track for your order #%s. Please log in to your account to listen to it and provide feedback. You can access your order here: %s', 'custom-track-ordering-system'), $order_id, $order_url);
                
                wp_mail($customer->user_email, $subject, $message);
            }
            
            // Add message to thread if it exists
            $thread_id = get_post_meta($order_id, '_ctos_message_thread_id', true);
            if ($thread_id && function_exists('marketking_add_message_to_thread')) {
                marketking_add_message_to_thread(
                    $thread_id,
                    $producer_id,
                    sprintf(__('I have uploaded a demo for your review. You can listen to it in the order details.', 'custom-track-ordering-system'))
                );
            }
            
            wp_send_json_success(__('Demo uploaded successfully', 'custom-track-ordering-system'));
        } else {
            wp_send_json_error(__('Failed to upload file', 'custom-track-ordering-system'));
        }
    }
    
    /**
     * AJAX handler for final files upload
     */
    public function ajax_upload_final_files() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ctos-marketking-nonce')) {
            wp_send_json_error(__('Security check failed', 'custom-track-ordering-system'));
        }
        
        // Check files and order ID
        if (empty($_FILES) || !isset($_POST['order_id'])) {
            wp_send_json_error(__('Missing required data', 'custom-track-ordering-system'));
        }
        
        $order_id = intval($_POST['order_id']);
        $producer_id = get_current_user_id();
        
        // Verify producer has access to this order
        global $wpdb;
        $meta_table = $wpdb->prefix . 'ctos_order_meta';
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $meta_table WHERE order_id = %d AND producer_id = %d",
            $order_id, $producer_id
        ));
        
        if (!$order) {
            wp_send_json_error(__('You do not have permission to upload to this order', 'custom-track-ordering-system'));
        }
        
        // Check if final payment is made
        if (!$order->final_paid) {
            wp_send_json_error(__('Cannot upload final files until final payment is made', 'custom-track-ordering-system'));
        }
        
        // Upload the files
        $upload_dir = wp_upload_dir();
        $ctos_dir = $upload_dir['basedir'] . '/ctos_files/' . $order_id . '/final/';
        
        // Create directory if it doesn't exist
        if (!file_exists($ctos_dir)) {
            wp_mkdir_p($ctos_dir);
        }
        
        $uploaded_files = array();
        
        foreach ($_FILES as $key => $file_array) {
            if (is_array($file_array['name'])) {
                // Multiple files
                for ($i = 0; $i < count($file_array['name']); $i++) {
                    if ($file_array['error'][$i] === 0) {
                        $filename = sanitize_file_name($file_array['name'][$i]);
                        $target_path = $ctos_dir . $filename;
                        
                        if (move_uploaded_file($file_array['tmp_name'][$i], $target_path)) {
                            $uploaded_files[] = array(
                                'name' => $filename,
                                'url' => $upload_dir['baseurl'] . '/ctos_files/' . $order_id . '/final/' . $filename
                            );
                        }
                    }
                }
            } else {
                // Single file
                if ($file_array['error'] === 0) {
                    $filename = sanitize_file_name($file_array['name']);
                    $target_path = $ctos_dir . $filename;
                    
                    if (move_uploaded_file($file_array['tmp_name'], $target_path)) {
                        $uploaded_files[] = array(
                            'name' => $filename,
                            'url' => $upload_dir['baseurl'] . '/ctos_files/' . $order_id . '/final/' . $filename
                        );
                    }
                }
            }
        }
        
        if (!empty($uploaded_files)) {
            // Update order status
            $wpdb->update(
                $meta_table,
                array(
                    'status' => 'completed',
                    'final_files' => json_encode($uploaded_files)
                ),
                array('order_id' => $order_id)
            );
            
            // Send email notification to customer
            $customer_id = $order->customer_id;
            $customer = get_user_by('id', $customer_id);
            if ($customer) {
                $order_url = CTOS_Order_Workflow::get_order_details_url($order_id);
                $subject = sprintf(__('Your Final Track is Ready - Order #%s', 'custom-track-ordering-system'), $order_id);
                $message = sprintf(__('The producer has delivered the final files for your order #%s. You can download them from your account. Visit your order details here: %s', 'custom-track-ordering-system'), $order_id, $order_url);
                
                wp_mail($customer->user_email, $subject, $message);
            }
            
            // Add message to thread if it exists
            $thread_id = get_post_meta($order_id, '_ctos_message_thread_id', true);
            if ($thread_id && function_exists('marketking_add_message_to_thread')) {
                marketking_add_message_to_thread(
                    $thread_id,
                    $producer_id,
                    sprintf(__('I have uploaded the final files for your order. You can download them from the order details.', 'custom-track-ordering-system'))
                );
            }
            
            wp_send_json_success(__('Final files uploaded successfully', 'custom-track-ordering-system'));
        } else {
            wp_send_json_error(__('Failed to upload files', 'custom-track-ordering-system'));
        }
    }
    
    /**
     * AJAX handler for downloading a file
     */
    public function ajax_download_file() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ctos-download-nonce')) {
            wp_send_json_error(__('Security check failed', 'custom-track-ordering-system'));
        }
        
        // Check required data
        if (!isset($_POST['file_type']) || !isset($_POST['order_id'])) {
            wp_send_json_error(__('Missing required data', 'custom-track-ordering-system'));
        }
        
        $file_type = sanitize_text_field($_POST['file_type']);
        $order_id = intval($_POST['order_id']);
        $file_index = isset($_POST['file_index']) ? intval($_POST['file_index']) : 0;
        
        // Process the download
        $this->process_download($file_type, $order_id, $file_index);
    }
}
