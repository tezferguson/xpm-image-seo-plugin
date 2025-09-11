<?php
/**
 * XPM Image SEO Admin Interface - COMPLETE FIXED VERSION WITH PNG TO JPEG CONVERSION
 * 
 * @package XPM_Image_SEO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle admin interface, settings, and pages
 */
class XPM_Image_SEO_Admin {
    
    private $option_name = 'xpm_image_seo_settings';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Handle lazy loading frontend
        $options = get_option($this->option_name);
        if (!empty($options['enable_lazy_loading'])) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_lazy_loading_scripts'));
            add_filter('the_content', array($this, 'add_lazy_loading_to_images'));
            add_filter('post_thumbnail_html', array($this, 'add_lazy_loading_to_thumbnails'), 10, 5);
        }
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Main settings page
        add_options_page(
            __('XPM Image SEO Settings', 'xpm-image-seo'),
            __('XPM Image SEO', 'xpm-image-seo'),
            'manage_options',
            'xpm-image-seo-settings',
            array($this, 'display_settings_page')
        );
        
        // Bulk update page
        add_submenu_page(
            'upload.php',
            __('XPM Bulk Alt Text Update', 'xpm-image-seo'),
            __('Bulk Alt Text Update', 'xpm-image-seo'),
            'upload_files',
            'xpm-image-seo-bulk-update',
            array($this, 'display_bulk_update_page')
        );
        
        // Image optimizer page
        add_submenu_page(
            'upload.php',
            __('XPM Image Optimizer', 'xpm-image-seo'),
            __('Image Optimizer', 'xpm-image-seo'),
            'upload_files',
            'xpm-image-seo-optimizer',
            array($this, 'display_optimizer_page')
        );
    }
    
    /**
     * Initialize admin settings
     */
    public function admin_init() {
        register_setting('xpm_image_seo_settings', $this->option_name, array($this, 'sanitize_settings'));
    }
    
    /**
     * Sanitize settings - FIXED VERSION WITH COMPLETE VALIDATION INCLUDING PNG TO JPEG
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Get existing options to preserve values from other tabs
        $existing_options = get_option($this->option_name, array());
        $sanitized = $existing_options;
        
        // Alt Text settings
        if (isset($input['api_key'])) {
            $api_key = sanitize_text_field($input['api_key']);
            // Validate OpenAI API key format
            if (empty($api_key) || preg_match('/^sk-[a-zA-Z0-9]{48,}$/', $api_key)) {
                $sanitized['api_key'] = $api_key;
            } else {
                add_settings_error('xpm_image_seo_settings', 'invalid_api_key', 
                    __('Invalid OpenAI API key format. Please check your key.', 'xpm-image-seo'));
                $sanitized['api_key'] = $existing_options['api_key'] ?? '';
            }
        }
        
        if (isset($input['global_keywords'])) {
            $sanitized['global_keywords'] = sanitize_textarea_field($input['global_keywords']);
        }
        
        if (isset($input['use_contextual_keywords'])) {
            $sanitized['use_contextual_keywords'] = intval($input['use_contextual_keywords']);
        }
        
        if (isset($input['keyword_priority'])) {
            $allowed_priorities = array('contextual_first', 'global_first', 'mixed');
            $sanitized['keyword_priority'] = in_array($input['keyword_priority'], $allowed_priorities) ? 
                $input['keyword_priority'] : 'contextual_first';
        }
        
        if (isset($input['max_keywords'])) {
            $sanitized['max_keywords'] = max(1, min(10, intval($input['max_keywords'])));
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
        
        // NEW: PNG to JPEG conversion setting
        if (isset($input['convert_png_to_jpeg'])) {
            $sanitized['convert_png_to_jpeg'] = intval($input['convert_png_to_jpeg']);
        }
        
        // Performance settings
        if (isset($input['enable_lazy_loading'])) {
            $sanitized['enable_lazy_loading'] = intval($input['enable_lazy_loading']);
        }
        
        if (isset($input['lazy_loading_threshold'])) {
            $sanitized['lazy_loading_threshold'] = max(0, min(1000, intval($input['lazy_loading_threshold'])));
        }
        
        if (isset($input['lazy_loading_placeholder'])) {
            $allowed_placeholders = array('blur', 'grey', 'transparent', 'custom');
            $sanitized['lazy_loading_placeholder'] = in_array($input['lazy_loading_placeholder'], $allowed_placeholders) ? 
                $input['lazy_loading_placeholder'] : 'blur';
        }
        
        if (isset($input['lazy_loading_custom_placeholder'])) {
            $sanitized['lazy_loading_custom_placeholder'] = esc_url_raw($input['lazy_loading_custom_placeholder']);
        }
        
        if (isset($input['lazy_loading_effect'])) {
            $allowed_effects = array('fade', 'slide', 'zoom', 'none');
            $sanitized['lazy_loading_effect'] = in_array($input['lazy_loading_effect'], $allowed_effects) ? 
                $input['lazy_loading_effect'] : 'fade';
        }
        
        // Post Duplicator settings
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
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'xpm-image-seo') === false) {
            return;
        }
        
        wp_enqueue_style(
            'xpm-admin-css',
            XPM_IMAGE_SEO_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            XPM_IMAGE_SEO_VERSION
        );
        
        wp_enqueue_script(
            'xpm-admin-js',
            XPM_IMAGE_SEO_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            XPM_IMAGE_SEO_VERSION,
            true
        );
        
        wp_localize_script('xpm-admin-js', 'xpmImageSeo', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('xpm_image_seo_nonce'),
            'current_page' => $hook,
            'strings' => array(
                'scanning' => __('Scanning...', 'xpm-image-seo'),
                'no_images' => __('No images to update', 'xpm-image-seo'),
                'stopped' => __('Bulk update stopped by user', 'xmp-image-seo'),
                'completed' => __('Bulk update completed!', 'xpm-image-seo'),
                'network_error' => __('Network error', 'xpm-image-seo'),
                'scan_failed' => __('Failed to scan images', 'xpm-image-seo'),
                'confirm_stop' => __('Are you sure you want to stop the bulk update?', 'xpm-image-seo'),
                'scan_button' => __('Scan for Images Without Alt Text', 'xpm-image-seo'),
                'scan_optimizer_button' => __('Scan for Unoptimized Images', 'xpm-image-seo'),
                'no_unoptimized' => __('No unoptimized images found', 'xpm-image-seo'),
                'confirm_optimize' => __('Start image optimization?', 'xpm-image-seo')
            )
        ));
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        if (get_transient('xpm_image_seo_activated')) {
            delete_transient('xpm_image_seo_activated');
            
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>' . __('XPM Image SEO activated!', 'xpm-image-seo') . '</strong> ';
            printf(__('Configure your OpenAI API key in %s to get started.', 'xpm-image-seo'), 
                '<a href="' . admin_url('options-general.php?page=xpm-image-seo-settings') . '">' . __('Settings', 'xpm-image-seo') . '</a>');
            echo '</p>';
            echo '</div>';
        }
        
        // Check if API key is missing
        $options = get_option($this->option_name);
        if (empty($options['api_key'])) {
            $screen = get_current_screen();
            if ($screen && strpos($screen->id, 'xpm-image-seo') !== false) {
                echo '<div class="notice notice-warning">';
                echo '<p><strong>' . __('XPM Image SEO:', 'xpm-image-seo') . '</strong> ';
                printf(__('Please configure your OpenAI API key in %s to enable alt text generation.', 'xpm-image-seo'),
                    '<a href="' . admin_url('options-general.php?page=xpm-image-seo-settings') . '">' . __('Settings', 'xpm-image-seo') . '</a>');
                echo '</p>';
                echo '</div>';
            }
        }
    }
    
    /**
     * Display settings page with enhanced tabs - COMPLETELY FIXED VERSION WITH PNG TO JPEG
     */
    public function display_settings_page() {
        $options = get_option($this->option_name);
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'alt_text';
        ?>
        <div class="wrap">
            <h1><?php _e('XPM Image SEO Settings', 'xpm-image-seo'); ?></h1>
            
            <div class="xpm-nav-tab-wrapper">
                <a href="#" class="nav-tab <?php echo $active_tab == 'alt_text' ? 'nav-tab-active' : ''; ?>" data-tab="alt_text">
                    <?php _e('Alt Text', 'xpm-image-seo'); ?>
                </a>
                <a href="#" class="nav-tab <?php echo $active_tab == 'optimization' ? 'nav-tab-active' : ''; ?>" data-tab="optimization">
                    <?php _e('Optimization', 'xpm-image-seo'); ?>
                </a>
                <a href="#" class="nav-tab <?php echo $active_tab == 'performance' ? 'nav-tab-active' : ''; ?>" data-tab="performance">
                    <?php _e('Performance', 'xpm-image-seo'); ?>
                </a>
                <a href="#" class="nav-tab <?php echo $active_tab == 'duplicator' ? 'nav-tab-active' : ''; ?>" data-tab="duplicator">
                    <?php _e('Post Duplicator', 'xpm-image-seo'); ?>
                </a>
            </div>
            
            <form method="post" action="options.php" id="xpm-settings-form">
                <?php settings_fields('xpm_image_seo_settings'); ?>
                
                <!-- Alt Text Tab -->
                <div id="alt_text_content" class="xpm-tab-content <?php echo $active_tab == 'alt_text' ? 'active' : ''; ?>">
                    <h2><?php _e('Alt Text Generation Settings', 'xpm-image-seo'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('OpenAI API Key', 'xpm-image-seo'); ?></th>
                            <td>
                                <input type="password" name="<?php echo $this->option_name; ?>[api_key]" 
                                       value="<?php echo esc_attr($options['api_key'] ?? ''); ?>" 
                                       class="regular-text" />
                                <p class="description">
                                    <?php printf(__('Get your API key from %s', 'xpm-image-seo'), 
                                        '<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a>'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Global Keywords', 'xpm-image-seo'); ?></th>
                            <td>
                                <textarea name="<?php echo $this->option_name; ?>[global_keywords]" 
                                          rows="3" cols="50" class="large-text"><?php echo esc_textarea($options['global_keywords'] ?? ''); ?></textarea>
                                <p class="description"><?php _e('Comma-separated keywords to include in alt text generation.', 'xpm-image-seo'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Use Contextual Keywords', 'xpm-image-seo'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[use_contextual_keywords]" 
                                           value="1" <?php checked($options['use_contextual_keywords'] ?? 1, 1); ?> />
                                    <?php _e('Extract keywords from page content where images are used.', 'xpm-image-seo'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Keyword Priority', 'xpm-image-seo'); ?></th>
                            <td>
                                <select name="<?php echo $this->option_name; ?>[keyword_priority]">
                                    <option value="contextual_first" <?php selected($options['keyword_priority'] ?? 'contextual_first', 'contextual_first'); ?>><?php _e('Contextual First', 'xpm-image-seo'); ?></option>
                                    <option value="global_first" <?php selected($options['keyword_priority'] ?? 'contextual_first', 'global_first'); ?>><?php _e('Global First', 'xpm-image-seo'); ?></option>
                                    <option value="mixed" <?php selected($options['keyword_priority'] ?? 'contextual_first', 'mixed'); ?>><?php _e('Mixed', 'xpm-image-seo'); ?></option>
                                </select>
                                <p class="description"><?php _e('How to prioritize keywords when both global and contextual are available.', 'xpm-image-seo'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Max Keywords', 'xpm-image-seo'); ?></th>
                            <td>
                                <input type="number" name="<?php echo $this->option_name; ?>[max_keywords]" 
                                       value="<?php echo esc_attr($options['max_keywords'] ?? 3); ?>" 
                                       min="1" max="10" class="small-text" />
                                <p class="description"><?php _e('Maximum number of keywords to use per image.', 'xpm-image-seo'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Auto-generate for new uploads', 'xpm-image-seo'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[auto_generate]" 
                                           value="1" <?php checked($options['auto_generate'] ?? 0, 1); ?> />
                                    <?php _e('Automatically generate alt text for new image uploads.', 'xpm-image-seo'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Custom Prompt', 'xpm-image-seo'); ?></th>
                            <td>
                                <textarea name="<?php echo $this->option_name; ?>[prompt]" 
                                          rows="4" cols="50" class="large-text"><?php echo esc_textarea($options['prompt'] ?? ''); ?></textarea>
                                <p class="description"><?php _e('Custom prompt for AI. Leave blank to use default SEO-optimized prompt.', 'xpm-image-seo'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Update Additional Fields', 'xpm-image-seo'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[update_title]" 
                                           value="1" <?php checked($options['update_title'] ?? 1, 1); ?> />
                                    <?php _e('Update image titles', 'xpm-image-seo'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[update_description]" 
                                           value="1" <?php checked($options['update_description'] ?? 1, 1); ?> />
                                    <?php _e('Update image descriptions', 'xpm-image-seo'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[skip_existing]" 
                                           value="1" <?php checked($options['skip_existing'] ?? 1, 1); ?> />
                                    <?php _e('Skip if fields already have content', 'xpm-image-seo'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Optimization Tab -->
                <div id="optimization_content" class="xpm-tab-content <?php echo $active_tab == 'optimization' ? 'active' : ''; ?>">
                    <h2><?php _e('Image Optimization Settings', 'xpm-image-seo'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Auto-optimize new uploads', 'xpm-image-seo'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[auto_optimize]" 
                                           value="1" <?php checked($options['auto_optimize'] ?? 1, 1); ?> />
                                    <?php _e('Automatically optimize images when uploaded.', 'xpm-image-seo'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Compression Quality', 'xpm-image-seo'); ?></th>
                            <td>
                                <input type="range" name="<?php echo $this->option_name; ?>[compression_quality]" 
                                       min="60" max="100" value="<?php echo esc_attr($options['compression_quality'] ?? 85); ?>" 
                                       class="compression-slider" />
                                <span class="quality-display"><?php echo esc_html($options['compression_quality'] ?? 85); ?>%</span>
                                <p class="description"><?php _e('Higher quality = larger file size. 85% is recommended for best balance.', 'xpm-image-seo'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Maximum Width', 'xpm-image-seo'); ?></th>
                            <td>
                                <input type="number" name="<?php echo $this->option_name; ?>[max_width]" 
                                       value="<?php echo esc_attr($options['max_width'] ?? 2048); ?>" 
                                       min="500" max="4000" class="small-text" />
                                <p class="description"><?php _e('Images wider than this will be resized. Set to 0 to disable.', 'xpm-image-seo'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Maximum Height', 'xpm-image-seo'); ?></th>
                            <td>
                                <input type="number" name="<?php echo $this->option_name; ?>[max_height]" 
                                       value="<?php echo esc_attr($options['max_height'] ?? 2048); ?>" 
                                       min="0" max="4000" class="small-text" />
                                <p class="description"><?php _e('Images taller than this will be resized. Set to 0 to disable height limit.', 'xpm-image-seo'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Backup Originals', 'xpm-image-seo'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[backup_originals]" 
                                           value="1" <?php checked($options['backup_originals'] ?? 1, 1); ?> />
                                    <?php _e('Create backups before optimization (recommended).', 'xpm-image-seo'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Convert to WebP', 'xpm-image-seo'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[convert_to_webp]" 
                                           value="1" <?php checked($options['convert_to_webp'] ?? 0, 1); ?> />
                                    <?php _e('Create WebP versions for better compression (if supported).', 'xpm-image-seo'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <!-- NEW: PNG to JPEG Conversion -->
                        <tr>
                            <th scope="row"><?php _e('PNG to JPEG Conversion', 'xpm-image-seo'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[convert_png_to_jpeg]" 
                                           value="1" <?php checked($options['convert_png_to_jpeg'] ?? 0, 1); ?> />
                                    <?php _e('Convert PNG images to high-quality JPEG for smaller file sizes.', 'xpm-image-seo'); ?>
                                </label>
                                <p class="description">
                                    <strong style="color: #d63638;"><?php _e('⚠️ Important:', 'xpm-image-seo'); ?></strong>
                                    <?php _e('Only PNGs without transparency will be converted. Images with transparent backgrounds will be automatically skipped to prevent visual issues on your website.', 'xpm-image-seo'); ?>
                                </p>
                                <p class="description">
                                    <?php _e('This can significantly reduce file sizes for photos and complex images. Original PNG files will be backed up before conversion.', 'xpm-image-seo'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Performance Tab -->
                <div id="performance_content" class="xpm-tab-content <?php echo $active_tab == 'performance' ? 'active' : ''; ?>">
                    <h2><?php _e('Performance Settings', 'xpm-image-seo'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable Lazy Loading', 'xpm-image-seo'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[enable_lazy_loading]" 
                                           value="1" <?php checked($options['enable_lazy_loading'] ?? 0, 1); ?> />
                                    <?php _e('Load images only when they come into view.', 'xpm-image-seo'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Loading Threshold', 'xpm-image-seo'); ?></th>
                            <td>
                                <input type="number" name="<?php echo $this->option_name; ?>[lazy_loading_threshold]" 
                                       value="<?php echo esc_attr($options['lazy_loading_threshold'] ?? 200); ?>" 
                                       min="0" max="1000" class="small-text" />
                                <p class="description"><?php _e('Distance in pixels before image loads (default: 200px).', 'xpm-image-seo'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Placeholder Type', 'xpm-image-seo'); ?></th>
                            <td>
                                <select name="<?php echo $this->option_name; ?>[lazy_loading_placeholder]">
                                    <option value="blur" <?php selected($options['lazy_loading_placeholder'] ?? 'blur', 'blur'); ?>><?php _e('Blur Effect', 'xpm-image-seo'); ?></option>
                                    <option value="grey" <?php selected($options['lazy_loading_placeholder'] ?? 'blur', 'grey'); ?>><?php _e('Grey Placeholder', 'xpm-image-seo'); ?></option>
                                    <option value="transparent" <?php selected($options['lazy_loading_placeholder'] ?? 'blur', 'transparent'); ?>><?php _e('Transparent', 'xpm-image-seo'); ?></option>
                                    <option value="custom" <?php selected($options['lazy_loading_placeholder'] ?? 'blur', 'custom'); ?>><?php _e('Custom Image', 'xpm-image-seo'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr id="custom-placeholder-field" style="<?php echo ($options['lazy_loading_placeholder'] ?? 'blur') == 'custom' ? '' : 'display: none;'; ?>">
                            <th scope="row"><?php _e('Custom Placeholder URL', 'xpm-image-seo'); ?></th>
                            <td>
                                <input type="url" name="<?php echo $this->option_name; ?>[lazy_loading_custom_placeholder]" 
                                       value="<?php echo esc_attr($options['lazy_loading_custom_placeholder'] ?? ''); ?>" 
                                       class="regular-text" />
                                <p class="description"><?php _e('URL to custom placeholder image.', 'xpm-image-seo'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Loading Effect', 'xpm-image-seo'); ?></th>
                            <td>
                                <select name="<?php echo $this->option_name; ?>[lazy_loading_effect]">
                                    <option value="fade" <?php selected($options['lazy_loading_effect'] ?? 'fade', 'fade'); ?>><?php _e('Fade', 'xpm-image-seo'); ?></option>
                                    <option value="slide" <?php selected($options['lazy_loading_effect'] ?? 'fade', 'slide'); ?>><?php _e('Slide', 'xpm-image-seo'); ?></option>
                                    <option value="zoom" <?php selected($options['lazy_loading_effect'] ?? 'fade', 'zoom'); ?>><?php _e('Zoom', 'xpm-image-seo'); ?></option>
                                    <option value="none" <?php selected($options['lazy_loading_effect'] ?? 'fade', 'none'); ?>><?php _e('None', 'xpm-image-seo'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Post Duplicator Tab -->
                <div id="duplicator_content" class="xpm-tab-content <?php echo $active_tab == 'duplicator' ? 'active' : ''; ?>">
                    <h2><?php _e('Post Duplicator Settings', 'xpm-image-seo'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable Post Duplicator', 'xpm-image-seo'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[enable_post_duplicator]" 
                                           value="1" <?php checked($options['enable_post_duplicator'] ?? 1, 1); ?> />
                                    <?php _e('Add duplicate functionality to posts, pages, and custom post types.', 'xpm-image-seo'); ?>
                                </label>
                                <p class="description"><?php _e('Includes full Elementor support and all custom fields.', 'xpm-image-seo'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Default Status for Duplicates', 'xpm-image-seo'); ?></th>
                            <td>
                                <select name="<?php echo $this->option_name; ?>[duplicate_status]">
                                    <option value="draft" <?php selected($options['duplicate_status'] ?? 'draft', 'draft'); ?>><?php _e('Draft', 'xpm-image-seo'); ?></option>
                                    <option value="pending" <?php selected($options['duplicate_status'] ?? 'draft', 'pending'); ?>><?php _e('Pending Review', 'xpm-image-seo'); ?></option>
                                    <option value="private" <?php selected($options['duplicate_status'] ?? 'draft', 'private'); ?>><?php _e('Private', 'xpm-image-seo'); ?></option>
                                </select>
                                <p class="description"><?php _e('Status assigned to duplicated posts.', 'xpm-image-seo'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Duplicate Author', 'xpm-image-seo'); ?></th>
                            <td>
                                <select name="<?php echo $this->option_name; ?>[duplicate_author]">
                                    <option value="current" <?php selected($options['duplicate_author'] ?? 'current', 'current'); ?>><?php _e('Current User', 'xpm-image-seo'); ?></option>
                                    <option value="original" <?php selected($options['duplicate_author'] ?? 'current', 'original'); ?>><?php _e('Original Author', 'xpm-image-seo'); ?></option>
                                </select>
                                <p class="description"><?php _e('Who should be set as the author of duplicated posts.', 'xpm-image-seo'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Title Suffix', 'xpm-image-seo'); ?></th>
                            <td>
                                <input type="text" name="<?php echo $this->option_name; ?>[duplicate_suffix]" 
                                       value="<?php echo esc_attr($options['duplicate_suffix'] ?? 'Copy'); ?>" 
                                       class="regular-text" />
                                <p class="description"><?php _e('Text added to duplicate titles (e.g., "Original Title (Copy)").', 'xpm-image-seo'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="xpm-info-box">
                        <h3><?php _e('Duplicator Features', 'xpm-image-seo'); ?></h3>
                        <ul>
                            <li><?php _e('✅ Complete Elementor support (designs, widgets, settings)', 'xpm-image-seo'); ?></li>
                            <li><?php _e('✅ Advanced Custom Fields (ACF) duplication', 'xpm-image-seo'); ?></li>
                            <li><?php _e('✅ All custom post types supported', 'xpm-image-seo'); ?></li>
                            <li><?php _e('✅ Meta Box and Custom Fields Suite support', 'xpm-image-seo'); ?></li>
                            <li><?php _e('✅ Taxonomies and featured images duplicated', 'xpm-image-seo'); ?></li>
                            <li><?php _e('✅ Bulk duplication with progress tracking', 'xpm-image-seo'); ?></li>
                        </ul>
                    </div>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <!-- CRITICAL: Force CSS and JavaScript to ensure tabs work -->
        <style type="text/css">
            /* Critical tab CSS - Force working tabs */
            .xpm-tab-content { 
                display: none !important; 
                visibility: hidden !important;
                opacity: 0;
            }
            .xpm-tab-content.active { 
                display: block !important; 
                visibility: visible !important;
                opacity: 1;
            }
            
            /* Debug helper */
            .xpm-debug {
                border: 2px solid red !important;
                background: yellow !important;
            }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('=== XPM SETTINGS PAGE DEBUG START ===');
            
            // Force hide all tabs except active on load
            $('.xpm-tab-content').each(function(index) {
                var $tab = $(this);
                var tabId = $tab.attr('id');
                var isActive = $tab.hasClass('active');
                
                console.log('Tab ' + index + ': ' + tabId + ' - Active: ' + isActive);
                
                if (!isActive) {
                    $tab.hide().css({
                        'display': 'none',
                        'visibility': 'hidden',
                        'opacity': '0'
                    });
                } else {
                    $tab.show().css({
                        'display': 'block',
                        'visibility': 'visible',
                        'opacity': '1'
                    });
                }
            });
            
            console.log('Settings page loaded, active tab should be visible');
            console.log('Active tab: <?php echo esc_js($active_tab); ?>');
            console.log('Found tabs: ' + $('.xpm-tab-content').length);
            console.log('Visible tabs: ' + $('.xpm-tab-content:visible').length);
            
            // Tab click handlers with debugging
            $('.xpm-nav-tab-wrapper .nav-tab').off('click.debug').on('click.debug', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $clickedTab = $(this);
                var targetTab = $clickedTab.data('tab');
                
                console.log('=== TAB CLICK DEBUG ===');
                console.log('Clicked tab:', targetTab);
                console.log('Tab element:', $clickedTab[0]);
                console.log('Data-tab attribute:', $clickedTab.attr('data-tab'));
                
                if (!targetTab) {
                    console.error('ERROR: No data-tab attribute found!');
                    return false;
                }
                
                // Hide all tabs
                $('.xpm-tab-content').each(function() {
                    $(this).removeClass('active').hide().css({
                        'display': 'none',
                        'visibility': 'hidden',
                        'opacity': '0'
                    });
                });
                
                // Remove all active nav classes
                $('.xpm-nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
                
                // Show target tab
                var $targetContent = $('#' + targetTab + '_content');
                console.log('Target content element:', $targetContent.length);
                
                if ($targetContent.length > 0) {
                    $targetContent.addClass('active').show().css({
                        'display': 'block',
                        'visibility': 'visible',
                        'opacity': '1'
                    });
                    
                    console.log('SUCCESS: Tab content shown for:', targetTab);
                } else {
                    console.error('ERROR: Tab content not found for:', targetTab);
                }
                
                // Set active nav tab
                $clickedTab.addClass('nav-tab-active');
                
                console.log('=== TAB CLICK DEBUG END ===');
                
                return false;
            });
            
            // Force show the active tab after a brief delay
            setTimeout(function() {
                var activeTab = '<?php echo esc_js($active_tab); ?>';
                var $activeContent = $('#' + activeTab + '_content');
                
                console.log('Forcing active tab visibility check...');
                console.log('Active tab:', activeTab);
                console.log('Active content element:', $activeContent.length);
                console.log('Is visible:', $activeContent.is(':visible'));
                
                if ($activeContent.length > 0 && !$activeContent.is(':visible')) {
                    console.log('FORCING tab to be visible...');
                    $activeContent.addClass('active').show().css({
                        'display': 'block',
                        'visibility': 'visible',
                        'opacity': '1'
                    });
                }
            }, 500);
            
            console.log('=== XPM SETTINGS PAGE DEBUG END ===');
        });
        </script>
        <?php
    }
    
    /**
     * Display bulk update page
     */
    public function display_bulk_update_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('XPM Bulk Alt Text Update', 'xpm-image-seo'); ?></h1>
            
            <div class="xpm-bulk-controls">
                <button id="xpm-scan-images" class="button button-primary">
                    <span class="dashicons dashicons-search"></span> 
                    <?php _e('Scan for Images Without Alt Text', 'xpm-image-seo'); ?>
                </button>
                
                <button id="xpm-bulk-update-start" class="button button-secondary" style="display: none;">
                    <span class="dashicons dashicons-update"></span> 
                    <?php _e('Start Bulk Update', 'xpm-image-seo'); ?>
                </button>
                
                <button id="xpm-bulk-update-stop" class="button" style="display: none;">
                    <span class="dashicons dashicons-no"></span> 
                    <?php _e('Stop Update', 'xpm-image-seo'); ?>
                </button>
            </div>
            
            <div id="xpm-scan-results" style="display: none;">
                <div class="xpm-results-header">
                    <h3><?php _e('Images without Alt Text:', 'xpm-image-seo'); ?> <span id="xpm-images-count" class="count">0</span></h3>
                    <p class="description"><?php _e('These images will benefit from SEO-optimized alt text to improve accessibility and search engine rankings.', 'xpm-image-seo'); ?></p>
                </div>
                
                <div id="xpm-images-preview"></div>
            </div>
            
            <div id="xpm-progress-container" style="display: none;">
                <h3><?php _e('Update Progress', 'xpm-image-seo'); ?></h3>
                <div class="xpm-progress-bar">
                    <div id="xpm-progress-fill"></div>
                </div>
                <p id="xpm-progress-text"><?php _e('0 of 0 images processed', 'xpm-image-seo'); ?></p>
                <p id="xpm-estimated-time"></p>
            </div>
            
            <div id="xpm-results-log"></div>
        </div>
        <?php
    }
    
    /**
     * Display optimizer page
     */
    public function display_optimizer_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('XPM Image Optimizer', 'xpm-image-seo'); ?></h1>
            
            <div class="xpm-optimizer-controls">
                <button id="xpm-scan-unoptimized" class="button button-primary">
                    <span class="dashicons dashicons-images-alt2"></span> 
                    <?php _e('Scan for Unoptimized Images', 'xpm-image-seo'); ?>
                </button>
                
                <button id="xpm-optimize-start" class="button button-secondary" style="display: none;">
                    <span class="dashicons dashicons-update"></span> 
                    <?php _e('Start Optimization', 'xpm-image-seo'); ?>
                </button>
                
                <button id="xpm-optimize-stop" class="button" style="display: none;">
                    <span class="dashicons dashicons-no"></span> 
                    <?php _e('Stop Optimization', 'xpm-image-seo'); ?>
                </button>
                
                <button id="xpm-restore-images" class="button">
                    <span class="dashicons dashicons-undo"></span> 
                    <?php _e('Restore Images', 'xpm-image-seo'); ?>
                </button>
            </div>
            
            <div id="xpm-optimizer-stats" style="display: none;"></div>
            
            <div id="xpm-optimizer-results" style="display: none;">
                <div class="xpm-results-header">
                    <h3><?php _e('Unoptimized Images:', 'xpm-image-seo'); ?> <span id="xpm-optimizer-count" class="count">0</span></h3>
                </div>
                
                <div id="xpm-optimizer-preview"></div>
            </div>
            
            <div id="xpm-optimizer-progress" style="display: none;">
                <h3><?php _e('Optimization Progress', 'xpm-image-seo'); ?></h3>
                <div class="xpm-progress-bar">
                    <div id="xpm-optimizer-progress-fill"></div>
                </div>
                <p id="xpm-optimizer-progress-text"></p>
                <p id="xpm-optimizer-savings"></p>
            </div>
            
            <div id="xpm-optimizer-log"></div>
        </div>
        <?php
    }
    
    /**
     * Enqueue lazy loading scripts on frontend
     */
    public function enqueue_lazy_loading_scripts() {
        $options = get_option($this->option_name);
        
        wp_enqueue_script(
            'xpm-lazy-loading',
            XPM_IMAGE_SEO_PLUGIN_URL . 'assets/js/lazy-loading.js',
            array(),
            XPM_IMAGE_SEO_VERSION,
            true
        );
        
        wp_enqueue_style(
            'xpm-lazy-loading',
            XPM_IMAGE_SEO_PLUGIN_URL . 'assets/css/lazy-loading.css',
            array(),
            XPM_IMAGE_SEO_VERSION
        );
        
        wp_localize_script('xpm-lazy-loading', 'xpmLazy', array(
            'threshold' => intval($options['lazy_loading_threshold'] ?? 200),
            'effect' => $options['lazy_loading_effect'] ?? 'fade'
        ));
    }
    
    /**
     * Add lazy loading to images in content
     */
    public function add_lazy_loading_to_images($content) {
        if (is_admin() || is_feed() || is_preview()) {
            return $content;
        }
        
        // Skip if already processed
        if (strpos($content, 'xpm-lazy') !== false) {
            return $content;
        }
        
        $options = get_option($this->option_name);
        $placeholder = $this->get_lazy_loading_placeholder($options);
        
        // Process images with regex
        $content = preg_replace_callback(
            '/<img([^>]*)src=["\']([^"\']*)["\']([^>]*)>/i',
            function($matches) use ($placeholder) {
                $before_src = $matches[1];
                $src = $matches[2];
                $after_src = $matches[3];
                
                // Skip if image already has data-src or is very small
                if (strpos($before_src . $after_src, 'data-src') !== false) {
                    return $matches[0];
                }
                
                // Add lazy loading attributes
                $new_img = '<img' . $before_src . 
                           'src="' . $placeholder . '" ' .
                           'data-src="' . $src . '" ' .
                           'class="xpm-lazy"' . $after_src . '>';
                
                return $new_img;
            },
            $content
        );
        
        return $content;
    }
    
    /**
     * Add lazy loading to post thumbnails
     */
    public function add_lazy_loading_to_thumbnails($html, $post_id, $post_thumbnail_id, $size, $attr) {
        if (is_admin() || is_feed() || is_preview()) {
            return $html;
        }
        
        $options = get_option($this->option_name);
        $placeholder = $this->get_lazy_loading_placeholder($options);
        
        // Add lazy loading to thumbnail
        $html = preg_replace_callback(
            '/<img([^>]*)src=["\']([^"\']*)["\']([^>]*)>/i',
            function($matches) use ($placeholder) {
                $before_src = $matches[1];
                $src = $matches[2];
                $after_src = $matches[3];
                
                // Add class if not present
                if (strpos($before_src . $after_src, 'class=') !== false) {
                    $new_img = preg_replace('/class=["\']([^"\']*)["\']/', 'class="$1 xpm-lazy"', $before_src . $after_src);
                } else {
                    $new_img = $before_src . ' class="xpm-lazy"' . $after_src;
                }
                
                return '<img' . $new_img . 'src="' . $placeholder . '" data-src="' . $src . '">';
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Get lazy loading placeholder based on settings
     */
    private function get_lazy_loading_placeholder($options) {
        $placeholder_type = $options['lazy_loading_placeholder'] ?? 'blur';
        
        switch ($placeholder_type) {
            case 'grey':
                return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAiIGhlaWdodD0iMTAiIHZpZXdCb3g9IjAgMCAxMCAxMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjEwIiBoZWlnaHQ9IjEwIiBmaWxsPSIjRjNGM0YzIi8+Cjwvc3ZnPgo=';
                
            case 'transparent':
                return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
                
            case 'custom':
                $custom_url = $options['lazy_loading_custom_placeholder'] ?? '';
                return !empty($custom_url) ? $custom_url : $this->get_blur_placeholder();
                
            default: // blur
                return $this->get_blur_placeholder();
        }
    }
    
    /**
     * Generate blur placeholder
     */
    private function get_blur_placeholder() {
        return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAiIGhlaWdodD0iMTAiIHZpZXdCb3g9IjAgMCAxMCAxMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGRlZnM+CjxsaW5lYXJHcmFkaWVudCBpZD0iZ3JhZGllbnQiIHgxPSIwJSIgeTE9IjAlIiB4Mj0iMTAwJSIgeTI9IjEwMCUiPgo8c3RvcCBvZmZzZXQ9IjAlIiBzdG9wLWNvbG9yPSIjRjBGMEYwIi8+CjxzdG9wIG9mZnNldD0iNTAlIiBzdG9wLWNvbG9yPSIjRTBFMEUwIi8+CjxzdG9wIG9mZnNldD0iMTAwJSIgc3RvcC1jb2xvcj0iI0YwRjBGMCIvPgo8L2xpbmVhckdyYWRpZW50Pgo8L2RlZnM+CjxyZWN0IHdpZHRoPSIxMCIgaGVpZ2h0PSIxMCIgZmlsbD0idXJsKCNncmFkaWVudCkiLz4KPC9zdmc+';
    }
}