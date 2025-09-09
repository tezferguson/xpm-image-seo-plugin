<?php
/**
 * XPM Image SEO AJAX Handlers
 * 
 * @package XPM_Image_SEO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle all AJAX requests for the plugin
 */
class XPM_AJAX_Handlers {
    
    private $option_name = 'xpm_image_seo_settings';
    private $rate_limiter;
    
    public function __construct() {
        $this->rate_limiter = new XPM_Rate_Limiter();
        
        // Alt text AJAX handlers - FIXED THE TYPO HERE
        add_action('wp_ajax_xpm_get_images_without_alt', array($this, 'ajax_get_images_without_alt'));
        add_action('wp_ajax_xpm_bulk_update_alt_text', array($this, 'ajax_bulk_update_alt_text'));
        
        // Optimization AJAX handlers
        add_action('wp_ajax_xpm_get_unoptimized_images', array($this, 'ajax_get_unoptimized_images'));
        add_action('wp_ajax_xpm_bulk_optimize_image', array($this, 'ajax_bulk_optimize_image'));
        add_action('wp_ajax_xpm_get_optimization_stats', array($this, 'ajax_get_optimization_stats'));
        add_action('wp_ajax_xpm_restore_from_backup', array($this, 'ajax_restore_from_backup'));
    }
    
