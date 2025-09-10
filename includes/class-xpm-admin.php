/**
     * Sanitize settings - UPDATED WITH DUPLICATOR SETTINGS
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Get existing options to preserve values from other tabs
        $existing_options = get_option($this->option_name, array());
        $sanitized = $existing_options;
        
        // Alt Text settings
        if (isset($input['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field($input['api_key']);
        }
        if (isset($input['global_keywords'])) {
            $sanitized['global_keywords'] = sanitize_textarea_field($input['global_keywords']);
        }
        if (isset($input['use_contextual_keywords'])) {
            $sanitized['use_contextual_keywords'] = intval($input['use_contextual_keywords']);
        }
        if (isset($input['keyword_priority'])) {
            $sanitized['keyword_priority'] = sanitize_text_field($input['keyword_priority']);
        }
        if (isset($input['max_keywords'])) {
            $sanitized['max_keywords'] = intval($input['max_keywords']);
        }
        if (isset($input['auto_generate'])) {
            $sanitized['auto_generate'] = intval($input['auto_generate']);
        }
        if (isset($input['prompt'])) {
            $sanitized['prompt'] = sanitize_textarea_field($input['prompt']);
        }
        if (isset($input['update_title'])) {
            $sanitized['update_title'] = intval($input['update_title']);
        }
        if (isset($input['update_description'])) {
            $sanitized['update_description'] = intval($input['update_description']);
        }
        if (isset($input['skip_existing'])) {
            $sanitized['skip_existing'] = intval($input['skip_existing']);
        }
        
        // Image Optimization settings
        if (isset($input['auto_optimize'])) {
            $sanitized['auto_optimize'] = intval($input['auto_optimize']);
        }
        if (isset($input['compression_quality'])) {
            $sanitized['compression_quality'] = max(60, min(100, intval($input['compression_quality'])));
        }
        if (isset($input['max_width'])) {
            $sanitized['max_width'] = max(500, min(4000, intval($input['max_width'])));
        }
        if (isset($input['max_height'])) {
            $sanitized['max_height'] = max(0, min(4000, intval($input['max_height'])));
        }
        if (isset($input['backup_originals'])) {
            $sanitized['backup_originals'] = intval($input['backup_originals']);
        }
        if (isset($input['convert_to_webp'])) {
            $sanitized['convert_to_webp'] = intval($input['convert_to_webp']);
        }
        
        // Performance settings
        if (isset($input['enable_lazy_loading'])) {
            $sanitized['enable_lazy_loading'] = intval($input['enable_lazy_loading']);
        }
        if (isset($input['lazy_loading_threshold'])) {
            $sanitized['lazy_loading_threshold'] = max(0, min(1000, intval($input['lazy_loading_threshold'])));
        }
        if (isset($input['lazy_loading_placeholder'])) {
            $sanitized['lazy_loading_placeholder'] = sanitize_text_field($input['lazy_loading_placeholder']);
        }
        if (isset($input['lazy_loading_custom_placeholder'])) {
            $sanitized['lazy_loading_custom_placeholder'] = esc_url_raw($input['lazy_loading_custom_placeholder']);
        }
        if (isset($input['lazy_loading_effect'])) {
            $sanitized['lazy_loading_effect'] = sanitize_text_field($input['lazy_loading_effect']);
        }
        
        // NEW: Post Duplicator settings
        if (isset($input['enable_post_duplicator'])) {
            $sanitized['enable_post_duplicator'] = intval($input['enable_post_duplicator']);
        }
        if (isset($input['duplicate_status'])) {
            $allowed_statuses = array('draft', 'pending', 'private');
            $sanitized['duplicate_status'] = in_array($input['duplicate_status'], $allowed_statuses) ? 
                $input['duplicate_status'] : 'draft';
        }
        if (isset($input['duplicate_author'])) {
            $allowed_authors = array('current', 'original');
            $sanitized['duplicate_author'] = in_array($input['duplicate_author'], $allowed_authors) ? 
                $input['duplicate_author'] : 'current';
        }
        if (isset($input['duplicate_suffix'])) {
            $sanitized['duplicate_suffix'] = sanitize_text_field($input['duplicate_suffix']);
            // Ensure it's not empty
            if (empty($sanitized['duplicate_suffix'])) {
                $sanitized['duplicate_suffix'] = 'Copy';
            }
        }
        
        return $sanitized;
    }