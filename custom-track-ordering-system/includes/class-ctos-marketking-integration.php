<?php
/**
 * Integrates the custom track ordering system with MarketKing.
 */
class CTOS_MarketKing_Integration {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Handle file uploads via AJAX
        add_action('wp_ajax_ctos_upload_demo', array($this, 'ajax_upload_demo'));
        add_action('wp_ajax_ctos_upload_final_files', array($this, 'ajax_upload_final_files'));
        
        // Add AJAX handler for checking if an order is a custom track order
        add_action('wp_ajax_ctos_check_order_type', array($this, 'ajax_check_order_type'));
        
        // Add AJAX handler for getting order details
        add_action('wp_ajax_ctos_get_order_details', array($this, 'ajax_get_order_details'));
        
        // Add AJAX handler for getting producer orders
        add_action('wp_ajax_ctos_get_producer_orders', array($this, 'ajax_get_producer_orders'));
        
        // Add AJAX handlers for order actions
        add_action('wp_ajax_ctos_approve_demo', array($this, 'ajax_approve_demo'));
        add_action('wp_ajax_ctos_request_revision', array($this, 'ajax_request_revision'));
        add_action('wp_ajax_ctos_send_message', array($this, 'ajax_send_message'));
        
        // Add scripts to MarketKing dashboard
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add custom track orders tab to MarketKing dashboard
        add_filter('marketking_dashboard_tabs', array($this, 'add_dashboard_tab'));
        
        // Handle the custom track orders tab content
        add_action('marketking_dashboard_content', array($this, 'dashboard_tab_content'));
        
