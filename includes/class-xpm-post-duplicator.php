<?php
/**
 * XPM Image SEO Post Duplicator
 * 
 * @package XPM_Image_SEO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle post/page duplication for all post types including Elementor support
 */
class XPM_Post_Duplicator {
    
    public function __construct() {
        add_action('admin_action_xmp_duplicate_post', array($this, 'duplicate_post'));
        add_filter('post_row_actions', array($this, 'add_duplicate_link'), 10, 2);
        add_filter('page_row_actions', array($this, 'add_duplicate_link'), 10, 2);
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handler for duplicating posts
        add_action('wp_ajax_xmp_duplicate_post_ajax', array($this, 'ajax_duplicate_post'));
        
        // Add bulk action
        add_filter('bulk_actions-edit-post', array($this, 'add_bulk_duplicate_action'));
        add_filter('bulk_actions-edit-page', array($this, 'add_bulk_duplicate_action'));
        add_filter('handle_bulk_actions-edit-post', array($this, 'handle_bulk_duplicate'), 10, 3);
        add_filter('handle_bulk_actions-edit-page', array($this, 'handle_bulk_duplicate'), 10, 3);
        
        // Add support for all custom post types
        add_action('init', array($this, 'add_custom_post_type_support'), 99);
    }
    
    /**
     * Add support for all custom post types
     */
    public function add_custom_post_type_support() {
        $post_types = get_post_types(array('public' => true), 'names');
        
        foreach ($post_types as $post_type) {
            if ($post_type !== 'attachment') {
                add_filter("bulk_actions-edit-{$post_type}", array($this, 'add_bulk_duplicate_action'));
                add_filter("handle_bulk_actions-edit-{$post_type}", array($this, 'handle_bulk_duplicate'), 10, 3);
                add_filter("{$post_type}_row_actions", array($this, 'add_duplicate_link'), 10, 2);
            }
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if (in_array($hook, array('edit.php', 'post.php'))) {
            wp_add_inline_style('admin-menu', '
                .duplicate-post-link {
                    color: #0073aa;
                    text-decoration: none;
                }
                .duplicate-post-link:hover {
                    color: #005a87;
                }
                .duplicate-post-link .dashicons {
                    margin-right: 3px;
                    font-size: 16px;
                    vertical-align: middle;
                }
                .xmp-duplicating {
                    opacity: 0.6;
                    pointer-events: none;
                }
            ');
            
            // Add JavaScript for AJAX duplication
            wp_add_inline_script('jquery', '
                jQuery(document).ready(function($) {
                    $(document).on("click", ".duplicate-post-ajax", function(e) {
                        e.preventDefault();
                        
                        var $link = $(this);
                        var postId = $link.data("post-id");
                        var $row = $link.closest("tr");
                        
                        $row.addClass("xmp-duplicating");
                        $link.html("<span class=\"dashicons dashicons-update spin\"></span>" + "' . __('Duplicating...', 'xpm-image-seo') . '");
                        
                        $.ajax({
                            url: ajaxurl,
                            type: "POST",
                            data: {
                                action: "xmp_duplicate_post_ajax",
                                post_id: postId,
                                nonce: "' . wp_create_nonce('xmp_duplicate_nonce') . '"
                            },
                            success: function(response) {
                                if (response.success) {
                                    location.reload();
                                } else {
                                    alert("Error: " + response.data);
                                    $row.removeClass("xmp-duplicating");
                                    $link.html("<span class=\"dashicons dashicons-admin-page\"></span>' . __('Duplicate', 'xpm-image-seo') . '");
                                }
                            },
                            error: function() {
                                alert("' . __('Network error occurred', 'xpm-image-seo') . '");
                                $row.removeClass("xmp-duplicating");
                                $link.html("<span class=\"dashicons dashicons-admin-page\"></span>' . __('Duplicate', 'xpm-image-seo') . '");
                            }
                        });
                    });
                });
            ');
        }
    }
    
    /**
     * Add duplicate link to post row actions
     */
    public function add_duplicate_link($actions, $post) {
        if (!$this->user_can_duplicate($post)) {
            return $actions;
        }
        
        // Regular duplicate link
        $duplicate_url = wp_nonce_url(
            admin_url('admin.php?action=xmp_duplicate_post&post=' . $post->ID),
            'xmp_duplicate_post_' . $post->ID
        );
        
        $actions['duplicate'] = sprintf(
            '<a href="%s" class="duplicate-post-link" title="%s"><span class="dashicons dashicons-admin-page"></span>%s</a>',
            esc_url($duplicate_url),
            esc_attr__('Duplicate this item', 'xpm-image-seo'),
            __('Duplicate', 'xpm-image-seo')
        );
        
        return $actions;
    }
    
    /**
     * Add bulk duplicate action
     */
    public function add_bulk_duplicate_action($actions) {
        $actions['xmp_duplicate'] = __('Duplicate', 'xpm-image-seo');
        return $actions;
    }
    
    /**
     * Handle bulk duplicate action
     */
    public function handle_bulk_duplicate($redirect_to, $action, $post_ids) {
        if ($action !== 'xmp_duplicate') {
            return $redirect_to;
        }
        
        $duplicated_count = 0;
        
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if ($post && $this->user_can_duplicate($post)) {
                $duplicated_post = $this->duplicate_post_data($post);
                if ($duplicated_post) {
                    $duplicated_count++;
                }
            }
        }
        
        $redirect_to = add_query_arg(array(
            'xmp_duplicated' => $duplicated_count
        ), $redirect_to);
        
        return $redirect_to;
    }
    
    /**
     * Main duplicate post action
     */
    public function duplicate_post() {
        if (!isset($_GET['post']) || !isset($_GET['_wpnonce'])) {
            wp_die(__('Invalid request', 'xpm-image-seo'));
        }
        
        $post_id = intval($_GET['post']);
        $nonce = $_GET['_wpnonce'];
        
        if (!wp_verify_nonce($nonce, 'xmp_duplicate_post_' . $post_id)) {
            wp_die(__('Security check failed', 'xpm-image-seo'));
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_die(__('Post not found', 'xpm-image-seo'));
        }
        
        if (!$this->user_can_duplicate($post)) {
            wp_die(__('You do not have permission to duplicate this item', 'xpm-image-seo'));
        }
        
        $duplicated_post = $this->duplicate_post_data($post);
        
        if ($duplicated_post) {
            $redirect_url = add_query_arg(array(
                'xmp_duplicated' => 1,
                'post_type' => $post->post_type
            ), admin_url('edit.php'));
            
            wp_redirect($redirect_url);
            exit;
        } else {
            wp_die(__('Failed to duplicate the item', 'xpm-image-seo'));
        }
    }
    
    /**
     * Check if user can duplicate post
     */
    private function user_can_duplicate($post) {
        $post_type_object = get_post_type_object($post->post_type);
        
        if (!$post_type_object) {
            return false;
        }
        
        return current_user_can($post_type_object->cap->edit_posts);
    }
    
    /**
     * Duplicate post data with full support for custom fields and Elementor
     */
    public function duplicate_post_data($original_post) {
        global $wpdb;
        
        // Get settings
        $options = get_option('xpm_image_seo_settings', array());
        $duplicate_status = isset($options['duplicate_status']) ? $options['duplicate_status'] : 'draft';
        $duplicate_author = isset($options['duplicate_author']) ? $options['duplicate_author'] : 'current';
        
        // Determine author
        $author_id = ($duplicate_author === 'original') ? $original_post->post_author : get_current_user_id();
        
        // Prepare new post data
        $new_post_data = array(
            'post_title' => $this->get_duplicate_title($original_post->post_title, $original_post->post_type),
            'post_content' => $original_post->post_content,
            'post_excerpt' => $original_post->post_excerpt,
            'post_type' => $original_post->post_type,
            'post_status' => $duplicate_status,
            'post_author' => $author_id,
            'post_parent' => $original_post->post_parent,
            'menu_order' => $original_post->menu_order,
            'comment_status' => $original_post->comment_status,
            'ping_status' => $original_post->ping_status,
            'post_password' => $original_post->post_password,
        );
        
        // Insert the new post
        $new_post_id = wp_insert_post($new_post_data);
        
        if (is_wp_error($new_post_id) || !$new_post_id) {
            return false;
        }
        
        // Duplicate all meta data
        $this->duplicate_post_meta($original_post->ID, $new_post_id);
        
        // Duplicate taxonomies
        $this->duplicate_post_taxonomies($original_post->ID, $new_post_id);
        
        // Handle Elementor data specifically
        $this->duplicate_elementor_data($original_post->ID, $new_post_id);
        
        // Handle other page builders
        $this->duplicate_page_builder_data($original_post->ID, $new_post_id);
        
        // Handle featured image
        $this->duplicate_featured_image($original_post->ID, $new_post_id);
        
        // Fire action for custom extensions
        do_action('xmp_post_duplicated', $new_post_id, $original_post->ID);
        
        return $new_post_id;
    }
    
    /**
     * Generate unique title for duplicate
     */
    private function get_duplicate_title($original_title, $post_type) {
        $options = get_option('xpm_image_seo_settings', array());
        $suffix = isset($options['duplicate_suffix']) ? $options['duplicate_suffix'] : 'Copy';
        
        $new_title = $original_title . ' (' . $suffix . ')';
        
        // Check if title already exists and add number if needed
        $counter = 1;
        while ($this->title_exists($new_title, $post_type)) {
            $counter++;
            $new_title = $original_title . ' (' . $suffix . ' ' . $counter . ')';
        }
        
        return $new_title;
    }
    
    /**
     * Check if title exists
     */
    private function title_exists($title, $post_type) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = %s AND post_status != 'trash'",
            $title,
            $post_type
        );
        
        return $wpdb->get_var($query) ? true : false;
    }
    
