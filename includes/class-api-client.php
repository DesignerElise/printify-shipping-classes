<?php
/**
 * Printify API Client
 *
 * Handles communication with the Printify API using WordPress encryption.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class PSC_API_Client {
    /**
     * API base URL
     */
    private $api_base_url = 'https://api.printify.com/v1/';

    /**
     * API token
     */
    private $api_token;

    /**
     * Cache expiration time in seconds (default: 1 hour)
     */
    private $cache_expiration = 3600;

    /**
     * Constructor
     */
    public function __construct() {
        $settings = get_option('psc_settings', array());
        $this->api_token = isset($settings['api_token']) ? $this->get_api_token() : '';
        $this->cache_expiration = isset($settings['cache_expiration']) ? (int) $settings['cache_expiration'] : 3600;
    }

    /**
     * Get shops from Printify
     *
     * @return array|WP_Error Array of shops or WP_Error on failure
     */
    public function get_shops() {
        $cache_key = 'psc_printify_shops';
        $cached_data = get_transient($cache_key);

        if (false !== $cached_data) {
            return $cached_data;
        }

        $response = $this->make_request('shops.json');

        if (is_wp_error($response)) {
            return $response;
        }

        set_transient($cache_key, $response, $this->cache_expiration);

        return $response;
    }

    /**
     * Get products from a specific shop with pagination support
     *
     * @param int $shop_id Shop ID
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array|WP_Error Array of products or WP_Error on failure
     */
    public function get_products($shop_id, $page = 1, $limit = 50) {
        $cache_key = 'psc_printify_products_' . $shop_id . '_' . $page . '_' . $limit;
        $cached_data = get_transient($cache_key);

        if (false !== $cached_data) {
            return $cached_data;
        }

        $response = $this->make_request("shops/{$shop_id}/products.json?page={$page}&limit={$limit}");

        if (is_wp_error($response)) {
            return $response;
        }

        set_transient($cache_key, $response, $this->cache_expiration);

        return $response;
    }

    /**
     * Get all products from a shop, handling pagination automatically
     *
     * @param int $shop_id Shop ID
     * @return array|WP_Error Array of all products or WP_Error on failure
     */
    public function get_all_products($shop_id) {
        $all_products = array();
        $page = 1;
        $limit = 50;
        $total_pages = 1;

        do {
            $response = $this->get_products($shop_id, $page, $limit);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            if (isset($response['data']) && is_array($response['data'])) {
                $all_products = array_merge($all_products, $response['data']);
            }
            
            // Update total pages if available in response
            if (isset($response['last_page'])) {
                $total_pages = intval($response['last_page']);
            }
            
            $page++;
        } while ($page <= $total_pages);
        
        return array('data' => $all_products);
    }

    /**
     * Get print providers from Printify catalog
     *
     * @return array|WP_Error Array of print providers or WP_Error on failure
     */
    public function get_print_providers() {
        $cache_key = 'psc_printify_print_providers';
        $cached_data = get_transient($cache_key);

        if (false !== $cached_data) {
            return $cached_data;
        }

        $response = $this->make_request('catalog/print_providers.json');

        if (is_wp_error($response)) {
            return $response;
        }

        set_transient($cache_key, $response, $this->cache_expiration);

        return $response;
    }

    /**
     * Enhanced make_request method with better error handling
     *
     * @param string $endpoint API endpoint
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param array $data Request data for POST/PUT requests
     * @return array|WP_Error Response data or WP_Error on failure
     */
    private function make_request($endpoint, $method = 'GET', $data = array()) {
        if (empty($this->api_token)) {
            return new WP_Error(
                'invalid_api_token', 
                esc_html__('Printify API token is not set.', 'printify-shipping-class-helper'),
                array('status' => 401)
            );
        }

        $url = esc_url_raw($this->api_base_url . $endpoint);

        $args = array(
            'method'    => $method,
            'timeout'   => 30,
            'headers'   => array(
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'Printify Shipping Classes Generator/' . PSC_VERSION,
                'Accept'        => 'application/json',
            ),
            'sslverify' => true, // Always verify SSL for production
        );

        if ('POST' === $method || 'PUT' === $method) {
            $args['body'] = json_encode($data);
        }

        // Log request (without sensitive data)
        PSC_Logger::log(sprintf(
            'API Request: %s %s',
            $method,
            $url
        ), 'info');

        // Perform the request
        $response = wp_remote_request($url, $args);

        // Handle connection errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $error_data = $response->get_error_data();
            
            PSC_Logger::log('API connection error: ' . sanitize_text_field($error_message) . ' (Code: ' . 
                (is_array($error_data) ? json_encode($error_data) : ($error_data ? sanitize_text_field($error_data) : 'unknown')) . 
            ')', 'error');
            
            return new WP_Error(
                'api_connection_error',
                /* translators: connection error value */
                sprintf(esc_html__('Failed to connect to Printify API: %s', 'printify-shipping-class-helper'), $error_message),
                $error_data
            );
        }

        // Get response data
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Log response code
        PSC_Logger::log(sprintf(
            'API Response: %s %s (Status: %d)',
            $method,
            $url,
            absint($response_code)
        ), 'info');

        // Try to decode JSON response
        $response_data = json_decode($response_body, true);
        if (JSON_ERROR_NONE !== json_last_error() && !empty($response_body)) {
            PSC_Logger::log(sprintf(
                'API JSON decode error: %s, Raw response: %s',
                sanitize_text_field(json_last_error_msg()),
                sanitize_text_field(substr($response_body, 0, 255)) // Log only first 255 chars of response
            ), 'error');
            
            return new WP_Error(
                'api_invalid_json',
                esc_html__('Invalid JSON response from API', 'printify-shipping-class-helper'),
                array('status' => $response_code, 'body' => $response_body)
            );
        }

        // Handle API errors
        if ($response_code < 200 || $response_code >= 300) {
            $error_message = isset($response_data['errors'][0]['message']) 
                ? $response_data['errors'][0]['message'] 
                : esc_html__('Unknown API error', 'printify-shipping-class-helper');
            
            $error_code = isset($response_data['errors'][0]['code']) 
                ? $response_data['errors'][0]['code'] 
                : 'unknown_error';
            
            PSC_Logger::log(sprintf(
                'API error: %s (Code: %s, HTTP: %d)',
                sanitize_text_field($error_message),
                sanitize_text_field($error_code),
                absint($response_code)
            ), 'error');
            
            return new WP_Error(
                'api_error_' . $error_code,
                $error_message,
                array('status' => $response_code, 'response' => $response_data)
            );
        }

        return $response_data;
    }

    /**
     * Get API token
     *
     * @return string API token
     */
    private function get_api_token() {
        if (function_exists('wp_decrypt_data')) {
            // WordPress 5.2+ method
            $settings = get_option('psc_settings', array());
            $encrypted_token = isset($settings['api_token']) ? $settings['api_token'] : '';
            
            if (!empty($encrypted_token)) {
                // Check if the token is already encrypted
                if (strpos($encrypted_token, ':') !== false) {
                    return wp_decrypt_data($encrypted_token);
                }
                
                // Return token as-is if not encrypted (during transition)
                return $encrypted_token;
            }
        } else {
            // Fallback for WordPress versions before 5.2
            $settings = get_option('psc_settings', array());
            return isset($settings['api_token']) ? $settings['api_token'] : '';
        }
        
        return '';
    }

    /**
     * Save API token
     *
     * @param string $token API token
     */
    public static function save_api_token($token) {
        if (empty($token)) {
            return;
        }
        
        $settings = get_option('psc_settings', array());
        
        if (function_exists('wp_encrypt_data')) {
            // WordPress 5.2+ method
            $settings['api_token'] = wp_encrypt_data(sanitize_text_field($token));
        } else {
            // Fallback for WordPress versions before 5.2
            $settings['api_token'] = sanitize_text_field($token);
        }
        
        update_option('psc_settings', $settings);
    }

    /**
     * Clear API cache
     */
    public function clear_cache() {
        // Delete main transients
        delete_transient('psc_printify_shops');
        delete_transient('psc_printify_print_providers');
        
        // Clear product caches for all shops
        $shops = $this->get_shops();
        if (!is_wp_error($shops)) {
            foreach ($shops as $shop) {
                $shop_id = isset($shop['id']) ? absint($shop['id']) : 0;
                if ($shop_id > 0) {
                    // Get the transient option names matching the pattern
                    $transient_like = '_transient_psc_printify_products_' . $shop_id . '_%';
                    
                    // Use the WordPress option API to get matching options
                    $transients = $this->get_transients_by_pattern($transient_like);
                    
                    // Delete each transient individually using WordPress functions
                    if (!empty($transients)) {
                        foreach ($transients as $transient) {
                            $transient_name = str_replace('_transient_', '', $transient);
                            delete_transient($transient_name);
                        }
                    }
                }
            }
        }
    }

    /**
     * Get transient names by pattern
     *
     * This is a replacement for direct database access that uses WordPress API
     * and includes caching to avoid performance issues.
     *
     * @param string $pattern The LIKE pattern to search for
     * @return array Array of transient option names
     */
    private function get_transients_by_pattern($pattern) {
        // Check the cache first
        $cache_key = 'psc_transients_' . md5($pattern);
        $cached_results = wp_cache_get($cache_key, 'psc');
        
        if (false !== $cached_results) {
            return $cached_results;
        }
        
        // Use get_option with filter to get all options
        // This is a WordPress 5.3+ feature, but we'll use it if available
        if (function_exists('wp_list_filter') && version_compare(get_bloginfo('version'), '5.3', '>=')) {
            $all_options = wp_load_alloptions();
            
            // Extract the pattern for matching
            $search_pattern = str_replace('_transient_', '', $pattern);
            $search_pattern = str_replace('%', '', $search_pattern);
            
            // Find matching options
            $matching_options = array();
            foreach ($all_options as $option_name => $value) {
                if (strpos($option_name, '_transient_') === 0 && strpos($option_name, $search_pattern) !== false) {
                    $matching_options[] = $option_name;
                }
            }
            
            // Cache the results
            wp_cache_set($cache_key, $matching_options, 'psc', HOUR_IN_SECONDS);
            
            return $matching_options;
        } else {
            // Fallback implementation for older WordPress versions
            global $wpdb;

            // Ensure pattern includes wildcards and is properly escaped
            $pattern = '%' . $wpdb->esc_like($pattern) . '%';
            $cache_key = 'psc_' . md5($pattern);
            
            $results = wp_cache_get($cache_key, 'psc');
            if (false === $results) {
                $query = $wpdb->prepare(
                    "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s",
                    $pattern
                );

                $results = $wpdb->get_col($query);
                wp_cache_set($cache_key, $results, 'psc', HOUR_IN_SECONDS);
            }

            return $results;
        }
    }
}