<?php
/**
 * Loader class for the Custom Track Ordering System plugin.
 */
class CTOS_Loader {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Make sure our scripts load with priority
        add_action('wp_enqueue_scripts', array($this, 'priority_scripts'), 9999);
        
        // Force scripts loading on MarketKing pages
        add_action('marketking_dashboard_content_before', array($this, 'force_script_loading'), 9999);
    }
    
    /**
     * Run method - kept for backward compatibility
     * 
     * This is no longer needed as functionality is now initialized in the constructor,
     * but we keep it for compatibility with the main plugin file.
     */
    public function run() {
        // Functionality is now in the constructor
        return;
    }
    
    /**
     * Load scripts with highest priority
     */
    public function priority_scripts() {
        // Only on MarketKing dashboard pages
        if (function_exists('marketking') && 
            (is_account_page() || 
             strpos($_SERVER['REQUEST_URI'], 'seller-dashboard') !== false ||
             strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false ||
             isset($_GET['order_id']) || 
             strpos($_SERVER['REQUEST_URI'], 'order_id=') !== false)) {
            
            // Remove any existing instances of our scripts
            wp_dequeue_script('ctos-marketking');
            wp_deregister_script('ctos-marketking');
            
            // Enqueue with highest priority
            wp_enqueue_script('ctos-marketking', plugin_dir_url(CTOS_PLUGIN_FILE) . 'assets/js/marketking-integration.js', array('jquery'), CTOS_VERSION . '.' . time(), true);
            
            // Pass data to script
            wp_localize_script('ctos-marketking', 'ctos_mk', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ctos-marketking-nonce'),
                'message_nonce' => wp_create_nonce('ctos-message-nonce'),
                'uploading' => __('Uploading...', 'custom-track-ordering-system'),
                'upload_success' => __('Files uploaded successfully!', 'custom-track-ordering-system'),
                'upload_error' => __('Error uploading files', 'custom-track-ordering-system'),
                'select_file' => __('Please select a file to upload', 'custom-track-ordering-system')
            ));
        }
    }
    
    /**
     * Force scripts to load on MarketKing dashboard
     */
    public function force_script_loading() {
        // Write script directly to page
        echo '<script src="' . esc_url(plugin_dir_url(CTOS_PLUGIN_FILE) . 'assets/js/marketking-integration.js?ver=' . CTOS_VERSION . '.' . time()) . '" id="ctos-marketking-forced"></script>';
        
        // Also add the script data
        $data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ctos-marketking-nonce'),
            'message_nonce' => wp_create_nonce('ctos-message-nonce'),
            'uploading' => __('Uploading...', 'custom-track-ordering-system'),
            'upload_success' => __('Files uploaded successfully!', 'custom-track-ordering-system'),
            'upload_error' => __('Error uploading files', 'custom-track-ordering-system'),
            'select_file' => __('Please select a file to upload', 'custom-track-ordering-system')
        );
        
        echo '<script>var ctos_mk = ' . json_encode($data) . ';</script>';
    }
}
