<?php
/**
 * XPM Image SEO Core Functionality
 * 
 * @package XPM_Image_SEO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core plugin functionality
 */
class XPM_Image_SEO_Core {
    
    private $option_name = 'xpm_image_seo_settings';
    
    public function __construct() {
        add_action('add_attachment', array($this, 'auto_generate_alt_text'));
        add_action('wp_ajax_xpm_bulk_update_alt_text', array($this, 'ajax_bulk_update_alt_text'));
        add_action('wp_ajax_xmp_get_images_without_alt', array($this, 'ajax_get_images_without_alt'));
    }
    
    /**
     * Auto-generate alt text for new uploads
     */
    public function auto_generate_alt_text($attachment_id) {
        $options = get_option($this->option_name);
        
        if (!isset($options['auto_generate']) || !$options['auto_generate']) {
            return;
        }
        
        // Check if it's an image
        if (!wp_attachment_is_image($attachment_id)) {
            return;
        }
        
        // Check if alt text already exists
        $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if (!empty($existing_alt)) {
            return;
        }
        
        // Generate alt text in background
        wp_schedule_single_event(time() + 10, 'xpm_generate_alt_text', array($attachment_id));
        add_action('xpm_generate_alt_text', array($this, 'generate_alt_text_async'));
    }
    
    /**
     * Generate alt text asynchronously
     */
    public function generate_alt_text_async($attachment_id) {
        $this->generate_alt_text($attachment_id);
    }
    
    /**
     * AJAX handler for getting images without alt text
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
     * AJAX handler for bulk updating alt text
     */
    public function ajax_bulk_update_alt_text() {
        check_ajax_referer('xpm_image_seo_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Insufficient permissions', 'xpm-image-seo'));
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        
        if (!$attachment_id) {
            wp_send_json_error(__('Invalid attachment ID', 'xpm-image-seo'));
        }
        
        $result = $this->generate_alt_text($attachment_id);
        
        if ($result['success']) {
            // Log the update
            $this->log_alt_text_update($attachment_id, '', $result['alt_text']);
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Generate alt text using OpenAI API
     */
    public function generate_alt_text($attachment_id) {
        $options = get_option($this->option_name);
        $api_key = isset($options['api_key']) ? trim($options['api_key']) : '';
        
        if (empty($api_key)) {
            return array('success' => false, 'message' => __('OpenAI API key not configured', 'xpm-image-seo'));
        }
        
        // Get image URL
        $image_url = wp_get_attachment_url($attachment_id);
        if (!$image_url) {
            return array('success' => false, 'message' => __('Could not get image URL', 'xpm-image-seo'));
        }
        
        // Get image metadata for context
        $attachment = get_post($attachment_id);
        $image_meta = wp_get_attachment_metadata($attachment_id);
        
        // Prepare the prompt
        $custom_prompt = isset($options['prompt']) ? trim($options['prompt']) : '';
        $default_prompt = __("Analyze this image and provide a concise, SEO-friendly alt text description. Focus on the main subject, important visual elements, and context that would be valuable for both screen readers and search engines. Keep it descriptive but under 125 characters.", 'xpm-image-seo');
        
        $prompt = !empty($custom_prompt) ? $custom_prompt : $default_prompt;
        
        // Add filename context if helpful
        if ($attachment && !empty($attachment->post_title)) {
            $prompt .= sprintf(__(" The image filename suggests it might be about: %s", 'xpm-image-seo'), $attachment->post_title);
        }
        
        // Prepare API request
        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        );
        
        $body = array(
            'model' => 'gpt-4o-mini',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        array(
                            'type' => 'text',
                            'text' => $prompt
                        ),
                        array(
                            'type' => 'image_url',
                            'image_url' => array(
                                'url' => $image_url,
                                'detail' => 'low' // Cost optimization
                            )
                        )
                    )
                )
            ),
            'max_tokens' => 150,
            'temperature' => 0.3 // More consistent results
        );
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 60,
            'user-agent' => 'XPM-Image-SEO/' . XPM_IMAGE_SEO_VERSION . ' (WordPress Plugin)'
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => __('API request failed: ', 'xpm-image-seo') . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : __('Unknown API error', 'xpm-image-seo');
            return array('success' => false, 'message' => __('API error: ', 'xpm-image-seo') . $error_message);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return array('success' => false, 'message' => __('Invalid API response format', 'xpm-image-seo'));
        }
        
        $alt_text = trim($data['choices'][0]['message']['content']);
        
        // Clean up the alt text
        $alt_text = trim($alt_text, '"\'');
        $alt_text = str_replace(array("\n", "\r", "\t"), ' ', $alt_text);
        $alt_text = preg_replace('/\s+/', ' ', $alt_text);
        
        // Limit alt text length (recommended max 125 characters)
        if (strlen($alt_text) > 125) {
            $alt_text = substr($alt_text, 0, 122) . '...';
        }
        
        // Update the alt text
        $old_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
        
        return array(
            'success' => true, 
            'alt_text' => $alt_text,
            'old_alt_text' => $old_alt,
            'api_usage' => array(
                'tokens_used' => isset($data['usage']['total_tokens']) ? $data['usage']['total_tokens'] : 0
            )
        );
    }
    
    /**
     * Log alt text updates
     */
    private function log_alt_text_update($attachment_id, $old_alt, $new_alt) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'xpm_image_seo_log';
        
        $wpdb->insert(
            $table_name,
            array(
                'attachment_id' => $attachment_id,
                'old_alt_text' => $old_alt,
                'new_alt_text' => $new_alt,
                'created_at' => current_time('mysql')
            )
        );
    }
}