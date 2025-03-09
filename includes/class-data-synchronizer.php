<?php
/**
 * Data Synchronizer
 *
 * Handles synchronization between Printify and WooCommerce shipping classes.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class PSC_Data_Synchronizer {
    /**
     * API Client
     *
     * @var PSC_API_Client
     */
    private $api_client;

    /**
     * Shipping Class Manager
     *
     * @var PSC_Shipping_Class_Manager
     */
    private $shipping_class_manager;

    /**
     * Current shop ID
     * 
     * @var int
     */
    private $current_shop_id;

    /**
     * Constructor
     *
     * @param PSC_API_Client $api_client
     * @param PSC_Shipping_Class_Manager $shipping_class_manager
     */
    public function __construct($api_client, $shipping_class_manager) {
        $this->api_client = $api_client;
        $this->shipping_class_manager = $shipping_class_manager;
        
        // Get settings
        $settings = get_option('psc_settings', array());
        $this->current_shop_id = isset($settings['shop_id']) ? intval($settings['shop_id']) : 0;
    }

    /**
     * Run synchronization process
     *
     * @return array Sync results
     */
    public function sync() {
        $result = array(
            'success' => false,
            'message' => '',
            'created' => 0,
            'updated' => 0,
            'errors' => array()
        );

        // Step 1: Get shop ID if not set
        if (empty($this->current_shop_id)) {
            $shops = $this->api_client->get_shops();
            
            if (is_wp_error($shops)) {
                $result['message'] = $shops->get_error_message();
                $result['errors'][] = $shops->get_error_message();
                PSC_Logger::log('Failed to get shops: ' . $shops->get_error_message());
                return $result;
            }
            
            if (empty($shops)) {
                $result['message'] = __('No shops found in Printify account.', 'printify-shipping-class-helper');
                $result['errors'][] = $result['message'];
                PSC_Logger::log('No shops found in Printify account.');
                return $result;
            }
            
            $this->current_shop_id = $shops[0]['id'];
            
            // Save shop ID to settings
            $settings = get_option('psc_settings', array());
            $settings['shop_id'] = $this->current_shop_id;
            update_option('psc_settings', $settings);
        }
        
        // Step 2: Get products from the shop
        $products = $this->api_client->get_products($this->current_shop_id);
        
        if (is_wp_error($products)) {
            $result['message'] = $products->get_error_message();
            $result['errors'][] = $products->get_error_message();
            PSC_Logger::log('Failed to get products: ' . $products->get_error_message());
            return $result;
        }
        
        if (empty($products['data'])) {
            $result['message'] = __('No products found in the Printify shop.', 'printify-shipping-class-helper');
            $result['errors'][] = $result['message'];
            PSC_Logger::log('No products found in the Printify shop.');
            return $result;
        }
        
        // Step 3: Get print providers
        $providers = $this->api_client->get_print_providers();
        
        if (is_wp_error($providers)) {
            $result['message'] = $providers->get_error_message();
            $result['errors'][] = $providers->get_error_message();
            PSC_Logger::log('Failed to get print providers: ' . $providers->get_error_message());
            return $result;
        }
        
        if (empty($providers)) {
            $result['message'] = __('No print providers found.', 'printify-shipping-class-helper');
            $result['errors'][] = $result['message'];
            PSC_Logger::log('No print providers found.');
            return $result;
        }
        
        // Create a lookup array for providers
        $provider_lookup = array();
        foreach ($providers as $provider) {
            $provider_lookup[$provider['id']] = $provider;
        }
        
        // Step 4: Create shipping classes for each product/provider combination
        foreach ($products['data'] as $product) {
            // Skip if provider ID is not set or not found
            if (!isset($product['print_provider_id']) || !isset($provider_lookup[$product['print_provider_id']])) {
                $result['errors'][] = sprintf(
                    /* translators: 1: printify product name, 2: printify product id */
                    esc_html__('Provider not found for product: %1$s (ID: %2$s)', 'printify-shipping-class-helper'),
                    $product['title'],
                    $product['id']
                );
                continue;
            }
            
            $provider = $provider_lookup[$product['print_provider_id']];
            
            // Generate shipping class data
            $name = $this->shipping_class_manager->generate_shipping_class_name($product, $provider);
            $description = $this->shipping_class_manager->generate_shipping_class_description($product, $provider);
            $slug = $this->shipping_class_manager->generate_shipping_class_slug($product, $provider);
            
            // Create or update shipping class
            $shipping_class = $this->shipping_class_manager->get_or_create_shipping_class($name, $description, $slug);
            
            if (isset($shipping_class['error'])) {
                $result['errors'][] = $shipping_class['error'];
            } else {
                if (isset($shipping_class['_added']) && $shipping_class['_added']) {
                    $result['created']++;
                } else {
                    $result['updated']++;
                }
            }
        }
        
        // Set result
        $result['success'] = true;
        $result['message'] = sprintf(
            /* translators: 1: shipping classes created, 2: shipping classes updated, 3: shipping class errors */
            __('Sync completed. Created: %1$d, Updated: %2$d, Errors: %3$d', 'printify-shipping-class-helper'),
            $result['created'],
            $result['updated'],
            count($result['errors'])
        );
        
        PSC_Logger::log($result['message']);
        
        return $result;
    }
}
