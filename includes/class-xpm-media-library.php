<?php
/**
 * XPM Image SEO Media Library Integration
 * 
 * @package XPM_Image_SEO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add columns and enhancements to media library
 */
class XPM_Media_Library_Integration {
    
    public function __construct() {
        add_filter('manage_media_columns', array($this, 'add_media_columns'));
        add_action('manage_media_custom_column', array($this, 'display_media_columns'), 10, 2);
        add_filter('manage_upload_sortable_columns', array($this, 'make_columns_sortable'));
        
        // Add bulk actions
        add_filter('bulk_actions-upload', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-upload', array($this, 'handle_bulk_actions'), 10, 3);
        
        // Add media library filters
        add_action('restrict_manage_posts', array($this, 'add_media_filters'));
        add_filter('pre_get_posts', array($this, 'filter_media_query'));
        
        // Add attachment fields
        add_filter('attachment_fields_to_edit', array($this, 'add_attachment_fields'), 10, 2);
        add_filter('attachment_fields_to_save', array($this, 'save_attachment_fields'), 10, 2);
        
        // Admin notices for bulk actions
        add_action('admin_notices', array($this, 'bulk_action_notices'));
    }
    
    /**
     * Add custom columns to media library
     */
    public function add_media_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Add our columns after the title column
            if ($key === 'title') {
                $new_columns['xpm_alt_status'] = __('Alt Text', 'xpm-image-seo');
                $new_columns['xpm_optimization'] = __('Optimization', 'xpm-image-seo');
                $new_columns['xpm_file_size'] = __('File Size', 'xpm-image-seo');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Display custom column content
     */
    public function display_media_columns($column_name, $attachment_id) {
        switch ($column_name) {
            case 'xpm_alt_status':
                $this->display_alt_status($attachment_id);
                break;
            case 'xpm_optimization':
                $this->display_optimization_status($attachment_id);
                break;
            case 'xpm_file_size':
                $this->display_file_size($attachment_id);
                break;
        }
    }
    
    /**
     * Display alt text status
     */
    private function display_alt_status($attachment_id) {
        if (!wp_attachment_is_image($attachment_id)) {
            echo '<span class="dashicons dashicons-minus" style="color: #ccc;"></span>';
            return;
        }
        
        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $generated_date = get_post_meta($attachment_id, '_xpm_alt_text_generated', true);
        $keywords_used = get_post_meta($attachment_id, '_xpm_keywords_used', true);
        
        if (!empty($alt_text)) {
            $icon_color = $generated_date ? '#46b450' : '#0073aa';
            $title = $generated_date ? 
                __('AI Generated', 'xpm-image-seo') . ': ' . $alt_text :
                __('Manual Alt Text', 'xpm-image-seo') . ': ' . $alt_text;
            
            if ($keywords_used) {
                $keywords_display = is_array($keywords_used) ? implode(', ', $keywords_used) : $keywords_used;
                $title .= ' | ' . __('Keywords:', 'xpm-image-seo') . ' ' . $keywords_display;
            }
            
            echo '<span class="dashicons dashicons-yes" style="color: ' . $icon_color . ';" title="' . esc_attr($title) . '"></span>';
            
            if ($generated_date) {
                echo '<br><small style="color: #46b450;">' . __('AI', 'xpm-image-seo') . '</small>';
            }
        } else {
            echo '<span class="dashicons dashicons-warning" style="color: #dc3232;" title="' . __('Missing Alt Text', 'xpm-image-seo') . '"></span>';
            echo '<br><small style="color: #dc3232;">' . __('Missing', 'xpm-image-seo') . '</small>';
        }
    }
    
    /**
     * Display optimization status
     */
    private function display_optimization_status($attachment_id) {
        if (!wp_attachment_is_image($attachment_id)) {
            echo '<span class="dashicons dashicons-minus" style="color: #ccc;"></span>';
            return;
        }
        
        $optimized = get_post_meta($attachment_id, '_xpm_optimized', true);
        $bytes_saved = get_post_meta($attachment_id, '_xpm_bytes_saved', true);
        $resized = get_post_meta($attachment_id, '_xpm_resized', true);
        $webp_path = get_post_meta($attachment_id, '_xpm_webp_path', true);
        
        if ($optimized) {
            $title = __('Optimized', 'xpm-image-seo');
            if ($bytes_saved) {
                $title .= ' | ' . __('Saved:', 'xpm-image-seo') . ' ' . size_format($bytes_saved);
            }
            
            echo '<span class="dashicons dashicons-yes" style="color: #46b450;" title="' . esc_attr($title) . '"></span>';
            
            $features = array();
            if ($resized) $features[] = __('Resized', 'xpm-image-seo');
            if ($webp_path) $features[] = __('WebP', 'xpm-image-seo');
            
            if (!empty($features)) {
                echo '<br><small style="color: #46b450;">' . implode(', ', $features) . '</small>';
            }
        } else {
            echo '<span class="dashicons dashicons-clock" style="color: #ffb900;" title="' . __('Not Optimized', 'xpm-image-seo') . '"></span>';
            echo '<br><small style="color: #ffb900;">' . __('Pending', 'xpm-image-seo') . '</small>';
        }
    }
    
    /**
     * Display file size with optimization info
     */
    private function display_file_size($attachment_id) {
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            echo __('N/A', 'xpm-image-seo');
            return;
        }
        
        $current_size = filesize($file_path);
        $original_size = get_post_meta($attachment_id, '_xpm_original_size', true);
        
        echo size_format($current_size);
        
        if ($original_size && $original_size > $current_size) {
            $savings_percent = round((($original_size - $current_size) / $original_size) * 100);
            echo '<br><small style="color: #46b450;">-' . $savings_percent . '%</small>';
        }
    }
    
    /**
     * Make columns sortable
     */
    public function make_columns_sortable($columns) {
        $columns['xpm_file_size'] = 'file_size';
        return $columns;
    }
    
    /**
     * Add bulk actions to media library
     */
    public function add_bulk_actions($actions) {
        $actions['xpm_generate_alt_text'] = __('Generate Alt Text (XPM)', 'xpm-image-seo');
        $actions['xpm_optimize_images'] = __('Optimize Images (XPM)', 'xpm-image-seo');
        return $actions;
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $action, $post_ids) {
        if ($action !== 'xpm_generate_alt_text' && $action !== 'xpm_optimize_images') {
            return $redirect_to;
        }
        
        if (!current_user_can('upload_files')) {
            return $redirect_to;
        }
        
        $processed = 0;
        foreach ($post_ids as $post_id) {
            if (!wp_attachment_is_image($post_id)) {
                continue;
            }
            
            if ($action === 'xpm_generate_alt_text') {
                $existing_alt = get_post_meta($post_id, '_wp_attachment_image_alt', true);
                if (empty($existing_alt)) {
                    wp_schedule_single_event(time() + ($processed * 3), 'xpm_generate_alt_text', array($post_id));
                    $processed++;
                }
            } elseif ($action === 'xpm_optimize_images') {
                $optimized = get_post_meta($post_id, '_xpm_optimized', true);
                if (!$optimized) {
                    wp_schedule_single_event(time() + ($processed * 2), 'xpm_optimize_image', array($post_id));
                    $processed++;
                }
            }
        }
        
        $redirect_to = add_query_arg(array(
            'xpm_processed' => $processed,
            'xpm_action' => $action
        ), $redirect_to);
        
        return $redirect_to;
    }
    
    /**
     * Display bulk action notices
     */
    public function bulk_action_notices() {
        if (!isset($_GET['xpm_processed']) || !isset($_GET['xpm_action'])) {
            return;
        }
        
        $processed = intval($_GET['xpm_processed']);
        $action = sanitize_text_field($_GET['xpm_action']);
        
        if ($processed > 0) {
            $message = '';
            if ($action === 'xpm_generate_alt_text') {
                $message = sprintf(_n(
                    '%d image has been scheduled for alt text generation.',
                    '%d images have been scheduled for alt text generation.',
                    $processed,
                    'xpm-image-seo'
                ), $processed);
            } elseif ($action === 'xpm_optimize_images') {
                $message = sprintf(_n(
                    '%d image has been scheduled for optimization.',
                    '%d images have been scheduled for optimization.',
                    $processed,
                    'xpm-image-seo'
                ), $processed);
            }
            
            if (!empty($message)) {
                echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
            }
        }
    }
    
    /**
     * Add media library filters
     */
    public function add_media_filters() {
        $screen = get_current_screen();
        if ($screen->id !== 'upload') {
            return;
        }
        
        // Alt text filter
        $alt_filter = isset($_GET['xpm_alt_filter']) ? $_GET['xpm_alt_filter'] : '';
        echo '<select name="xpm_alt_filter">';
        echo '<option value="">' . __('All Alt Text Status', 'xpm-image-seo') . '</option>';
        echo '<option value="missing"' . selected($alt_filter, 'missing', false) . '>' . __('Missing Alt Text', 'xpm-image-seo') . '</option>';
        echo '<option value="present"' . selected($alt_filter, 'present', false) . '>' . __('Has Alt Text', 'xpm-image-seo') . '</option>';
        echo '<option value="ai_generated"' . selected($alt_filter, 'ai_generated', false) . '>' . __('AI Generated', 'xpm-image-seo') . '</option>';
        echo '</select>';
        
        // Optimization filter
        $opt_filter = isset($_GET['xpm_opt_filter']) ? $_GET['xpm_opt_filter'] : '';
        echo '<select name="xpm_opt_filter">';
        echo '<option value="">' . __('All Optimization Status', 'xpm-image-seo') . '</option>';
        echo '<option value="optimized"' . selected($opt_filter, 'optimized', false) . '>' . __('Optimized', 'xpm-image-seo') . '</option>';
        echo '<option value="unoptimized"' . selected($opt_filter, 'unoptimized', false) . '>' . __('Not Optimized', 'xpm-image-seo') . '</option>';
        echo '</select>';
    }
    
    /**
     * Filter media query based on custom filters
     */
    public function filter_media_query($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'upload') {
            return;
        }
        
        $meta_query = array();
        
        // Alt text filter
        $alt_filter = isset($_GET['xpm_alt_filter']) ? $_GET['xpm_alt_filter'] : '';
        if ($alt_filter) {
            switch ($alt_filter) {
                case 'missing':
                    $meta_query[] = array(
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
                    );
                    break;
                case 'present':
                    $meta_query[] = array(
                        'key' => '_wp_attachment_image_alt',
                        'value' => '',
                        'compare' => '!='
                    );
                    break;
                case 'ai_generated':
                    $meta_query[] = array(
                        'key' => '_xpm_alt_text_generated',
                        'compare' => 'EXISTS'
                    );
                    break;
            }
        }
        
        // Optimization filter
        $opt_filter = isset($_GET['xpm_opt_filter']) ? $_GET['xpm_opt_filter'] : '';
        if ($opt_filter) {
            switch ($opt_filter) {
                case 'optimized':
                    $meta_query[] = array(
                        'key' => '_xpm_optimized',
                        'value' => '1',
                        'compare' => '='
                    );
                    break;
                case 'unoptimized':
                    $meta_query[] = array(
                        'relation' => 'OR',
                        array(
                            'key' => '_xpm_optimized',
                            'compare' => 'NOT EXISTS'
                        ),
                        array(
                            'key' => '_xpm_optimized',
                            'value' => '1',
                            'compare' => '!='
                        )
                    );
                    break;
            }
        }
        
        if (!empty($meta_query)) {
            $existing_meta_query = $query->get('meta_query');
            if (!empty($existing_meta_query)) {
                $meta_query = array('relation' => 'AND', $existing_meta_query, $meta_query);
            }
            $query->set('meta_query', $meta_query);
        }
    }
    
