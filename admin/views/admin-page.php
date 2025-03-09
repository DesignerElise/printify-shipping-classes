<?php
/**
 * Admin page view
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
$nonce = wp_create_nonce('psc_admin_nonce');
?>
<div class="wrap">
    <h1><?php echo esc_html_e('Printify Shipping Classes Generator', 'printify-shipping-class-helper'); ?></h1>
    
    <?php if (!$api_token_set): ?>
        <div class="notice notice-warning">
            <p>
                <?php echo esc_html_e('Please configure your Printify API token to start using the plugin.', 'printify-shipping-class-helper'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=printify-shipping-class&tab=settings')); ?>"><?php echo esc_html_e('Configure now', 'printify-shipping-class-helper'); ?></a>
            </p>
        </div>
    <?php endif; ?>
    
    <?php
    // Verify nonce if tab is set in GET request
    $valid_nonce = isset($_REQUEST['_wpnonce']) ? wp_verify_nonce(sanitize_key($_REQUEST['_wpnonce']), 'psc_admin_nonce') : false;

    // Get current tab with nonce verification
    $default_tab = 'dashboard';
        if (isset($_GET['tab']) && $valid_nonce) {
            $current_tab = sanitize_text_field(wp_unslash($_GET['tab']));
        } else {
            $current_tab = $default_tab;
        }

    // Define tabs
    $tabs = array(
        'dashboard' => __('Dashboard', 'printify-shipping-class-helper'),
        'settings' => __('Settings', 'printify-shipping-class-helper'),
        'logs' => __('Logs', 'printify-shipping-class-helper'),
    );
    ?>
    
    <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
        <?php foreach ($tabs as $tab_id => $tab_name): ?>
            <a href="<?php echo esc_url(add_query_arg(array('page' => 'printify-shipping-classes', 'tab' => $tab_id, '_wpnonce' => $nonce), admin_url('admin.php'))); ?>" class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($tab_name); ?></a>
        <?php endforeach; ?>
    </nav>
    
    <div class="tab-content">
        <?php if ($current_tab === 'dashboard'): ?>
            <div class="dashboard-content">
                <div class="postbox">
                    <div class="inside">
                        <h2><?php echo esc_html_e('Synchronize Shipping Classes', 'printify-shipping-class-helper'); ?></h2>
                        <p><?php echo esc_html_e('Click the button below to synchronize shipping classes between Printify and WooCommerce.', 'printify-shipping-class-helper'); ?></p>
                        
                        <?php if ($api_token_set): ?>
                            <button id="psc-sync-button" class="button button-primary">
                                <?php echo esc_html_e('Sync Shipping Classes', 'printify-shipping-class-helper'); ?>
                            </button>
                            <span id="psc-sync-spinner" class="spinner" style="float: none; margin-top: 0;"></span>
                            <div id="psc-sync-result" style="margin-top: 10px;"></div>
                        <?php else: ?>
                            <button class="button button-primary" disabled>
                                <?php echo esc_html_e('Sync Shipping Classes', 'printify-shipping-class-helper'); ?>
                            </button>
                            <p class="description"><?php echo esc_html_e('Please configure your Printify API token first.', 'printify-shipping-class-helper'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="postbox">
                    <div class="inside">
                        <h2><?php echo esc_html_e('Shipping Classes', 'printify-shipping-class-helper'); ?></h2>
                        
                        <?php if (empty($shipping_classes)): ?>
                            <p><?php echo esc_html_e('No shipping classes found.', 'printify-shipping-class-helper'); ?></p>
                        <?php else: ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html_e('ID', 'printify-shipping-class-helper'); ?></th>
                                        <th><?php echo esc_html_e('Name', 'printify-shipping-class-helper'); ?></th>
                                        <th><?php echo esc_html_e('Slug', 'printify-shipping-class-helper'); ?></th>
                                        <th><?php echo esc_html_e('Description', 'printify-shipping-class-helper'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($shipping_classes as $shipping_class): ?>
                                        <tr>
                                            <td><?php echo absint($shipping_class['id']); ?></td>
                                            <td><?php echo esc_html($shipping_class['name']); ?></td>
                                            <td><?php echo esc_html($shipping_class['slug']); ?></td>
                                            <td><?php echo wp_kses_post($shipping_class['description']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php elseif ($current_tab === 'settings'): ?>
            <div class="settings-content">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('psc_settings');
                    do_settings_sections('printify-shipping-classes');
                    submit_button();
                    ?>
                </form>
            </div>
        <?php elseif ($current_tab === 'logs'): ?>
            <div class="logs-content">
                <div class="postbox">
                    <div class="inside">
                        <h2><?php echo esc_html_e('Logs', 'printify-shipping-class-helper'); ?></h2>
                        
                        <?php if (empty($logs)): ?>
                            <p><?php echo esc_html_e('No logs found.', 'printify-shipping-class-helper'); ?></p>
                        <?php else: ?>
                            <div class="psc-logs-wrapper" style="max-height: 500px; overflow-y: auto; margin-bottom: 20px;">
                            <pre style="white-space: pre-wrap; background: #f5f5f5; padding: 10px;"><?php 
                                foreach ($logs as $index => $log) {
                                    echo esc_html($log) . ($index < count($logs) - 1 ? "\n" : '');
                                }
                            ?></pre>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#psc-sync-button').on('click', function() {
        var button = $(this);
        var spinner = $('#psc-sync-spinner');
        var resultDiv = $('#psc-sync-result');
        
        // Disable button and show spinner
        button.prop('disabled', true);
        spinner.addClass('is-active');
        resultDiv.html('');
        
        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'psc_run_sync',
                nonce: '<?php echo esc_js(wp_create_nonce('psc_ajax_nonce')); ?>'
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                    
                    if (response.data.errors && response.data.errors.length > 0) {
                        var errorHtml = '<div class="notice notice-warning inline"><p>' + <?php echo wp_json_encode(esc_html__('Warnings:', 'printify-shipping-class-helper')); ?> + '</p><ul>';
                        $.each(response.data.errors, function(index, error) {
                            errorHtml += '<li>' + error + '</li>';
                        });
                        errorHtml += '</ul></div>';
                        resultDiv.append(errorHtml);
                    }
                    
                    // Reload page after 2 seconds to refresh the shipping classes list
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    resultDiv.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                    
                    if (response.data.errors && response.data.errors.length > 0) {
                        var errorHtml = '<ul>';
                        $.each(response.data.errors, function(index, error) {
                            errorHtml += '<li>' + error + '</li>';
                        });
                        errorHtml += '</ul>';
                        resultDiv.find('.notice').append(errorHtml);
                    }
                }
            },
            error: function() {
                resultDiv.html('<div class="notice notice-error inline"><p>' + <?php echo json_encode(__('An error occurred while processing the request.', 'printify-shipping-class-helper')); ?> + '</p></div>');
            },
            complete: function() {
                // Enable button and hide spinner
                button.prop('disabled', false);
                spinner.removeClass('is-active');
            }
        });
    });
});
</script>