        // Add our template path to MarketKing template paths
        add_filter('marketking_template_paths', array($this, 'add_template_path'));
    }
    
    /**
     * Add our template path to MarketKing template paths
     */
    public function add_template_path($paths) {
        $paths[50] = CTOS_PLUGIN_DIR . 'templates/';
        return $paths;
    }
    
    /**
     * Add custom track orders tab to MarketKing dashboard
     */
    public function add_dashboard_tab($tabs) {
        // Always add the tab for vendors/producers
        if (self::is_producer()) {
            $tabs['custom-tracks'] = array(
                'title' => __('Custom Tracks', 'custom-track-ordering-system'),
                'icon' => 'music-note',
                'position' => 35 // Position after regular tracks
            );
        }
        return $tabs;
    }
    
    /**
     * Handle the custom track orders tab content
     */
    public function dashboard_tab_content() {
        // Check if this is our tab
        if (!isset($_GET['page']) || $_GET['page'] !== 'custom-tracks') {
            return;
        }
        
        // Check if user is a producer/vendor
        if (!self::is_producer()) {
            echo '<div class="marketking-dashboard-content-wrapper">';
            echo '<p>' . __('You must be logged in as a producer to view this content.', 'custom-track-ordering-system') . '</p>';
            echo '</div>';
            return;
        }
        
        // Show list of all custom track orders
        $producer_id = get_current_user_id();
        include(CTOS_PLUGIN_DIR . 'templates/vendor-track-orders.php');
    }
    
    /**
     * AJAX handler for producer orders
     */
    public function ajax_get_producer_orders() {
        check_ajax_referer('wp_rest', 'nonce');
        
        $producer_id = get_current_user_id();
        
        if (!self::is_producer()) {
            wp_send_json_error(array('message' => 'You must be a producer to view these orders.'));
            return;
        }
        
        // Buffer output
        ob_start();
        include(CTOS_PLUGIN_DIR . 'templates/vendor-track-orders.php');
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        // Only load on relevant pages
        if ((function_exists('marketking') && 
            (is_account_page() || 
             strpos($_SERVER['REQUEST_URI'], 'seller-dashboard') !== false ||
             strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false ||
             isset($_GET['order_id']) || 
             strpos($_SERVER['REQUEST_URI'], 'order_id=') !== false))) {
            
            // Enqueue our custom CSS (unchanged)
            wp_enqueue_style('ctos-public-css', plugin_dir_url(CTOS_PLUGIN_FILE) . 'assets/css/public.css', array(), CTOS_VERSION);
            
            // Enqueue MarketKing integration script with current time for cache busting
            wp_enqueue_script('ctos-marketking', plugin_dir_url(CTOS_PLUGIN_FILE) . 'assets/js/marketking-integration.js', array('jquery'), time(), true);
            
            // Make ajaxurl available
            wp_localize_script('ctos-marketking', 'ajaxurl', admin_url('admin-ajax.php'));
        }
    }
    
    /**
     * AJAX handler for getting order details
     */
    public function ajax_get_order_details() {
        // Verify nonce - accept both our plugin nonce and WP REST nonce
        $nonce_verified = false;
        
        if (isset($_POST['nonce'])) {
            if (wp_verify_nonce($_POST['nonce'], 'ctos-marketking-nonce') || 
                wp_verify_nonce($_POST['nonce'], 'wp_rest')) {
                $nonce_verified = true;
            }
        }
        
        // Allow admin access without nonce for debugging
        if (!$nonce_verified && !current_user_can('manage_options')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Check order ID
        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Missing order ID');
            return;
        }
        
        $order_id = intval($_POST['order_id']);
        $user_id = get_current_user_id();
        
        // Get order meta
        global $wpdb;
        $meta_table = $wpdb->prefix . 'ctos_order_meta';
        $order_meta = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $meta_table WHERE order_id = %d AND (producer_id = %d OR customer_id = %d)",
            $order_id, $user_id, $user_id
        ));
        
        if (!$order_meta) {
            // Allow admin access
            if (current_user_can('manage_options')) {
                $order_meta = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $meta_table WHERE order_id = %d",
                    $order_id
                ));
                
                if (!$order_meta) {
                    wp_send_json_error('Order not found');
                    return;
                }
            } else {
                wp_send_json_error('You do not have permission to view this order');
                return;
            }
        }
        
        // Buffer output
        ob_start();
        
        // Include the template
        include(CTOS_PLUGIN_DIR . 'templates/order-details-modal.php');
        
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
        ));
    }
    
    /**
     * Get all custom track orders for a producer
     */
    public static function get_producer_orders($producer_id) {
        global $wpdb;
        $meta_table = $wpdb->prefix . 'ctos_order_meta';
        
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $meta_table WHERE producer_id = %d ORDER BY created_at DESC",
            $producer_id
        ));
        
        return $orders;
    }
    
    /**
     * Get status label for display
     */
    public static function get_status_label($status) {
        $labels = array(
            'pending' => __('Pending', 'custom-track-ordering-system'),
            'awaiting_deposit' => __('Awaiting Deposit', 'custom-track-ordering-system'),
            'pending_demo_submission' => __('Awaiting Demo', 'custom-track-ordering-system'),
            'awaiting_demo' => __('Awaiting Demo', 'custom-track-ordering-system'),
            'awaiting_customer_approval' => __('Awaiting Approval', 'custom-track-ordering-system'),
            'awaiting_final_payment' => __('Awaiting Payment', 'custom-track-ordering-system'),
            'awaiting_final_delivery' => __('Awaiting Delivery', 'custom-track-ordering-system'),
            'completed' => __('Completed', 'custom-track-ordering-system'),
            'cancelled' => __('Cancelled', 'custom-track-ordering-system')
        );
        
        return isset($labels[$status]) ? $labels[$status] : $status;
    }
    
    /**
     * Get status class for styling
     */
    public static function get_status_class($status) {
        $classes = array(
            'pending' => 'status-pending',
            'awaiting_deposit' => 'status-pending',
            'pending_demo_submission' => 'status-pending',
            'awaiting_demo' => 'status-pending',
            'awaiting_customer_approval' => 'status-processing',
            'awaiting_final_payment' => 'status-processing',
            'awaiting_final_delivery' => 'status-processing',
            'completed' => 'status-completed',
            'cancelled' => 'status-cancelled'
        );
        
        return isset($classes[$status]) ? $classes[$status] : 'status-default';
    }
    
    /**
     * Check if current user is a producer - Improved implementation
     */
    public static function is_producer() {
        // First check if user is logged in
        $user_id = get_current_user_id();
        if ($user_id === 0) {
            return false;
        }
        
        // If user is admin, allow access as well
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Check various ways to identify vendor/producer status
        
        // 1. Check if MarketKing is active and the user is a vendor
        if (function_exists('marketking_is_vendor') && marketking_is_vendor($user_id)) {
            return true;
        }
        
        // 2. Check for vendor role directly
        $user = get_user_by('id', $user_id);
        if ($user && in_array('vendor', (array) $user->roles)) {
            return true;
        }
        
        // 3. Check for any custom producer metadata
        $is_producer_meta = get_user_meta($user_id, '_is_producer', true);
        if ($is_producer_meta === 'yes') {
            return true;
        }
        
        // 4. Check if the user has producer settings
        $settings = get_user_meta($user_id, '_ctos_enable_custom_orders', true);
        if (!empty($settings)) {
            return true;
        }
        
        // 5. Check if the user has any existing track orders as a producer
        global $wpdb;
        $meta_table = $wpdb->prefix . 'ctos_order_meta';
        if ($wpdb->get_var("SHOW TABLES LIKE '$meta_table'") !== $meta_table) {
            // Table doesn't exist yet
            return false;
        }
        
        $producer_orders = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $meta_table WHERE producer_id = %d",
            $user_id
        ));
        
        if ($producer_orders > 0) {
            return true;
        }

        return false;
    }

    /* Other AJAX methods (upload_demo, upload_final_files, etc.) remain unchanged */
}
