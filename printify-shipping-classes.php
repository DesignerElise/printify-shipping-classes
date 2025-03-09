<?php
/**
 * Plugin Name: Printify Shipping Classes Generator
 * Plugin URI: https://UXSlayer.com/
 * Description: Automatically generates WooCommerce shipping classes from Printify providers and products.
 * Version: 1.0.0
 * Author: Elise Teddington the UX Slayer
 * Author URI: https://UXSlayer.com
 * Text Domain: printify-shipping-class-helper
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 8.0.0
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PSC_PLUGIN_FILE', __FILE__);
define('PSC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PSC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PSC_VERSION', '1.0.0');

/**
 * HPOS compatibility declaration
 */
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Check if WooCommerce is active
 */
function psc_is_woocommerce_active() {
    $active_plugins = (array) get_option('active_plugins', array());
    if (is_multisite()) {
        $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
    }
    return in_array('woocommerce/woocommerce.php', $active_plugins) || array_key_exists('woocommerce/woocommerce.php', $active_plugins);
}

/**
 * Get the WC Logger instance
 *
 * @return WC_Logger_Interface
 */
function psc_get_wc_logger() {
    if (function_exists('wc_get_logger')) {
        return wc_get_logger();
    }
    return null;
}

/**
 * Log a message to WooCommerce logs
 *
 * @param string $message Message to log
 * @param string $level One of: emergency, alert, critical, error, warning, notice, info, debug
 */
function psc_log($message, $level = 'info') {
    $logger = psc_get_wc_logger();
    if ($logger) {
        $context = array('source' => 'printify-shipping-classes');
        $logger->log($level, $message, $context);
    }
}

/**
 * Load plugin text domain for translations
 */
function psc_load_textdomain() {
    load_plugin_textdomain(
        'printify-shipping-class-helper',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'psc_load_textdomain', 9); 

/**
 * Initialize the plugin
 */
function psc_init() {
    // Check if WooCommerce is active
    if (!psc_is_woocommerce_active()) {
        add_action('admin_notices', 'psc_woocommerce_missing_notice');
        return;
    }

    // Load plugin files
    require_once PSC_PLUGIN_PATH . 'includes/class-logger.php';
    require_once PSC_PLUGIN_PATH . 'includes/class-api-client.php';
    require_once PSC_PLUGIN_PATH . 'includes/class-shipping-class-manager.php';
    require_once PSC_PLUGIN_PATH . 'includes/class-data-synchronizer.php';
    
    // Load admin files
    if (is_admin()) {
        require_once PSC_PLUGIN_PATH . 'admin/class-admin-page.php';
        require_once PSC_PLUGIN_PATH . 'admin/class-settings.php';
        
        // Initialize admin classes
        new PSC_Admin_Page();
    }
}
add_action('plugins_loaded', 'psc_init');

/**
 * Admin notice for missing WooCommerce
 */
function psc_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php esc_html_e('Printify Shipping Classes Generator requires WooCommerce to be installed and active.', 'printify-shipping-class-helper'); ?></p>
    </div>
    <?php
}

/**
 * Activation hook
 */
function psc_activate() {
    // Check if WooCommerce is active
    if (!psc_is_woocommerce_active()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(esc_html__('Printify Shipping Classes Generator requires WooCommerce to be installed and active.', 'printify-shipping-class-helper'));
    }

    // Create required database tables and options
    update_option('psc_version', PSC_VERSION);
    
    // Schedule events
    if (!wp_next_scheduled('psc_daily_sync')) {
        wp_schedule_event(time(), 'daily', 'psc_daily_sync');
    }
    
    // Log activation
    psc_log('Printify Shipping Classes Generator activated. Version: ' . PSC_VERSION);
}
register_activation_hook(__FILE__, 'psc_activate');

/**
 * Deactivation hook
 */
function psc_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook('psc_daily_sync');
    
    // Log deactivation
    psc_log('Printify Shipping Classes Generator deactivated.');
}
register_deactivation_hook(__FILE__, 'psc_deactivate');

/**
 * Daily sync action hook
 */
function psc_do_daily_sync() {
    // Get settings
    $settings = get_option('psc_settings', array());
    
    // Check if auto sync is enabled
    if (isset($settings['auto_sync']) && $settings['auto_sync'] === 'yes') {
        psc_log('Starting scheduled daily sync');
        
        // Initialize the synchronizer and run sync
        $api_client = new PSC_API_Client();
        $shipping_class_manager = new PSC_Shipping_Class_Manager();
        $synchronizer = new PSC_Data_Synchronizer($api_client, $shipping_class_manager);
        $result = $synchronizer->sync();
        
        // Log result
        if ($result['success']) {
            psc_log(sprintf(
                'Daily sync completed. Created: %d, Updated: %d, Errors: %d', 
                absint($result['created']), 
                absint($result['updated']), 
                is_array($result['errors']) ? absint(count($result['errors'])) : 0
            ));
        } else {
            psc_log('Daily sync failed: ' . sanitize_text_field($result['message']), 'error');
        }
    }
}
add_action('psc_daily_sync', 'psc_do_daily_sync');

/**
 * AJAX handler for revealing API token
 */
/**
 * AJAX handler for revealing API token
 */
function psc_ajax_reveal_token() {
    // Check nonce
    if (!check_ajax_referer('psc_reveal_token_nonce', 'nonce', false)) {
        wp_send_json_error(array('message' => esc_html__('Security check failed.', 'printify-shipping-class-helper')));
    }
    
    // Check user capabilities
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'printify-shipping-class-helper')));
    }
    
    // Get the token
    $settings = get_option('psc_settings', array());
    $encrypted_token = isset($settings['api_token']) ? $settings['api_token'] : '';
    
    // Create API client to get decrypted token
    $api_client = new PSC_API_Client();
    
    // Use the API client to get the decrypted token
    $token = '';
    if (function_exists('wp_decrypt_data') && strpos($encrypted_token, ':') !== false) {
        // WordPress 5.2+ encryption method
        try {
            $token = wp_decrypt_data($encrypted_token);
        } catch (Exception $e) {
            $token = '';
            // Log the error
            PSC_Logger::log('Failed to decrypt API token: ' . sanitize_text_field($e->getMessage()), 'error');
        }
    } else {
        // Fallback for non-encrypted tokens
        $token = $encrypted_token;
    }
    
    // Send back the token
    wp_send_json_success(array('token' => $token));
}
add_action('wp_ajax_psc_reveal_token', 'psc_ajax_reveal_token');
