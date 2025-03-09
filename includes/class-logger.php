<?php
/**
 * Logger
 *
 * Handles logging for the plugin.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class PSC_Logger {
    /**
     * Log a message
     *
     * @param string $message Message to log
     * @param string $level Log level (info, warning, error)
     */
    public static function log($message, $level = 'info') {
        // Get settings
        $settings = get_option('psc_settings', array());
        $logging_enabled = isset($settings['enable_logging']) ? $settings['enable_logging'] === 'yes' : true;

        if (!$logging_enabled) {
            return;
        }

        // Format message
        $timestamp = current_time('Y-m-d H:i:s');
        $formatted_message = sprintf('[%s] [%s] %s', $timestamp, strtoupper($level), sanitize_text_field($message));

        // Get log file path
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/psc-logs';
        
        // Create log directory if it doesn't exist

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            
            // Create .htaccess file to protect logs
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents($log_dir . '/.htaccess', $htaccess_content);
        }


        if (!file_exists($log_dir)) {
            $dir_created = wp_mkdir_p($log_dir);
            
            if ($dir_created) {
                // Create .htaccess file to protect logs
                $htaccess_content = "Order deny,allow\nDeny from all";
                $htaccess_file = $log_dir . '/.htaccess';
                $file_written = @file_put_contents($htaccess_file, $htaccess_content);
                
                if (!$file_written) {
                    // Log the failure but continue
                    if (function_exists('wc_get_logger')) {
                        $logger = wc_get_logger();
                        $logger->error('Failed to create log directory .htaccess file', array('source' => 'printify-shipping-classes'));
                    }
                }
            } else {
                // Log directory creation failure
                if (function_exists('wc_get_logger')) {
                    $logger = wc_get_logger();
                    $logger->error('Failed to create log directory: ' . $log_dir, array('source' => 'printify-shipping-classes'));
                }
            }
        }
        
        $log_file = $log_dir . '/psc-' . current_time('Y-m-d') . '.log';
        
        // Write to log file
        file_put_contents($log_file, $formatted_message . PHP_EOL, FILE_APPEND);
        
        // Log to WC_Logger if WooCommerce is active
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $context = array('source' => 'printify-shipping-classes');
            
            switch ($level) {
                case 'error':
                    $logger->error($message, $context);
                    break;
                case 'warning':
                    $logger->warning($message, $context);
                    break;
                default:
                    $logger->info($message, $context);
                    break;
            }
        }
    }
    
    /**
     * Get logs for display in admin
     * 
     * @param int $limit Number of log entries to retrieve
     * @return array Array of log entries
     */
    public static function get_logs($limit = 100) {
        $logs = array();
        
        // Get log file path
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/psc-logs';
        $log_file = $log_dir . '/psc-' . current_time('Y-m-d') . '.log';
        
        if (file_exists($log_file)) {
            $file = new SplFileObject($log_file);
            $file->seek(PHP_INT_MAX); // Seek to end of file
            $total_lines = $file->key(); // Get total lines
            
            // Calculate starting line
            $start_line = max(0, $total_lines - $limit);
            
            // Reset pointer
            $file->rewind();
            
            // Skip to starting line
            for ($i = 0; $i < $start_line; $i++) {
                $file->current();
                $file->next();
            }
            
            // Read lines
            $count = 0;
            while (!$file->eof() && $count < $limit) {
                $line = trim($file->fgets());
                if (!empty($line)) {
                    $logs[] = $line;
                    $count++;
                }
            }
            
            // Reverse array to show newest logs first
            $logs = array_reverse($logs);
        }
        
        return $logs;
    }
}