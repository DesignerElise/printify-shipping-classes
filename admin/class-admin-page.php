<?php
/**
 * Admin Page
 *
 * Handles the plugin's admin interface.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class PSC_Admin_Page {
    /**
     * Constructor
     */
    public function __construct() {
        // Load settings class
        require_once PSC_PLUGIN_PATH . 'admin/class-settings.php';
        new PSC_Settings();
        
        // Add menu items
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register ajax actions
        add_action('wp_ajax_psc_run_sync', array($this, 'ajax_run_sync'));
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Printify Shipping Classes', 'printify-shipping-class-helper'),
            __('Printify Shipping', 'printify-shipping-class-helper'),
            'manage_woocommerce',
            'printify-shipping-classes',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Render the admin page
     */
    public function render_admin_page() {
        // Get settings
        $settings = get_option('psc_settings', array());
        
        // Get API client
        $api_client = new PSC_API_Client();
        
        // Check if API token is set
        $api_token_set = !empty($settings['api_token']);
        
        // Get shops
        $shops = array();
        $shop_id = isset($settings['shop_id']) ? intval($settings['shop_id']) : 'Waiting for API Key';
        
        if ($api_token_set) {
            $shops_data = $api_client->get_shops();
            if (!is_wp_error($shops_data)) {
                $shops = $shops_data;
            }
        }
        
        // Get shipping classes
        $shipping_class_manager = new PSC_Shipping_Class_Manager();
        $shipping_classes = $shipping_class_manager->get_shipping_classes();
        
        // Get logs
        $logs = PSC_Logger::get_logs(20);
        
        // Include the view
        include PSC_PLUGIN_PATH . 'admin/views/admin-page.php';
    }

    /**
     * AJAX handler for running sync
     */
    public function ajax_run_sync() {
    // Check nonce
    check_ajax_referer('psc_ajax_nonce', 'nonce');
    
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'printify-shipping-class-helper')));
        }
    
    // Get API client
    $api_client = new PSC_API_Client();
    
    // Clear cache
    $api_client->clear_cache();
    
    // Get shipping class manager
    $shipping_class_manager = new PSC_Shipping_Class_Manager();
    
    // Initialize synchronizer
    $synchronizer = new PSC_Data_Synchronizer($api_client, $shipping_class_manager);
    
    // Run sync
    $result = $synchronizer->sync();
    
        // Sanitize result data
        if (isset($result['message'])) {
            $result['message'] = sanitize_text_field($result['message']);
        }
        
        if (isset($result['errors']) && is_array($result['errors'])) {
            foreach ($result['errors'] as $key => $error) {
                $result['errors'][$key] = sanitize_text_field($error);
            }
        }
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
