<?php
/**
 * XPM Image SEO Alt Text Generator
 * 
 * @package XPM_Image_SEO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle AI-powered alt text generation with smart keywords
 */
class XPM_Alt_Text_Generator {
    
    private $option_name = 'xpm_image_seo_settings';
    
    public function __construct() {
        add_action('xpm_generate_alt_text', array($this, 'generate_alt_text_async'));
    }
    
    /**
     * Generate alt text asynchronously (for scheduled events)
     */
    public function generate_alt_text_async($attachment_id) {
        $keywords_extractor = new XPM_Keywords();
        $keywords = $keywords_extractor->extract_keywords_for_image($attachment_id);
        $this->generate_alt_text_with_keywords($attachment_id, $keywords);
    }
    
    /**
     * Generate alt text with smart keywords
     */
    public function generate_alt_text_with_keywords($attachment_id, $keywords = array()) {
        $start_time = microtime(true);
        $options = get_option($this->option_name);
        $api_key = isset($options['api_key']) ? trim($options['api_key']) : '';
        
        if (empty($api_key)) {
            return array('success' => false, 'message' => __('OpenAI API key not configured', 'xpm-image-seo'));
        }
        
        $image_url = wp_get_attachment_url($attachment_id);
        if (!$image_url) {
            return array('success' => false, 'message' => __('Could not get image URL', 'xpm-image-seo'));
        }
        
        // Get image metadata for context
        $attachment = get_post($attachment_id);
        $image_meta = wp_get_attachment_metadata($attachment_id);
        
        // Build enhanced prompt with keywords
        $custom_prompt = isset($options['prompt']) ? trim($options['prompt']) : '';
        $default_prompt = $this->build_smart_prompt($keywords, $attachment);
        
        $prompt = !empty($custom_prompt) ? $custom_prompt : $default_prompt;
        
        // Add filename context if helpful
        if ($attachment && !empty($attachment->post_title)) {
            $prompt .= sprintf(__(" The image filename suggests it might be about: %s", 'xpm-image-seo'), $attachment->post_title);
        }
        
        // Retry logic for better reliability
        $max_retries = 3;
        $retry_count = 0;
        
        while ($retry_count < $max_retries) {
            $result = $this->make_openai_request($image_url, $prompt);
            
            if ($result['success']) {
                $alt_text = $this->process_alt_text_response($result['content'], $keywords);
                $processing_time = microtime(true) - $start_time;
                
                // Update the alt text
                $old_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
                
                // Store keywords used for this image
                update_post_meta($attachment_id, '_xpm_keywords_used', $keywords);
                update_post_meta($attachment_id, '_xpm_alt_text_generated', current_time('mysql'));
                
                return array(
                    'success' => true,
                    'alt_text' => $alt_text,
                    'old_alt_text' => $old_alt,
                    'keywords_used' => $keywords,
                    'retry_count' => $retry_count,
                    'processing_time' => $processing_time,
                    'api_usage' => $result['api_usage'] ?? array()
                );
            }
            
            $retry_count++;
            if ($retry_count < $max_retries) {
                sleep(2); // Wait before retry
            }
        }
        
        return array('success' => false, 'message' => $result['message'] ?? __('Failed after multiple retries', 'xpm-image-seo'));
    }
    
    /**
     * Build smart prompt with keyword integration
     */
    private function build_smart_prompt($keywords, $attachment = null) {
        $base_prompt = __("Analyze this image and provide a concise, SEO-friendly alt text description. Focus on the main subject, important visual elements, and context that would be valuable for both screen readers and search engines.", 'xpm-image-seo');
        
        if (!empty($keywords)) {
            $keywords_str = implode(', ', array_slice($keywords, 0, 3));
            $base_prompt .= sprintf(__(" If relevant and natural, try to incorporate these keywords: %s.", 'xpm-image-seo'), $keywords_str);
        }
        
        $base_prompt .= __(" Keep the description descriptive but under 125 characters. Make it sound natural and helpful for screen readers.", 'xpm-image-seo');
        
        // Add context from filename or existing title
        if ($attachment && !empty($attachment->post_title)) {
            $base_prompt .= sprintf(__(" The image is titled '%s' which may provide context.", 'xpm-image-seo'), $attachment->post_title);
        }
        
        return $base_prompt;
    }
    