    /**
     * Add fields to attachment edit screen
     */
    public function add_attachment_fields($fields, $post) {
        if (!wp_attachment_is_image($post->ID)) {
            return $fields;
        }
        
        // XPM Status field with enhanced display like Imagify
        $optimized = get_post_meta($post->ID, '_xpm_optimized', true);
        $alt_generated = get_post_meta($post->ID, '_xpm_alt_text_generated', true);
        $keywords_used = get_post_meta($post->ID, '_xpm_keywords_used', true);
        
        // Get optimization details
        $original_size = get_post_meta($post->ID, '_xpm_original_size', true);
        $optimized_size = get_post_meta($post->ID, '_xpm_optimized_size', true);
        $bytes_saved = get_post_meta($post->ID, '_xpm_bytes_saved', true);
        $resized = get_post_meta($post->ID, '_xpm_resized', true);
        $webp_path = get_post_meta($post->ID, '_xpm_webp_path', true);
        $backup_path = get_post_meta($post->ID, '_xpm_backup_path', true);
        $optimization_date = get_post_meta($post->ID, '_xpm_optimization_date', true);
        
        // Get current file size
        $file_path = get_attached_file($post->ID);
        $current_size = $file_path && file_exists($file_path) ? filesize($file_path) : 0;
        
        $status_html = '<div class="xpm-attachment-status" style="border: 1px solid #ddd; padding: 15px; border-radius: 6px; background: #f9f9f9;">';
        $status_html .= '<h4 style="margin-top: 0; color: #0073aa;">XPM Image SEO</h4>';
        
        // Alt text status
        if ($alt_generated) {
            $status_html .= '<div style="margin-bottom: 15px; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">';
            $status_html .= '<p style="margin: 0;"><strong style="color: #155724;">✓ ' . __('Alt Text: AI Generated', 'xpm-image-seo') . '</strong></p>';
            if ($keywords_used) {
                $keywords_display = is_array($keywords_used) ? implode(', ', $keywords_used) : $keywords_used;
                $status_html .= '<p style="margin: 5px 0 0 0; font-size: 12px;"><strong>' . __('Keywords Used:', 'xpm-image-seo') . '</strong> ' . esc_html($keywords_display) . '</p>';
            }
            $status_html .= '</div>';
        }
        
        // Optimization status with Imagify-style display
        if ($optimized) {
            $savings_percent = $original_size > 0 ? round(($bytes_saved / $original_size) * 100, 1) : 0;
            
            $status_html .= '<div style="margin-bottom: 15px; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">';
            $status_html .= '<p style="margin: 0;"><strong style="color: #155724;">✓ ' . __('Optimized', 'xpm-image-seo') . '</strong></p>';
            
            // New Filesize (like Imagify)
            $status_html .= '<ul style="margin: 10px 0; padding-left: 20px;">';
            $status_html .= '<li><strong>' . __('New Filesize:', 'xpm-image-seo') . '</strong> ' . size_format($current_size) . '</li>';
            
            if ($savings_percent > 0) {
                $status_html .= '<li><strong>' . __('Original Saving:', 'xpm-image-seo') . '</strong> ' . $savings_percent . '%</li>';
            }
            $status_html .= '</ul>';
            
            // Collapsible details section (like Imagify)
            $status_html .= '<details style="margin-top: 10px;">';
            $status_html .= '<summary style="cursor: pointer; font-weight: bold; color: #0073aa;">' . __('View Details', 'xpm-image-seo') . '</summary>';
            $status_html .= '<div style="margin-top: 10px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">';
            
            // Original filesize
            if ($original_size) {
                $status_html .= '<p><strong>' . __('Original Filesize:', 'xpm-image-seo') . '</strong> ' . size_format($original_size) . '</p>';
            }
            
            // Compression level
            $options = get_option('xpm_image_seo_settings');
            $quality = isset($options['compression_quality']) ? $options['compression_quality'] : 85;
            $level = $quality >= 90 ? __('Lossless', 'xpm-image-seo') : ($quality >= 80 ? __('Glossy', 'xpm-image-seo') : __('Lossy', 'xpm-image-seo'));
            $status_html .= '<p><strong>' . __('Level:', 'xpm-image-seo') . '</strong> ' . $level . ' (' . $quality . '%)</p>';
            
            // Next-Gen generated (WebP)
            $webp_status = $webp_path && file_exists($webp_path) ? __('Yes', 'xpm-image-seo') : __('No', 'xpm-image-seo');
            $status_html .= '<p><strong>' . __('Next-Gen generated:', 'xpm-image-seo') . '</strong> ' . $webp_status . '</p>';
            
            // Thumbnails optimized
            $image_meta = wp_get_attachment_metadata($post->ID);
            $thumbnails_count = isset($image_meta['sizes']) ? count($image_meta['sizes']) : 0;
            $status_html .= '<p><strong>' . __('Thumbnails Optimized:', 'xpm-image-seo') . '</strong> ' . $thumbnails_count . '</p>';
            
            // Overall saving
            $status_html .= '<p><strong>' . __('Overall Saving:', 'xpm-image-seo') . '</strong> ' . $savings_percent . '%</p>';
            
            // Optimization date
            if ($optimization_date) {
                $status_html .= '<p><strong>' . __('Optimized on:', 'xpm-image-seo') . '</strong> ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($optimization_date)) . '</p>';
            }
            
            $status_html .= '</div>';
            $status_html .= '</details>';
            
            // Action buttons (like Imagify)
            $status_html .= '<div style="margin-top: 15px;">';
            $status_html .= '<button type="button" class="button xpm-reoptimize" data-attachment-id="' . $post->ID . '" style="margin-right: 10px;">' . __('Re-Optimize', 'xpm-image-seo') . '</button>';
            
            if ($backup_path && file_exists($backup_path)) {
                $status_html .= '<button type="button" class="button xpm-restore-original" data-attachment-id="' . $post->ID . '">' . __('Restore Original', 'xpm-image-seo') . '</button>';
            }
            $status_html .= '</div>';
            
            $status_html .= '</div>';
        } else {
            // Not optimized
            $status_html .= '<div style="margin-bottom: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">';
            $status_html .= '<p style="margin: 0;"><strong style="color: #856404;">⏳ ' . __('Not Optimized', 'xpm-image-seo') . '</strong></p>';
            if ($current_size) {
                $status_html .= '<p style="margin: 5px 0 0 0;"><strong>' . __('Current Filesize:', 'xpm-image-seo') . '</strong> ' . size_format($current_size) . '</p>';
            }
            $status_html .= '</div>';
        }
        
        $status_html .= '</div>';
        
        $fields['xpm_status'] = array(
            'label' => __('XPM Image SEO Status', 'xpm-image-seo'),
            'input' => 'html',
            'html' => $status_html
        );
        
        // Quick action buttons with FIXED JavaScript
        $actions_html = '<div class="xpm-quick-actions">';
        $actions_html .= '<style>.xpm-quick-actions button { margin-right: 10px; margin-bottom: 5px; } .xpm-processing { opacity: 0.6; }</style>';
        
        if (!$alt_generated) {
            $actions_html .= '<button type="button" class="button xpm-generate-alt" data-attachment-id="' . $post->ID . '">' . __('Generate Alt Text', 'xpm-image-seo') . '</button>';
        }
        
        if (!$optimized) {
            $actions_html .= '<button type="button" class="button xpm-optimize-image" data-attachment-id="' . $post->ID . '">' . __('Optimize Image', 'xpm-image-seo') . '</button>';
        }
        
        $actions_html .= '</div>';
        
        if (!$alt_generated || !$optimized) {
            $fields['xpm_actions'] = array(
                'label' => __('Quick Actions', 'xpm-image-seo'),
                'input' => 'html',
                'html' => $actions_html
            );
        }
        
        return $fields;
    }
    
    /**
     * Save attachment fields (placeholder for future use)
     */
    public function save_attachment_fields($post, $attachment) {
        // This method can be used for saving custom attachment fields in the future
        return $post;
    }
}