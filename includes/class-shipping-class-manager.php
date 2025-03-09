<?php
/**
 * Shipping Class Manager
 *
 * Handles creation and management of WooCommerce shipping classes.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class PSC_Shipping_Class_Manager {
    /**
     * Get all existing shipping classes
     *
     * @return array Array of shipping classes with slug as key
     */
    public function get_shipping_classes() {
        $shipping_classes = array();
        $term_args = array(
            'taxonomy'   => 'product_shipping_class',
            'hide_empty' => false,
        );

        $terms = get_terms($term_args);

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $shipping_classes[$term->slug] = array(
                    'id'          => $term->term_id,
                    'name'        => $term->name,
                    'slug'        => $term->slug,
                    'description' => $term->description,
                );
            }
        }

        return $shipping_classes;
    }

    /**
     * Create a new shipping class
     *
     * @param string $name Shipping class name
     * @param string $description Shipping class description
     * @param string $slug Optional. Shipping class slug. Will be generated from name if not provided.
     * @return int|WP_Error Term ID on success, WP_Error on failure
     */
    public function create_shipping_class($name, $description = '', $slug = '') {
        $args = array(
            'description' => $description,
            'slug'        => $slug,
        );

        $result = wp_insert_term($name, 'product_shipping_class', $args);

        if (is_wp_error($result)) {
            PSC_Logger::log('Failed to create shipping class: ' . sanitize_text_field($result->get_error_message()));
            return $result;
        }

        PSC_Logger::log('Created shipping class: ' . $name);
        return $result['term_id'];
    }

    /**
     * Update an existing shipping class
     *
     * @param int $term_id Term ID
     * @param string $name Shipping class name
     * @param string $description Shipping class description
     * @param string $slug Optional. Shipping class slug.
     * @return array|WP_Error Term array on success, WP_Error on failure
     */
    public function update_shipping_class($term_id, $name, $description = '', $slug = '') {
        $args = array(
            'name'        => $name,
            'description' => $description,
        );

        if (!empty($slug)) {
            $args['slug'] = $slug;
        }

        $result = wp_update_term($term_id, 'product_shipping_class', $args);

        if (is_wp_error($result)) {
            PSC_Logger::log('Failed to update shipping class: ' . sanitize_text_field($result->get_error_message()));
            return $result;
        }

        PSC_Logger::log('Updated shipping class: ' . $name);
        return $result;
    }

    /**
     * Delete a shipping class
     *
     * @param int $term_id Term ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete_shipping_class($term_id) {
        $result = wp_delete_term($term_id, 'product_shipping_class');

        if (is_wp_error($result)) {
            PSC_Logger::log('Failed to delete shipping class: ' . sanitize_text_field($result->get_error_message()));;
            return $result;
        }

        PSC_Logger::log('Deleted shipping class with ID: ' . $term_id);
        return true;
    }

    /**
     * Get or create shipping class
     * 
     * This method will get an existing shipping class or create a new one if it doesn't exist
     *
     * @param string $name Shipping class name
     * @param string $description Shipping class description
     * @param string $slug Optional. Shipping class slug. Will be generated from name if not provided.
     * @return array Shipping class data
     */
    public function get_or_create_shipping_class($name, $description = '', $slug = '') {
        // Generate slug if not provided
        if (empty($slug)) {
            $slug = sanitize_title($name);
        }

        // Get existing shipping classes
        $shipping_classes = $this->get_shipping_classes();

        // Check if shipping class exists
        if (isset($shipping_classes[$slug])) {
            // Update if description has changed
            if ($shipping_classes[$slug]['description'] !== $description || 
                $shipping_classes[$slug]['name'] !== $name) {
                $this->update_shipping_class(
                    $shipping_classes[$slug]['id'],
                    $name,
                    $description,
                    $slug
                );
                
                // Get updated class
                $shipping_classes = $this->get_shipping_classes();
            }
            
            return $shipping_classes[$slug];
        }

        // Create new shipping class
        $term_id = $this->create_shipping_class($name, $description, $slug);
        
        if (is_wp_error($term_id)) {
            // Try with a different slug if there was an error
            $slug = sanitize_title($name . '-' . uniqid());
            $term_id = $this->create_shipping_class($name, $description, $slug);
            
            if (is_wp_error($term_id)) {
                return array(
                    'id'          => 0,
                    'name'        => $name,
                    'slug'        => $slug,
                    'description' => $description,
                    'error'       => $term_id->get_error_message()
                );
            }
        }
        
        // Get updated shipping classes
        $shipping_classes = $this->get_shipping_classes();
        
        return isset($shipping_classes[$slug]) ? $shipping_classes[$slug] : array(
            'id'          => $term_id,
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description
        );
    }

    /**
     * Generate shipping class name from provider and product
     *
     * @param array $product Printify product data
     * @param array $provider Printify provider data
     * @return string Shipping class name
     */
    public function generate_shipping_class_name($product, $provider) {
        // Get first 6 words from product title
        $title = $product['title'];
        $words = explode(' ', $title);
        $title_words = array_slice($words, 0, 6);
        $title_part = implode(' ', $title_words);

        // Generate name: "[Provider] - [First 6 words of product title]"
        return sprintf('%s - %s', $provider['title'], $title_part);
    }

    /**
     * Generate shipping class description from provider and product
     *
     * @param array $product Printify product data
     * @param array $provider Printify provider data
     * @return string Shipping class description
     */
    public function generate_shipping_class_description($product, $provider) {
        return sprintf(
            /* translators: 1: Printify product id, 2: Printify provider id */
            __('Shipping class for %1$s printed by %2$s. Product ID: %1$s, Provider ID: %2$s', 'printify-shipping-class-helper'),
            sanitize_text_field($product['title']),
            sanitize_text_field($provider['title']),
            sanitize_text_field($product['id']),
            absint($provider['id'])
        );
    }

    /**
     * Generate shipping class slug from product and provider IDs
     *
     * @param array $product Printify product data
     * @param array $provider Printify provider data
     * @return string Shipping class slug
     */
    public function generate_shipping_class_slug($product, $provider) {
        return 'printify-' . $provider['id'] . '-' . sanitize_text_field(substr($product['id'], 0, 10));
    }
}