    /**
     * Make OpenAI API request with enhanced error handling
     */
    private function make_openai_request($image_url, $prompt) {
        $options = get_option($this->option_name);
        $api_key = $options['api_key'];
        
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
            
            // Handle specific error types
            if (isset($error_data['error']['type'])) {
                switch ($error_data['error']['type']) {
                    case 'insufficient_quota':
                        $error_message = __('OpenAI API quota exceeded. Please check your account billing.', 'xpm-image-seo');
                        break;
                    case 'invalid_request_error':
                        $error_message = __('Invalid API request. Please check your settings.', 'xpm-image-seo');
                        break;
                    case 'rate_limit_exceeded':
                        $error_message = __('API rate limit exceeded. Please try again later.', 'xpm-image-seo');
                        break;
                    default:
                        $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : __('Unknown API error', 'xpm-image-seo');
                }
            } else {
                $error_message = __('API request failed with code: ', 'xpm-image-seo') . $response_code;
            }
            
            return array('success' => false, 'message' => $error_message);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return array('success' => false, 'message' => __('Invalid API response format', 'xpm-image-seo'));
        }
        
        return array(
            'success' => true,
            'content' => $data['choices'][0]['message']['content'],
            'api_usage' => isset($data['usage']) ? $data['usage'] : array()
        );
    }
    
    /**
     * Process and clean alt text response
     */
    private function process_alt_text_response($raw_content, $keywords = array()) {
        $alt_text = trim($raw_content);
        
        // Clean up the alt text
        $alt_text = trim($alt_text, '"\'');
        $alt_text = str_replace(array("\n", "\r", "\t"), ' ', $alt_text);
        $alt_text = preg_replace('/\s+/', ' ', $alt_text);
        
        // Remove common AI prefixes
        $prefixes_to_remove = array(
            'This image shows',
            'The image depicts',
            'This is an image of',
            'The photo shows',
            'This picture shows',
            'Image shows',
            'Picture of'
        );
        
        foreach ($prefixes_to_remove as $prefix) {
            if (stripos($alt_text, $prefix) === 0) {
                $alt_text = trim(substr($alt_text, strlen($prefix)));
                break;
            }
        }
        
        // Ensure first letter is capitalized
        $alt_text = ucfirst($alt_text);
        
        // Limit alt text length (recommended max 125 characters)
        if (strlen($alt_text) > 125) {
            $alt_text = $this->smart_truncate($alt_text, 125);
        }
        
        return $alt_text;
    }
    
    /**
     * Smart truncate that tries to end at word boundaries
     */
    private function smart_truncate($text, $max_length) {
        if (strlen($text) <= $max_length) {
            return $text;
        }
        
        $truncated = substr($text, 0, $max_length - 3);
        $last_space = strrpos($truncated, ' ');
        
        if ($last_space !== false && $last_space > $max_length * 0.7) {
            return substr($truncated, 0, $last_space) . '...';
        }
        
        return $truncated . '...';
    }
    
    /**
     * Generate alt text for existing core method compatibility
     */
    public function generate_alt_text($attachment_id) {
        // Extract smart keywords first
        $keywords_extractor = new XPM_Keywords();
        $keywords = $keywords_extractor->extract_keywords_for_image($attachment_id);
        
        // Use enhanced generation with keywords
        return $this->generate_alt_text_with_keywords($attachment_id, $keywords);
    }
    
    /**
     * Batch generate alt text for multiple images
     */
    public function batch_generate_alt_text($attachment_ids) {
        $results = array();
        
        foreach ($attachment_ids as $attachment_id) {
            // Check if image already has alt text
            $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            if (!empty($existing_alt)) {
                $results[$attachment_id] = array('success' => false, 'message' => __('Alt text already exists', 'xpm-image-seo'));
                continue;
            }
            
            $result = $this->generate_alt_text($attachment_id);
            $results[$attachment_id] = $result;
            
            // Rate limiting delay
            sleep(2);
        }
        
        return $results;
    }
}