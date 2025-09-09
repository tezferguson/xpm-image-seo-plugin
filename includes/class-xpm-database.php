<?php
/**
 * XPM Image SEO Database Manager
 * 
 * @package XPM_Image_SEO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle database operations and logging
 */
class XPM_Database {
    
    /**
     * Create database tables on activation
     */
    public static function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Alt text generation log table
        $alt_text_log_table = $wpdb->prefix . 'xpm_alt_text_log';
        $alt_text_sql = "CREATE TABLE $alt_text_log_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            attachment_id bigint(20) NOT NULL,
            old_alt_text text,
            new_alt_text text,
            keywords_used text,
            api_tokens_used int,
            processing_time float,
            success tinyint(1) DEFAULT 1,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY attachment_id (attachment_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Image optimization log table
        $optimization_log_table = $wpdb->prefix . 'xpm_optimization_log';
        $optimization_sql = "CREATE TABLE $optimization_log_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            attachment_id bigint(20) NOT NULL,
            original_size bigint(20),
            optimized_size bigint(20),
            bytes_saved bigint(20),
            compression_quality int,
            resized tinyint(1) DEFAULT 0,
            webp_created tinyint(1) DEFAULT 0,
            backup_created tinyint(1) DEFAULT 0,
            processing_time float,
            success tinyint(1) DEFAULT 1,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY attachment_id (attachment_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($alt_text_sql);
        dbDelta($optimization_sql);
        
        // Store database version for future updates
        update_option('xpm_image_seo_db_version', '1.1');
    }
    
    /**
     * Enhanced logging for alt text generation
     */
    public static function log_alt_text_generation($attachment_id, $old_alt, $new_alt, $keywords = array(), $tokens_used = 0, $processing_time = 0, $success = true, $error = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'xpm_alt_text_log';
        
        $keywords_str = '';
        if (is_array($keywords)) {
            $keywords_str = implode(', ', $keywords);
        } elseif (is_string($keywords)) {
            $keywords_str = $keywords;
        }
        
        $wpdb->insert(
            $table_name,
            array(
                'attachment_id' => $attachment_id,
                'old_alt_text' => $old_alt,
                'new_alt_text' => $new_alt,
                'keywords_used' => $keywords_str,
                'api_tokens_used' => $tokens_used,
                'processing_time' => $processing_time,
                'success' => $success ? 1 : 0,
                'error_message' => $error,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%d', '%f', '%d', '%s', '%s')
        );
    }
    
    /**
     * Enhanced logging for image optimization
     */
    public static function log_optimization($attachment_id, $original_size, $optimized_size, $quality, $resized = false, $webp = false, $backup = false, $processing_time = 0, $success = true, $error = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'xpm_optimization_log';
        $bytes_saved = $original_size - $optimized_size;
        
        $wpdb->insert(
            $table_name,
            array(
                'attachment_id' => $attachment_id,
                'original_size' => $original_size,
                'optimized_size' => $optimized_size,
                'bytes_saved' => $bytes_saved,
                'compression_quality' => $quality,
                'resized' => $resized ? 1 : 0,
                'webp_created' => $webp ? 1 : 0,
                'backup_created' => $backup ? 1 : 0,
                'processing_time' => $processing_time,
                'success' => $success ? 1 : 0,
                'error_message' => $error,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%d', '%s', '%s')
        );
    }
    
    /**
     * Get optimization statistics for dashboard
     */
    public static function get_optimization_statistics() {
        global $wpdb;
        
        $optimization_table = $wpdb->prefix . 'xpm_optimization_log';
        $alt_text_table = $wpdb->prefix . 'xpm_alt_text_log';
        
        // Check if tables exist
        if ($wpdb->get_var("SHOW TABLES LIKE '$optimization_table'") != $optimization_table) {
            return array(
                'optimization' => array(
                    'total_images' => 0,
                    'total_bytes_saved' => 0,
                    'total_original_size' => 0,
                    'avg_processing_time' => 0,
                    'total_resized' => 0,
                    'total_webp_created' => 0,
                    'recent_30_days' => 0
                ),
                'alt_text' => array(
                    'total_generated' => 0,
                    'total_tokens_used' => 0,
                    'avg_processing_time' => 0,
                    'recent_30_days' => 0
                )
            );
        }
        
        // Optimization stats
        $optimization_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_optimized,
                SUM(bytes_saved) as total_bytes_saved,
                SUM(original_size) as total_original_size,
                AVG(processing_time) as avg_processing_time,
                SUM(CASE WHEN resized = 1 THEN 1 ELSE 0 END) as total_resized,
                SUM(CASE WHEN webp_created = 1 THEN 1 ELSE 0 END) as total_webp_created
            FROM $optimization_table 
            WHERE success = 1
        ");
        
        // Alt text stats
        $alt_text_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_generated,
                SUM(api_tokens_used) as total_tokens_used,
                AVG(processing_time) as avg_processing_time
            FROM $alt_text_table 
            WHERE success = 1
        ");
        
        // Recent activity (last 30 days)
        $recent_optimization = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM $optimization_table 
            WHERE success = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        $recent_alt_text = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM $alt_text_table 
            WHERE success = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        return array(
            'optimization' => array(
                'total_images' => intval($optimization_stats->total_optimized ?? 0),
                'total_bytes_saved' => intval($optimization_stats->total_bytes_saved ?? 0),
                'total_original_size' => intval($optimization_stats->total_original_size ?? 0),
                'avg_processing_time' => floatval($optimization_stats->avg_processing_time ?? 0),
                'total_resized' => intval($optimization_stats->total_resized ?? 0),
                'total_webp_created' => intval($optimization_stats->total_webp_created ?? 0),
                'recent_30_days' => intval($recent_optimization)
            ),
            'alt_text' => array(
                'total_generated' => intval($alt_text_stats->total_generated ?? 0),
                'total_tokens_used' => intval($alt_text_stats->total_tokens_used ?? 0),
                'avg_processing_time' => floatval($alt_text_stats->avg_processing_time ?? 0),
                'recent_30_days' => intval($recent_alt_text)
            )
        );
    }
    
    /**
     * Get recent activity log
     */
    public static function get_recent_activity($limit = 50) {
        global $wpdb;
        
        $optimization_table = $wpdb->prefix . 'xpm_optimization_log';
        $alt_text_table = $wpdb->prefix . 'xpm_alt_text_log';
        
        // Combined query for recent activity
        $activity = $wpdb->get_results($wpdb->prepare("
            (SELECT 
                'optimization' as type,
                attachment_id,
                CONCAT('Optimized: ', FORMAT(bytes_saved/1024, 0), 'KB saved') as description,
                success,
                created_at
            FROM $optimization_table
            ORDER BY created_at DESC
            LIMIT %d)
            UNION ALL
            (SELECT 
                'alt_text' as type,
                attachment_id,
                CONCAT('Alt text: \"', LEFT(new_alt_text, 50), '\"') as description,
                success,
                created_at
            FROM $alt_text_table
            ORDER BY created_at DESC
            LIMIT %d)
            ORDER BY created_at DESC
            LIMIT %d
        ", $limit, $limit, $limit));
        
        // Add attachment titles
        foreach ($activity as &$item) {
            $item->attachment_title = get_the_title($item->attachment_id) ?: __('Untitled', 'xpm-image-seo');
        }
        
        return $activity;
    }
    
    /**
     * Get error statistics
     */
    public static function get_error_statistics() {
        global $wpdb;
        
        $optimization_table = $wpdb->prefix . 'xpm_optimization_log';
        $alt_text_table = $wpdb->prefix . 'xpm_alt_text_log';
        
        $optimization_errors = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM $optimization_table 
            WHERE success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        $alt_text_errors = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM $alt_text_table 
            WHERE success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        return array(
            'optimization_errors_7_days' => intval($optimization_errors),
            'alt_text_errors_7_days' => intval($alt_text_errors)
        );
    }
    
    /**
     * Clean up old logs
     */
    public static function cleanup_old_logs($days = 90) {
        global $wpdb;
        
        $optimization_table = $wpdb->prefix . 'xpm_optimization_log';
        $alt_text_table = $wpdb->prefix . 'xpm_alt_text_log';
        
        // Delete optimization logs older than specified days
        $wpdb->query($wpdb->prepare("
            DELETE FROM $optimization_table 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $days));
        
        // Delete alt text logs older than specified days
        $wpdb->query($wpdb->prepare("
            DELETE FROM $alt_text_table 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $days));
    }
    
    /**
     * Get performance metrics
     */
    public static function get_performance_metrics() {
        global $wpdb;
        
        $optimization_table = $wpdb->prefix . 'xpm_optimization_log';
        $alt_text_table = $wpdb->prefix . 'xpm_alt_text_log';
        
        // Average processing times
        $optimization_avg_time = $wpdb->get_var("
            SELECT AVG(processing_time) 
            FROM $optimization_table 
            WHERE success = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        $alt_text_avg_time = $wpdb->get_var("
            SELECT AVG(processing_time) 
            FROM $alt_text_table 
            WHERE success = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        // Success rates
        $optimization_success_rate = $wpdb->get_var("
            SELECT (SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100
            FROM $optimization_table 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        $alt_text_success_rate = $wpdb->get_var("
            SELECT (SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100
            FROM $alt_text_table 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        return array(
            'optimization_avg_time' => floatval($optimization_avg_time ?? 0),
            'alt_text_avg_time' => floatval($alt_text_avg_time ?? 0),
            'optimization_success_rate' => floatval($optimization_success_rate ?? 100),
            'alt_text_success_rate' => floatval($alt_text_success_rate ?? 100)
        );
    }
    
    /**
     * Get database size information
     */
    public static function get_database_info() {
        global $wpdb;
        
        $optimization_table = $wpdb->prefix . 'xpm_optimization_log';
        $alt_text_table = $wpdb->prefix . 'xpm_alt_text_log';
        
        $optimization_size = $wpdb->get_var("
            SELECT ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) 
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE() 
            AND table_name = '$optimization_table'
        ");
        
        $alt_text_size = $wpdb->get_var("
            SELECT ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) 
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE() 
            AND table_name = '$alt_text_table'
        ");
        
        $optimization_rows = $wpdb->get_var("SELECT COUNT(*) FROM $optimization_table");
        $alt_text_rows = $wpdb->get_var("SELECT COUNT(*) FROM $alt_text_table");
        
        return array(
            'optimization_table_size_mb' => floatval($optimization_size ?? 0),
            'alt_text_table_size_mb' => floatval($alt_text_size ?? 0),
            'optimization_rows' => intval($optimization_rows ?? 0),
            'alt_text_rows' => intval($alt_text_rows ?? 0),
            'total_size_mb' => floatval($optimization_size ?? 0) + floatval($alt_text_size ?? 0)
        );
    }
    
    /**
     * Export data to CSV
     */
    public static function export_logs_to_csv($type = 'optimization', $days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'xpm_' . $type . '_log';
        
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table_name 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY created_at DESC
        ", $days));
        
        if (empty($logs)) {
            return false;
        }
        
        $filename = 'xpm_' . $type . '_logs_' . date('Y-m-d') . '.csv';
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        $file = fopen($file_path, 'w');
        
        // Write headers
        if (!empty($logs)) {
            $headers = array_keys((array) $logs[0]);
            fputcsv($file, $headers);
        }
        
        // Write data
        foreach ($logs as $log) {
            fputcsv($file, (array) $log);
        }
        
        fclose($file);
        
        return $upload_dir['url'] . '/' . $filename;
    }
}