    /**
     * Get images without alt text
     */
    public function ajax_get_images_without_alt() {
        check_ajax_referer('xpm_image_seo_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Insufficient permissions', 'xpm-image-seo'));
        }
        
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '='
                )
            )
        );
        
        $images = get_posts($args);
        $result = array();
        
        foreach ($images as $image) {
            $thumb_url = wp_get_attachment_thumb_url($image->ID);
            if ($thumb_url) {
                $result[] = array(
                    'id' => $image->ID,
                    'title' => $image->post_title ?: __('Untitled', 'xpm-image-seo'),
                    'url' => $thumb_url,
                    'upload_date' => get_the_date('Y-m-d', $image->ID)
                );
            }
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Enhanced bulk update alt text with smart keywords
     */
    public function ajax_bulk_update_alt_text() {
        check_ajax_referer('xpm_image_seo_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Insufficient permissions', 'xpm-image-seo'));
        }
        
        // Rate limiting check
        if ($this->rate_limiter->is_rate_limited()) {
            wp_send_json_error(__('Rate limit exceeded. Please wait before making more requests.', 'xpm-image-seo'));
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        if (!$attachment_id) {
            wp_send_json_error(__('Invalid attachment ID', 'xpm-image-seo'));
        }
        
        // Extract keywords for this image
        $keywords = $this->extract_smart_keywords($attachment_id);
        
        // Generate alt text with keywords
        $alt_text_generator = new XPM_Alt_Text_Generator();
        $result = $alt_text_generator->generate_alt_text_with_keywords($attachment_id, $keywords);
        
        if ($result['success']) {
            // Update additional fields if enabled
            $options = get_option($this->option_name);
            $updates_made = array('Alt Text');
            
            if (!empty($options['update_title'])) {
                $existing_title = get_the_title($attachment_id);
                if (empty($existing_title) || empty($options['skip_existing'])) {
                    wp_update_post(array(
                        'ID' => $attachment_id,
                        'post_title' => $result['alt_text']
                    ));
                    $updates_made[] = 'Title';
                }
            }
            
            if (!empty($options['update_description'])) {
                $existing_desc = get_post_field('post_content', $attachment_id);
                if (empty($existing_desc) || empty($options['skip_existing'])) {
                    wp_update_post(array(
                        'ID' => $attachment_id,
                        'post_content' => $result['alt_text']
                    ));
                    $updates_made[] = 'Description';
                }
            }
            
            $result['updates_made'] = $updates_made;
            $result['keywords_used'] = $keywords;
            $result['detailed_message'] = sprintf(
                __('Updated %s with smart keyword optimization', 'xpm-image-seo'),
                implode(', ', $updates_made)
            );
            
            // Log the update if database logging is available
            if (class_exists('XPM_Database')) {
                XPM_Database::log_alt_text_generation(
                    $attachment_id,
                    $result['old_alt_text'] ?? '',
                    $result['alt_text'],
                    $keywords,
                    $result['api_usage']['total_tokens'] ?? 0,
                    $result['processing_time'] ?? 0,
                    true
                );
            }
            
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Get unoptimized images for the optimizer page
     */
    public function ajax_get_unoptimized_images() {
        check_ajax_referer('xpm_image_seo_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Insufficient permissions', 'xpm-image-seo'));
        }
        
        // Get all images that haven't been optimized
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_xpm_optimized',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        
        $images = get_posts($args);
        $result = array();
        $total_size = 0;
        
        foreach ($images as $image) {
            $file_path = get_attached_file($image->ID);
            if (!$file_path || !file_exists($file_path)) continue;
            
            $file_size = filesize($file_path);
            $image_meta = wp_get_attachment_metadata($image->ID);
            
            $result[] = array(
                'id' => $image->ID,
                'title' => $image->post_title ?: __('Untitled', 'xpm-image-seo'),
                'url' => wp_get_attachment_thumb_url($image->ID),
                'file_size' => $file_size,
                'file_size_human' => size_format($file_size),
                'dimensions' => isset($image_meta['width']) ? $image_meta['width'] . 'x' . $image_meta['height'] : 'Unknown',
                'format' => strtoupper(pathinfo($file_path, PATHINFO_EXTENSION))
            );
            
            $total_size += $file_size;
        }
        
        // Calculate estimated savings (typical 20-30% for images)
        $estimated_savings = $total_size * 0.25;
        
        wp_send_json_success(array(
            'images' => $result,
            'total_size' => $total_size,
            'total_size_human' => size_format($total_size),
            'estimated_savings' => array(
                'bytes' => $estimated_savings,
                'human' => size_format($estimated_savings),
                'percent' => '25'
            )
        ));
    }
    
    /**
     * Bulk optimize single image
     */
    public function ajax_bulk_optimize_image() {
        check_ajax_referer('xpm_image_seo_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Insufficient permissions', 'xpm-image-seo'));
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        if (!$attachment_id) {
            wp_send_json_error(__('Invalid attachment ID', 'xpm-image-seo'));
        }
        
        $optimizer = new XPM_Image_Optimizer();
        $result = $optimizer->optimize_single_image($attachment_id);
        
        if ($result['success']) {
            // Log the optimization if database logging is available
            if (class_exists('XPM_Database')) {
                XPM_Database::log_optimization(
                    $attachment_id,
                    $result['original_size'],
                    $result['new_size'],
                    get_option('xpm_image_seo_settings')['compression_quality'] ?? 85,
                    $result['resized'],
                    $result['webp_created'],
                    $result['backup_created'],
                    $result['processing_time'] ?? 0,
                    true
                );
            }
            
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Get optimization statistics
     */
    public function ajax_get_optimization_stats() {
        check_ajax_referer('xpm_image_seo_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Insufficient permissions', 'xpm-image-seo'));
        }
        
        if (class_exists('XPM_Database')) {
            $stats = XPM_Database::get_optimization_statistics();
            wp_send_json_success($stats['optimization']);
        } else {
            // Fallback basic stats
            global $wpdb;
            $optimized_count = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_xpm_optimized' 
                AND meta_value = '1'
            ");
            
            wp_send_json_success(array(
                'total_images' => intval($optimized_count),
                'total_bytes_saved' => 0,
                'total_original_size' => 0
            ));
        }
    }
    
    /**
     * Restore image from backup
     */
    public function ajax_restore_from_backup() {
        check_ajax_referer('xpm_image_seo_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Insufficient permissions', 'xpm-image-seo'));
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        if (!$attachment_id) {
            wp_send_json_error(__('Invalid attachment ID', 'xpm-image-seo'));
        }
        
        $optimizer = new XPM_Image_Optimizer();
        $result = $optimizer->restore_from_backup($attachment_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Extract smart keywords for image
     */
    private function extract_smart_keywords($attachment_id) {
        if (class_exists('XPM_Keywords')) {
            $keywords_extractor = new XPM_Keywords();
            return $keywords_extractor->extract_keywords_for_image($attachment_id);
        }
        
        // Fallback: return empty array if keywords class not available
        return array();
    }
}