    /**
     * Duplicate all post meta data
     */
    private function duplicate_post_meta($original_id, $new_id) {
        global $wpdb;
        
        // Get all meta data
        $meta_data = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
            $original_id
        ));
        
        $excluded_meta_keys = array(
            '_edit_lock',
            '_edit_last',
            '_wp_old_slug',
            '_wp_old_date',
        );
        
        foreach ($meta_data as $meta) {
            // Skip excluded meta keys
            if (in_array($meta->meta_key, $excluded_meta_keys)) {
                continue;
            }
            
            // Handle serialized data properly
            $meta_value = maybe_unserialize($meta->meta_value);
            add_post_meta($new_id, $meta->meta_key, $meta_value);
        }
        
        // Handle specific meta keys that need special treatment
        $this->handle_special_meta_keys($original_id, $new_id);
    }
    
    /**
     * Handle special meta keys that need custom processing
     */
    private function handle_special_meta_keys($original_id, $new_id) {
        // Handle ACF fields
        if (function_exists('get_fields')) {
            $fields = get_fields($original_id);
            if ($fields) {
                foreach ($fields as $field_name => $field_value) {
                    update_field($field_name, $field_value, $new_id);
                }
            }
        }
        
        // Handle Custom Fields Suite
        if (function_exists('CFS')) {
            $cfs_fields = CFS()->get(false, $original_id);
            if ($cfs_fields) {
                foreach ($cfs_fields as $field_name => $field_value) {
                    CFS()->save(array($field_name => $field_value), array('ID' => $new_id));
                }
            }
        }
        
        // Handle Meta Box fields
        if (function_exists('rwmb_meta')) {
            $meta_boxes = rwmb_get_registry('meta_box')->all();
            foreach ($meta_boxes as $meta_box) {
                if (isset($meta_box->post_types) && in_array(get_post_type($original_id), $meta_box->post_types)) {
                    foreach ($meta_box->fields as $field) {
                        $value = rwmb_meta($field['id'], '', $original_id);
                        if ($value) {
                            update_post_meta($new_id, $field['id'], $value);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Duplicate post taxonomies
     */
    private function duplicate_post_taxonomies($original_id, $new_id) {
        $taxonomies = get_object_taxonomies(get_post_type($original_id));
        
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($original_id, $taxonomy, array('fields' => 'slugs'));
            
            if (!empty($terms) && !is_wp_error($terms)) {
                wp_set_object_terms($new_id, $terms, $taxonomy);
            }
        }
    }
    
    /**
     * Duplicate Elementor data specifically
     */
    private function duplicate_elementor_data($original_id, $new_id) {
        // Check if Elementor is active
        if (!defined('ELEMENTOR_VERSION')) {
            return;
        }
        
        // Duplicate Elementor data
        $elementor_data = get_post_meta($original_id, '_elementor_data', true);
        if ($elementor_data) {
            update_post_meta($new_id, '_elementor_data', $elementor_data);
        }
        
        // Duplicate Elementor edit mode
        $elementor_edit_mode = get_post_meta($original_id, '_elementor_edit_mode', true);
        if ($elementor_edit_mode) {
            update_post_meta($new_id, '_elementor_edit_mode', $elementor_edit_mode);
        }
        
        // Duplicate Elementor template type
        $elementor_template_type = get_post_meta($original_id, '_elementor_template_type', true);
        if ($elementor_template_type) {
            update_post_meta($new_id, '_elementor_template_type', $elementor_template_type);
        }
        
        // Duplicate Elementor version
        $elementor_version = get_post_meta($original_id, '_elementor_version', true);
        if ($elementor_version) {
            update_post_meta($new_id, '_elementor_version', $elementor_version);
        }
        
        // Duplicate Elementor Pro version if exists
        $elementor_pro_version = get_post_meta($original_id, '_elementor_pro_version', true);
        if ($elementor_pro_version) {
            update_post_meta($new_id, '_elementor_pro_version', $elementor_pro_version);
        }
        
        // Duplicate Elementor CSS
        $elementor_css = get_post_meta($original_id, '_elementor_css', true);
        if ($elementor_css) {
            update_post_meta($new_id, '_elementor_css', $elementor_css);
        }
        
        // Duplicate page settings
        $elementor_page_settings = get_post_meta($original_id, '_elementor_page_settings', true);
        if ($elementor_page_settings) {
            update_post_meta($new_id, '_elementor_page_settings', $elementor_page_settings);
        }
        
        // Clear Elementor cache for the new post
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
    }
    
    /**
     * Duplicate other page builder data
     */
    private function duplicate_page_builder_data($original_id, $new_id) {
        // Beaver Builder
        if (class_exists('FLBuilder')) {
            $bb_enabled = get_post_meta($original_id, '_fl_builder_enabled', true);
            if ($bb_enabled) {
                update_post_meta($new_id, '_fl_builder_enabled', $bb_enabled);
                
                $bb_data = get_post_meta($original_id, '_fl_builder_data', true);
                if ($bb_data) {
                    update_post_meta($new_id, '_fl_builder_data', $bb_data);
                }
                
                $bb_draft = get_post_meta($original_id, '_fl_builder_draft', true);
                if ($bb_draft) {
                    update_post_meta($new_id, '_fl_builder_draft', $bb_draft);
                }
            }
        }
        
        // Divi Builder
        if (function_exists('et_pb_is_pagebuilder_used')) {
            $divi_enabled = get_post_meta($original_id, '_et_pb_use_builder', true);
            if ($divi_enabled) {
                update_post_meta($new_id, '_et_pb_use_builder', $divi_enabled);
                
                $divi_old_content = get_post_meta($original_id, '_et_pb_old_content', true);
                if ($divi_old_content) {
                    update_post_meta($new_id, '_et_pb_old_content', $divi_old_content);
                }
            }
        }
        
        // Visual Composer
        if (defined('WPB_VC_VERSION')) {
            $vc_data = get_post_meta($original_id, '_wpb_vc_js_status', true);
            if ($vc_data) {
                update_post_meta($new_id, '_wpb_vc_js_status', $vc_data);
            }
        }
        
        // Gutenberg blocks
        if (function_exists('has_blocks')) {
            $post_content = get_post_field('post_content', $original_id);
            if (has_blocks($post_content)) {
                // Blocks are already copied with post_content, but we might need to handle block metadata
                $blocks_meta = get_post_meta($original_id, '_blocks_meta', true);
                if ($blocks_meta) {
                    update_post_meta($new_id, '_blocks_meta', $blocks_meta);
                }
            }
        }
    }
    
    /**
     * Duplicate featured image
     */
    private function duplicate_featured_image($original_id, $new_id) {
        $thumbnail_id = get_post_thumbnail_id($original_id);
        if ($thumbnail_id) {
            set_post_thumbnail($new_id, $thumbnail_id);
        }
    }
    
    /**
     * Admin notices for duplication results
     */
    public function admin_notices() {
        if (isset($_GET['xmp_duplicated'])) {
            $count = intval($_GET['xmp_duplicated']);
            
            if ($count > 0) {
                $message = sprintf(
                    _n(
                        '%d item has been successfully duplicated.',
                        '%d items have been successfully duplicated.',
                        $count,
                        'xpm-image-seo'
                    ),
                    $count
                );
                
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>XMP Duplicator:</strong> ' . esc_html($message) . '</p>';
                echo '</div>';
            }
        }
    }
    
    /**
     * Get duplicate statistics for dashboard
     */
    public function get_duplicate_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Get recent duplications (posts with "Copy" in title created in last 30 days)
        $recent_duplicates = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_title LIKE '%Copy%' 
            AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND post_status != 'trash'
        ");
        
        $stats['recent_duplicates'] = intval($recent_duplicates);
        
        return $stats;
    }
    
    /**
     * Duplicate posts via AJAX for better UX
     */
    public function ajax_duplicate_post() {
        check_ajax_referer('xmp_duplicate_nonce', 'nonce');
        
        if (!isset($_POST['post_id'])) {
            wp_send_json_error(__('Invalid post ID', 'xpm-image-seo'));
        }
        
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post || !$this->user_can_duplicate($post)) {
            wp_send_json_error(__('Permission denied', 'xpm-image-seo'));
        }
        
        $duplicated_post_id = $this->duplicate_post_data($post);
        
        if ($duplicated_post_id) {
            wp_send_json_success(array(
                'message' => __('Post duplicated successfully', 'xpm-image-seo'),
                'edit_link' => get_edit_post_link($duplicated_post_id, 'raw'),
                'new_post_id' => $duplicated_post_id
            ));
        } else {
            wp_send_json_error(__('Failed to duplicate post', 'xpm-image-seo'));
        }
    }
}