<?php
/**
 * Settings
 *
 * Handles the plugin's settings with API key obfuscation.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class PSC_Settings {
    /**
     * Constructor
     */
    public function __construct() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'psc_settings',
            'psc_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => array()
            )
        );

        // API settings section
        add_settings_section(
            'psc_api_settings',
            __('Printify API Settings', 'printify-shipping-class-helper'),
            array($this, 'render_api_settings_section'),
            'printify-shipping-classes'
        );

        // API token field
        add_settings_field(
            'psc_api_token',
            __('API Token', 'printify-shipping-class-helper'),
            array($this, 'render_api_token_field'),
            'printify-shipping-classes',
            'psc_api_settings'
        );

        // Shop ID field
        add_settings_field(
            'psc_shop_id',
            __('Shop ID', 'printify-shipping-class-helper'),
            array($this, 'render_shop_id_field'),
            'printify-shipping-classes',
            'psc_api_settings'
        );

        // Sync settings section
        add_settings_section(
            'psc_sync_settings',
            __('Synchronization Settings', 'printify-shipping-class-helper'),
            array($this, 'render_sync_settings_section'),
            'printify-shipping-classes'
        );

        // Auto sync field
        add_settings_field(
            'psc_auto_sync',
            __('Auto Sync', 'printify-shipping-class-helper'),
            array($this, 'render_auto_sync_field'),
            'printify-shipping-classes',
            'psc_sync_settings'
        );

        // Cache expiration field
        add_settings_field(
            'psc_cache_expiration',
            __('Cache Expiration', 'printify-shipping-class-helper'),
            array($this, 'render_cache_expiration_field'),
            'printify-shipping-classes',
            'psc_sync_settings'
        );

        // Logging settings section
        add_settings_section(
            'psc_logging_settings',
            __('Logging Settings', 'printify-shipping-class-helper'),
            array($this, 'render_logging_settings_section'),
            'printify-shipping-classes'
        );

        // Enable logging field
        add_settings_field(
            'psc_enable_logging',
            __('Enable Logging', 'printify-shipping-class-helper'),
            array($this, 'render_enable_logging_field'),
            'printify-shipping-classes',
            'psc_logging_settings'
        );
    }

    /**
     * Sanitize settings
     *
     * @param array $input Settings input
     * @return array Sanitized settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        // API token - preserve existing token if not changed
        if (isset($input['api_token'])) {
            $current_settings = get_option('psc_settings', array());
            $current_token = isset($current_settings['api_token']) ? $current_settings['api_token'] : '';
            
            // If the token field contains the masked value, keep the original token
            if ($input['api_token'] === $this->get_masked_token($current_token)) {
                $sanitized['api_token'] = $current_token;
            } else {
                $sanitized['api_token'] = sanitize_text_field($input['api_token']);
            }
        }

        // Shop ID
        if (isset($input['shop_id'])) {
            $sanitized['shop_id'] = intval($input['shop_id']);
        }

        // Auto sync
        if (isset($input['auto_sync'])) {
            $sanitized['auto_sync'] = $input['auto_sync'] === 'yes' ? 'yes' : 'no';
        } else {
            $sanitized['auto_sync'] = 'no';
        }

        // Cache expiration
        if (isset($input['cache_expiration'])) {
            $sanitized['cache_expiration'] = absint($input['cache_expiration']);
        } else {
            $sanitized['cache_expiration'] = 3600; // 1 hour
        }

        // Enable logging
        if (isset($input['enable_logging'])) {
            $sanitized['enable_logging'] = $input['enable_logging'] === 'yes' ? 'yes' : 'no';
        } else {
            $sanitized['enable_logging'] = 'no';
        }

        return $sanitized;
    }

    /**
     * Generate a masked version of the token for display
     * 
     * @param string $token The API token
     * @return string Masked token
     */
    private function get_masked_token($token) {
        if (empty($token)) {
            return '';
        }
        
        // Show first 4 and last 4 characters, mask the rest with ●
        $length = strlen($token);
        if ($length <= 8) {
            return str_repeat('●', $length);
        }
        
        return substr($token, 0, 4) . str_repeat('●', $length - 8) . substr($token, -4);
    }

    /**
     * Render API settings section
     */
    public function render_api_settings_section() {
        echo '<p>' . esc_html__('Configure your Printify API settings.', 'printify-shipping-class-helper') . '</p>';
        echo '<p>' . sprintf(
            /* translators: 1: connection, 2: link */
            esc_html__('You can generate an API token in your Printify account under %1$sConnections%2$s.', 'printify-shipping-class-helper'),
            ('<a href="https://printify.com/app/account/connections" target="_blank">'),
            '</a>'
        ) . '</p>';
    }

    /**
     * Render API token field
     */
    public function render_api_token_field() {
        $settings = get_option('psc_settings', array());
        $api_token = isset($settings['api_token']) ? $settings['api_token'] : '';
        $masked_token = $this->get_masked_token($api_token);
        $placeholder = empty($api_token) ? __('Enter your Printify API token', 'printify-shipping-class-helper') : '';

        echo '<input type="text" name="psc_settings[api_token]" id="psc_api_token" class="regular-text" value="' . esc_attr($masked_token) . '" placeholder="' . esc_attr($placeholder) . '" autocomplete="off" />';
        
        if (!empty($api_token)) {
            echo '<button type="button" id="psc-toggle-token" class="button button-secondary">' . esc_html__('Show/Hide Token', 'printify-shipping-class-helper') . '</button>';
            echo '<button type="button" id="psc-clear-token" class="button button-secondary">' . esc_html__('Change Token', 'printify-shipping-class-helper') . '</button>';
        }
        
        echo '<p class="description">' . esc_html__('Your Printify API token. This is stored securely and never displayed in full.', 'printify-shipping-class-helper') . '</p>';
        
        // Add JavaScript to handle token field interaction
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var originalValue = $('#psc_api_token').val();
            var isShowingMasked = true;
            
            $('#psc-toggle-token').on('click', function() {
                var tokenField = $('#psc_api_token');
                
                if (isShowingMasked) {
                    // Show the actual token
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'psc_reveal_token',
                            nonce: '<?php echo esc_js(wp_create_nonce('psc_reveal_token_nonce')); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                tokenField.val(response.data.token);
                                isShowingMasked = false;
                            }
                        }
                    });
                } else {
                    // Show the masked version again
                    tokenField.val(originalValue);
                    isShowingMasked = true;
                }
            });
            
            $('#psc-clear-token').on('click', function() {
                $('#psc_api_token').val('').focus();
            });
        });
        </script>
        <?php
    }

    /**
     * Render shop ID field
     */
    public function render_shop_id_field() {
        $settings = get_option('psc_settings', array());
        $shop_id = isset($settings['shop_id']) ? intval($settings['shop_id']) : 0;

        // Get API client
        $api_client = new PSC_API_Client();
        
        // Get shops
        $shops = array();
        if (!empty($settings['api_token'])) {
            $shops_data = $api_client->get_shops();
            if (!is_wp_error($shops_data)) {
                $shops = $shops_data;
            }
        }

        if (!empty($shops)) {
            echo '<select name="psc_settings[shop_id]" id="psc_shop_id">';
            echo '<option value="0">' . esc_html__('Select a shop', 'printify-shipping-class-helper') . '</option>';
            
            foreach ($shops as $shop) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($shop['id']),
                    selected($shop_id, $shop['id'], false),
                    esc_html($shop['title'])
                );
            }
            
            echo '</select>';
        } else {
            echo '<input type="text" name="psc_settings[shop_id]" id="psc_shop_id" class="regular-text" value="' . esc_attr($shop_id) . '" placeholder="' . esc_attr__('Enter your Printify shop ID', 'printify-shipping-class-helper') . '" />';
        }
        
        echo '<p class="description">' . esc_html__('Your Printify shop ID. If you\'ve entered a valid API token above, your shops will appear in this dropdown.', 'printify-shipping-class-helper') . '</p>';
    }

    /**
     * Render sync settings section
     */
    public function render_sync_settings_section() {
        echo '<p>' . esc_html__('Configure synchronization settings.', 'printify-shipping-class-helper') . '</p>';
    }

    /**
     * Render auto sync field
     */
    public function render_auto_sync_field() {
        $settings = get_option('psc_settings', array());
        $auto_sync = isset($settings['auto_sync']) ? $settings['auto_sync'] : 'no';

        echo '<label for="psc_auto_sync">';
        echo '<input type="checkbox" name="psc_settings[auto_sync]" id="psc_auto_sync" value="yes" ' . checked('yes', $auto_sync, false) . ' />';
        echo esc_html__('Enable automatic synchronization', 'printify-shipping-class-helper');
        echo '</label>';
        echo '<p class="description">' . esc_html__('If enabled, the plugin will automatically sync shipping classes once a day.', 'printify-shipping-class-helper') . '</p>';
    }

    /**
     * Render cache expiration field
     */
    public function render_cache_expiration_field() {
        $settings = get_option('psc_settings', array());
        $cache_expiration = isset($settings['cache_expiration']) ? intval($settings['cache_expiration']) : 3600;

        echo '<input type="number" name="psc_settings[cache_expiration]" id="psc_cache_expiration" class="small-text" value="' . esc_attr($cache_expiration) . '" min="0" step="60" />';
        echo '<p class="description">' . esc_html__('Cache expiration time in seconds. Default: 3600 (1 hour).', 'printify-shipping-class-helper') . '</p>';
    }

    /**
     * Render logging settings section
     */
    public function render_logging_settings_section() {
        echo '<p>' . esc_html__('Configure logging settings.', 'printify-shipping-class-helper') . '</p>';
    }

    /**
     * Render enable logging field
     */
    public function render_enable_logging_field() {
        $settings = get_option('psc_settings', array());
        $enable_logging = isset($settings['enable_logging']) ? $settings['enable_logging'] : 'yes';

        echo '<label for="psc_enable_logging">';
        echo '<input type="checkbox" name="psc_settings[enable_logging]" id="psc_enable_logging" value="yes" ' . checked('yes', $enable_logging, false) . ' />';
        echo esc_html__('Enable logging', 'printify-shipping-class-helper');
        echo '</label>';
        echo '<p class="description">' . esc_html__('If enabled, the plugin will log events for debugging purposes.', 'printify-shipping-class-helper') . '</p>';
    }
}
