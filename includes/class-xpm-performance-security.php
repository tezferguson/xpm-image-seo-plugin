<?php
/**
 * XPM Image SEO Performance and Security Classes
 * 
 * @package XPM_Image_SEO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rate limiting for API requests
 */
class XPM_Rate_Limiter {
    
    private $max_requests_per_minute = 20;
    private $option_name = 'xpm_rate_limit_data';
    
    public function is_rate_limited() {
        $data = get_option($this->option_name, array());
        $current_time = time();
        $current_minute = floor($current_time / 60);
        
        // Clean old data (older than 2 minutes)
        $data = array_filter($data, function($timestamp) use ($current_time) {
            return ($current_time - $timestamp) < 120;
        });
        
        // Count requests in current minute
        $current_minute_requests = array_filter($data, function($timestamp) use ($current_minute) {
            return floor($timestamp / 60) === $current_minute;
        });
        
        if (count($current_minute_requests) >= $this->max_requests_per_minute) {
            return true;
        }
        
        // Add current request
        $data[] = $current_time;
        update_option($this->option_name, $data);
        
        return false;
    }
    
    public function get_remaining_requests() {
        $data = get_option($this->option_name, array());
        $current_time = time();
        $current_minute = floor($current_time / 60);
        
        $current_minute_requests = array_filter($data, function($timestamp) use ($current_minute) {
            return floor($timestamp / 60) === $current_minute;
        });
        
        return max(0, $this->max_requests_per_minute - count($current_minute_requests));
    }
}

/**
 * Enhanced security measures
 */
class XPM_Security {
    
    /**
     * Validate image file before processing
     */
    public static function validate_image_file($file_path) {
        // Check if file exists
        if (!file_exists($file_path)) {
            return false;
        }
        
        // Check file size (max 50MB)
        if (filesize($file_path) > 50 * 1024 * 1024) {
            return false;
        }
        
        // Verify it's actually an image
        $image_info = getimagesize($file_path);
        if (!$image_info) {
            return false;
        }
        
        // Check allowed MIME types
        $allowed_types = array(
            IMAGETYPE_JPEG,
            IMAGETYPE_PNG,
            IMAGETYPE_GIF,
            IMAGETYPE_WEBP
        );
        
        if (!in_array($image_info[2], $allowed_types)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Sanitize API key
     */
    public static function sanitize_api_key($api_key) {
        // Remove any whitespace
        $api_key = trim($api_key);
        
        // Basic format validation for OpenAI API keys
        if (!empty($api_key) && !preg_match('/^sk-[a-zA-Z0-9]{48}$/', $api_key)) {
            return false;
        }
        
        return $api_key;
    }
    
    /**
     * Validate user permissions for bulk operations
     */
    public static function can_perform_bulk_operation() {
        return current_user_can('upload_files') && current_user_can('edit_posts');
    }
    
    /**
     * Secure file path validation
     */
    public static function validate_file_path($path) {
        $upload_dir = wp_upload_dir();
        $real_path = realpath($path);
        $upload_path = realpath($upload_dir['basedir']);
        
        // Ensure file is within uploads directory
        return $real_path && $upload_path && strpos($real_path, $upload_path) === 0;
    }
}

/**
 * Performance optimization utilities
 */
class XPM_Performance {
    
    /**
     * Batch process images to prevent timeouts
     */
    public static function process_in_batches($items, $callback, $batch_size = 5) {
        $batches = array_chunk($items, $batch_size);
        $results = array();
        
        foreach ($batches as $batch) {
            foreach ($batch as $item) {
                $results[] = call_user_func($callback, $item);
            }
            
            // Prevent memory issues
            if (function_exists('wp_suspend_cache_addition')) {
                wp_suspend_cache_addition(true);
            }
            
            // Brief pause between batches
            sleep(1);
        }
        
        if (function_exists('wp_suspend_cache_addition')) {
            wp_suspend_cache_addition(false);
        }
        
        return $results;
    }
    
    /**
     * Memory usage monitoring
     */
    public static function get_memory_usage() {
        return array(
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit'),
            'available' => self::get_available_memory()
        );
    }
    
    private static function get_available_memory() {
        $limit = ini_get('memory_limit');
        if ($limit == -1) {
            return -1; // No limit
        }
        
        $limit_bytes = self::convert_to_bytes($limit);
        $current_usage = memory_get_usage(true);
        
        return $limit_bytes - $current_usage;
    }
    
    private static function convert_to_bytes($value) {
        $unit = strtolower(substr($value, -1));
        $value = (int) $value;
        
        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Check if we have enough memory for operation
     */
    public static function has_sufficient_memory($required_mb = 64) {
        $available = self::get_available_memory();
        
        if ($available === -1) {
            return true; // No limit
        }
        
        return $available > ($required_mb * 1024 * 1024);
    }
}

/**
 * Caching utilities
 */
class XPM_Cache {
    
    private static $cache_group = 'xpm_image_seo';
    
    /**
     * Cache image metadata to reduce database queries
     */
    public static function get_image_metadata($attachment_id) {
        $cache_key = 'image_meta_' . $attachment_id;
        $cached = wp_cache_get($cache_key, self::$cache_group);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $metadata = wp_get_attachment_metadata($attachment_id);
        wp_cache_set($cache_key, $metadata, self::$cache_group, 300); // Cache for 5 minutes
        
        return $metadata;
    }
    
    /**
     * Cache optimization statistics
     */
    public static function get_cached_stats() {
        $cache_key = 'optimization_stats';
        $cached = wp_cache_get($cache_key, self::$cache_group);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Get fresh stats
        $stats = XPM_Database::get_optimization_statistics();
        wp_cache_set($cache_key, $stats, self::$cache_group, 600); // Cache for 10 minutes
        
        return $stats;
    }
    
    /**
     * Clear cache when images are updated
     */
    public static function clear_image_cache($attachment_id) {
        $cache_key = 'image_meta_' . $attachment_id;
        wp_cache_delete($cache_key, self::$cache_group);
        
        // Also clear related caches
        wp_cache_delete('optimization_stats', self::$cache_group);
    }
}

/**
 * Error handling and logging
 */
class XPM_Error_Handler {
    
    private static $log_file = null;
    
    public static function init() {
        $upload_dir = wp_upload_dir();
        self::$log_file = $upload_dir['basedir'] . '/xpm-errors.log';
    }
    
    public static function log_error($message, $context = array()) {
        if (!self::$log_file) {
            self::init();
        }
        
        $timestamp = current_time('Y-m-d H:i:s');
        $context_str = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $log_entry = "[{$timestamp}] {$message}{$context_str}" . PHP_EOL;
        
        // Append to log file
        file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // Keep log file size manageable (max 5MB)
        if (file_exists(self::$log_file) && filesize(self::$log_file) > 5 * 1024 * 1024) {
            self::rotate_log_file();
        }
    }
    
    private static function rotate_log_file() {
        $backup_file = self::$log_file . '.old';
        
        if (file_exists($backup_file)) {
            unlink($backup_file);
        }
        
        rename(self::$log_file, $backup_file);
    }
    
    public static function get_recent_errors($limit = 50) {
        if (!file_exists(self::$log_file)) {
            return array();
        }
        
        $lines = file(self::$log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_slice($lines, -$limit);
    }
}