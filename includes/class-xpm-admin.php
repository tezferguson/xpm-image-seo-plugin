<?php
/**
 * XPM Image SEO Admin Interface - SYNTAX FIXED VERSION
 * 
 * @package XPM_Image_SEO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin interface functionality with working tabbed interface
 */
class XPM_Image_SEO_Admin {
    
    private $option_name = 'xpm_image_seo_settings';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Lazy loading functionality
        add_action('wp_enqueue_scripts', array($this, 'enqueue_lazy_loading'));
        add_filter('the_content', array($this, 'add_lazy_loading_to_images'));
        add_filter('post_thumbnail_html', array($this, 'add_lazy_loading_to_thumbnails'), 10, 5);
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_options_page(
            __('XPM Image SEO Settings', 'xpm-image-seo'),
            __('XPM Image SEO', 'xpm-image-seo'),
            'manage_options',
            'xpm-image-seo-settings',
            array($this, 'settings_page')
        );
        
        add_media_page(
            __('XPM Bulk Alt Text Update', 'xpm-image-seo'),
            __('Bulk Alt Text Update', 'xpm-image-seo'),
            'upload_files',
            'xpm-image-seo-bulk-update',
            array($this, 'bulk_update_page')
        );
        
        add_media_page(
            __('XPM Image Optimizer', 'xpm-image-seo'),
            __('Image Optimizer', 'xpm-image-seo'),
            'upload_files',
            'xpm-image-seo-optimizer',
            array($this, 'optimizer_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (!in_array($hook, array('settings_page_xpm-image-seo-settings', 'media_page_xpm-image-seo-bulk-update', 'media_page_xpm-image-seo-optimizer'))) {
            return;
        }
        
        wp_enqueue_script(
            'xpm-image-seo-admin', 
            XPM_IMAGE_SEO_PLUGIN_URL . 'assets/js/admin.js', 
            array('jquery'), 
            XPM_IMAGE_SEO_VERSION, 
            true
        );
        
        wp_localize_script('xpm-image-seo-admin', 'xpmImageSeo', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('xpm_image_seo_nonce'),
            'current_page' => $hook,
            'strings' => array(
                'scanning' => __('Scanning...', 'xpm-image-seo'),
                'scan_button' => __('Scan for Images Without Alt Text', 'xpm-image-seo'),
                'scan_optimizer_button' => __('Scan for Unoptimized Images', 'xpm-image-seo'),
                'optimizing' => __('Optimizing...', 'xpm-image-seo'),
                'no_images' => __('No images to update', 'xpm-image-seo'),
                'no_unoptimized' => __('No unoptimized images found', 'xpm-image-seo'),
                'stopped' => __('Process stopped by user', 'xpm-image-seo'),
                'completed' => __('Process completed!', 'xpm-image-seo'),
                'network_error' => __('Network error', 'xpm-image-seo'),
                'scan_failed' => __('Failed to scan images', 'xpm-image-seo'),
                'confirm_stop' => __('Are you sure you want to stop the process?', 'xpm-image-seo'),
                'confirm_optimize' => __('Start optimizing images? This will compress and resize images according to your settings.', 'xpm-image-seo')
            )
        ));
        
        wp_enqueue_style(
            'xpm-image-seo-admin', 
            XPM_IMAGE_SEO_PLUGIN_URL . 'assets/css/admin.css', 
            array(), 
            XPM_IMAGE_SEO_VERSION
        );
        
        // Add working tabs for settings page
        if ($hook === 'settings_page_xpm-image-seo-settings') {
            $this->add_tab_functionality();
        }
    }
    
    /**
     * Add tab functionality safely
     */
    private function add_tab_functionality() {
        // WooCommerce-style CSS for tabs
        wp_add_inline_style('xpm-image-seo-admin', '
            .nav-tab-wrapper { 
                border-bottom: 1px solid #ccd0d4; 
                margin: 0 0 20px; 
                background: #f1f1f1; 
                padding-left: 10px;
            }
            .nav-tab { 
                border: 1px solid #ccd0d4; 
                border-bottom: none; 
                margin-left: 0.5em; 
                margin-bottom: -1px;
                padding: 10px 15px; 
                background: #e4e4e4; 
                color: #555; 
                text-decoration: none; 
                display: inline-block; 
                cursor: pointer;
                transition: all 0.2s ease;
                border-top-left-radius: 3px;
                border-top-right-radius: 3px;
            }
            .nav-tab:hover {
                background: #f9f9f9;
                color: #464646;
                border-color: #999;
            }
            .nav-tab-active { 
                background: #fff; 
                border-bottom-color: #fff; 
                color: #000; 
                font-weight: 600;
                z-index: 1;
                position: relative;
            }
            .tab-content { 
                display: none; 
                background: #fff;
                border: 1px solid #ccd0d4;
                border-top: none;
                margin-top: -1px;
                padding: 20px;
                min-height: 400px;
            }
            .tab-content.active { 
                display: block; 
            }
            .tab-content .form-table {
                margin-top: 0;
            }
            .tab-content .submit {
                margin: 20px 0 0 0;
                padding-top: 20px;
                border-top: 1px solid #ddd;
            }
        ');
        
        // JavaScript for tabs
        wp_add_inline_script('xpm-image-seo-admin', '
            jQuery(document).ready(function($) {
                $(".nav-tab").on("click", function(e) {
                    e.preventDefault();
                    var tab = $(this).data("tab");
                    $(".nav-tab").removeClass("nav-tab-active");
                    $(this).addClass("nav-tab-active");
                    $(".tab-content").removeClass("active").hide();
                    $("#" + tab + "_content").addClass("active").show();
                    
                    // Update URL
                    if (history.replaceState) {
                        var url = new URL(window.location);
                        url.searchParams.set("tab", tab);
                        window.history.replaceState({}, "", url);
                    }
                });
                
                // Initialize - hide non-active tabs
                $(".tab-content:not(.active)").hide();
                
                // Compression slider
                $(".compression-slider").on("input", function() {
                    $(this).next(".quality-display").text($(this).val() + "%");
                });
                
                // Custom placeholder toggle
                $("select[name$=\"[lazy_loading_placeholder]\"]").on("change", function() {
                    var customField = $("#custom-placeholder-field");
                    if ($(this).val() === "custom") {
                        customField.show();
                    } else {
                        customField.hide();
                    }
                });
            });
        ');
    }
    
    /**
     * Initialize settings
     */
    public function settings_init() {
        register_setting('xpm_image_seo', $this->option_name, array($this, 'sanitize_settings'));
    }
    
    /**
     * Sanitize settings
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
        
        return $sanitized;
    }
    
    /**
     * Settings page with ALL settings organized into tabs
     */
    public function settings_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'alt_text';
        $options = get_option($this->option_name, array());
        ?>
        <div class="wrap">
            <h1>
                XPM Image SEO Settings
                <span style="font-size: 14px; font-weight: normal; color: #666;">by <a href="https://xploited.media" target="_blank" style="color: #0073aa; text-decoration: none;">Xploited Media</a></span>
            </h1>
            
            <div class="nav-tab-wrapper">
                <a href="#" class="nav-tab <?php echo $active_tab === 'alt_text' ? 'nav-tab-active' : ''; ?>" data-tab="alt_text">AI Alt Text</a>
                <a href="#" class="nav-tab <?php echo $active_tab === 'optimization' ? 'nav-tab-active' : ''; ?>" data-tab="optimization">Image Optimization</a>
                <a href="#" class="nav-tab <?php echo $active_tab === 'performance' ? 'nav-tab-active' : ''; ?>" data-tab="performance">Performance</a>
            </div>
            
            <form action="options.php" method="post">
                <?php settings_fields('xpm_image_seo'); ?>
                
                <!-- ALT TEXT TAB -->
                <div id="alt_text_content" class="tab-content <?php echo $active_tab === 'alt_text' ? 'active' : ''; ?>">
                    
                    <h2>OpenAI API Configuration</h2>
                    <p>Configure your OpenAI API settings to enable automatic alt text generation.</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">OpenAI API Key</th>
                            <td>
                                <?php 
                                $api_key = isset($options['api_key']) ? $options['api_key'] : '';
                                $masked_key = !empty($api_key) ? str_repeat('*', max(0, strlen($api_key) - 8)) . substr($api_key, -8) : '';
                                ?>
                                <input type="password" name="<?php echo $this->option_name; ?>[api_key]" value="<?php echo esc_attr($api_key); ?>" class="regular-text" autocomplete="new-password" />
                                <?php if (!empty($masked_key)): ?>
                                    <p class="description">Current key: <?php echo esc_html($masked_key); ?></p>
                                <?php endif; ?>
                                <p class="description">Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>. Make sure you have sufficient credits in your OpenAI account.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <h2>Smart Keywords</h2>
                    <p>Configure smart keyword integration to optimize alt text for SEO.</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Global Keywords</th>
                            <td>
                                <textarea name="<?php echo $this->option_name; ?>[global_keywords]" rows="3" class="large-text" placeholder="e.g. your brand name, main services, target keywords"><?php echo esc_textarea(isset($options['global_keywords']) ? $options['global_keywords'] : ''); ?></textarea>
                                <p class="description">Enter keywords separated by commas. These will be considered for all images. Example: "Xploited Media, digital marketing, web design, SEO services"</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Use Contextual Keywords</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[use_contextual_keywords]" value="1" <?php checked(1, isset($options['use_contextual_keywords']) ? $options['use_contextual_keywords'] : 1); ?> />
                                    Extract keywords from pages where images are used
                                </label>
                                <p class="description">When enabled, the plugin will analyze the page title, content, tags, and categories to find relevant keywords for each image.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Keyword Priority</th>
                            <td>
                                <select name="<?php echo $this->option_name; ?>[keyword_priority]">
                                    <option value="contextual_first" <?php selected('contextual_first', isset($options['keyword_priority']) ? $options['keyword_priority'] : 'contextual_first'); ?>>Contextual keywords first</option>
                                    <option value="global_first" <?php selected('global_first', isset($options['keyword_priority']) ? $options['keyword_priority'] : 'contextual_first'); ?>>Global keywords first</option>
                                    <option value="mixed" <?php selected('mixed', isset($options['keyword_priority']) ? $options['keyword_priority'] : 'contextual_first'); ?>>Mix both intelligently</option>
                                </select>
                                <p class="description">Choose how to prioritize keywords when both global and contextual keywords are available.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Max Keywords Per Image</th>
                            <td>
                                <input type="number" name="<?php echo $this->option_name; ?>[max_keywords]" value="<?php echo esc_attr(isset($options['max_keywords']) ? $options['max_keywords'] : 3); ?>" min="1" max="10" class="small-text" />
                                <p class="description">Maximum number of keywords to incorporate per image (1-10). More keywords = longer alt text.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <h2>AI Behavior Settings</h2>
                    <p>Control how the AI generates alt text for your images.</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Auto-generate for new uploads</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[auto_generate]" value="1" <?php checked(1, isset($options['auto_generate']) ? $options['auto_generate'] : 0); ?> />
                                    Automatically generate alt text for new image uploads
                                </label>
                                <p class="description">When enabled, alt text will be automatically generated for images uploaded to your media library.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Custom Prompt</th>
                            <td>
                                <textarea name="<?php echo $this->option_name; ?>[prompt]" rows="4" class="large-text"><?php echo esc_textarea(isset($options['prompt']) ? $options['prompt'] : ''); ?></textarea>
                                <p class="description">Customize the prompt sent to OpenAI. Leave blank to use the default SEO-optimized prompt.</p>
                                <details>
                                    <summary>Show default prompt</summary>
                                    <code>Analyze this image and provide a concise, SEO-friendly alt text description. Focus on the main subject, important visual elements, and context that would be valuable for both screen readers and search engines. Keep it descriptive but under 125 characters.</code>
                                </details>
                            </td>
                        </tr>
                    </table>
                    
                    <h2>Fields to Update</h2>
                    <p>Choose which image fields to update with the generated content.</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Update Image Title</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[update_title]" value="1" <?php checked(1, isset($options['update_title']) ? $options['update_title'] : 1); ?> />
                                    Update image title with generated alt text
                                </label>
                                <p class="description">The image title helps with SEO and appears in media library listings.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Update Image Description</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[update_description]" value="1" <?php checked(1, isset($options['update_description']) ? $options['update_description'] : 1); ?> />
                                    Update image description with generated alt text
                                </label>
                                <p class="description">The description provides additional context and SEO value for the image.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Skip Existing Content</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[skip_existing]" value="1" <?php checked(1, isset($options['skip_existing']) ? $options['skip_existing'] : 1); ?> />
                                    Skip fields that already have content
                                </label>
                                <p class="description">When enabled, only empty fields will be updated. Disable to overwrite existing content.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('Save Alt Text Settings'); ?>
                </div>
                
                <!-- IMAGE OPTIMIZATION TAB -->
                <div id="optimization_content" class="tab-content <?php echo $active_tab === 'optimization' ? 'active' : ''; ?>">
                    
                    <h2>Image Optimization Settings</h2>
                    <p>Configure automatic image optimization to improve website speed and SEO rankings.</p>
                    
                    <?php
                    $gd_available = extension_loaded('gd');
                    $imagick_available = extension_loaded('imagick');
                    
                    if (!$gd_available && !$imagick_available) {
                        echo '<div class="notice notice-warning inline"><p><strong>Warning:</strong> Neither GD nor Imagick extensions are available. Image optimization will be limited.</p></div>';
                    } else {
                        $library = $imagick_available ? 'Imagick' : 'GD';
                        echo '<div class="notice notice-success inline"><p><strong>Image library detected: ' . $library . '</strong></p></div>';
                    }
                    ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Auto-optimize on Upload</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[auto_optimize]" value="1" <?php checked(1, isset($options['auto_optimize']) ? $options['auto_optimize'] : 1); ?> />
                                    Automatically optimize images when uploaded
                                </label>
                                <p class="description">When enabled, all new image uploads will be automatically optimized according to your settings below.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Backup Original Images</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[backup_originals]" value="1" <?php checked(1, isset($options['backup_originals']) ? $options['backup_originals'] : 1); ?> />
                                    Keep backup copies of original images
                                </label>
                                <p class="description">Recommended: Creates backup copies in uploads/xpm-backups/ folder. Allows restoration if needed.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <h2>Compression &amp; Quality</h2>
                    <p>Configure compression settings and image sizing limits.</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Compression Quality</th>
                            <td>
                                <?php $quality = isset($options['compression_quality']) ? $options['compression_quality'] : 85; ?>
                                <input type="range" name="<?php echo $this->option_name; ?>[compression_quality]" value="<?php echo esc_attr($quality); ?>" min="60" max="100" step="5" class="compression-slider" />
                                <span class="quality-display"><?php echo $quality; ?>%</span>
                                <p class="description">Compression quality: 60% = Maximum compression, 100% = Best quality. Recommended: 85%</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Maximum Width</th>
                            <td>
                                <input type="number" name="<?php echo $this->option_name; ?>[max_width]" value="<?php echo esc_attr(isset($options['max_width']) ? $options['max_width'] : 2048); ?>" min="500" max="4000" step="100" class="small-text" /> px
                                <p class="description">Images wider than this will be resized. Common values: 1920px (Full HD), 2048px (2K), 2560px (1440p)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Maximum Height</th>
                            <td>
                                <input type="number" name="<?php echo $this->option_name; ?>[max_height]" value="<?php echo esc_attr(isset($options['max_height']) ? $options['max_height'] : 2048); ?>" min="0" max="4000" step="100" class="small-text" /> px
                                <p class="description">Images taller than this will be resized. Set to 0 to disable height restrictions.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <h2>Advanced Optimization</h2>
                    <p>Advanced optimization features for better performance.</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Convert to WebP</th>
                            <td>
                                <?php 
                                $webp_supported = function_exists('imagewebp') || (extension_loaded('imagick') && class_exists('Imagick'));
                                $convert_webp = isset($options['convert_to_webp']) ? $options['convert_to_webp'] : 0;
                                
                                if (!$webp_supported): ?>
                                    <input type="checkbox" disabled /> Convert images to WebP format <em>(Not supported on this server)</em>
                                    <p class="description">WebP format provides superior compression. Your server does not support WebP conversion.</p>
                                <?php else: ?>
                                    <label>
                                        <input type="checkbox" name="<?php echo $this->option_name; ?>[convert_to_webp]" value="1" <?php checked(1, $convert_webp); ?> />
                                        Convert images to WebP format for better compression
                                    </label>
                                    <p class="description">WebP provides 25-35% better compression than JPEG. Creates both original and WebP versions.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('Save Optimization Settings'); ?>
                </div>
                
                <!-- PERFORMANCE TAB -->
                <div id="performance_content" class="tab-content <?php echo $active_tab === 'performance' ? 'active' : ''; ?>">
                    
                    <h2>Lazy Loading</h2>
                    <p>Lazy loading improves page speed by only loading images when they come into view.</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable Lazy Loading</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[enable_lazy_loading]" value="1" <?php checked(1, isset($options['enable_lazy_loading']) ? $options['enable_lazy_loading'] : 0); ?> />
                                    Enable lazy loading for images
                                </label>
                                <p class="description">Images will only load when they come into the viewport, improving page load speed.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Loading Threshold</th>
                            <td>
                                <input type="number" name="<?php echo $this->option_name; ?>[lazy_loading_threshold]" value="<?php echo esc_attr(isset($options['lazy_loading_threshold']) ? $options['lazy_loading_threshold'] : 200); ?>" min="0" max="1000" step="50" class="small-text" /> px
                                <p class="description">Distance from viewport when images should start loading. 0 = exactly when visible, 200 = 200px before visible.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Placeholder Image</th>
                            <td>
                                <?php $placeholder = isset($options['lazy_loading_placeholder']) ? $options['lazy_loading_placeholder'] : 'blur'; ?>
                                <select name="<?php echo $this->option_name; ?>[lazy_loading_placeholder]">
                                    <option value="blur" <?php selected('blur', $placeholder); ?>>Blurred placeholder</option>
                                    <option value="grey" <?php selected('grey', $placeholder); ?>>Grey placeholder</option>
                                    <option value="transparent" <?php selected('transparent', $placeholder); ?>>Transparent</option>
                                    <option value="custom" <?php selected('custom', $placeholder); ?>>Custom placeholder URL</option>
                                </select>
                                <p class="description">What to show while images are loading.</p>
                                
                                <?php $custom_placeholder = isset($options['lazy_loading_custom_placeholder']) ? $options['lazy_loading_custom_placeholder'] : ''; ?>
                                <div id="custom-placeholder-field" style="margin-top: 10px; <?php echo ($placeholder !== 'custom' ? 'display: none;' : ''); ?>">
                                    <input type="url" name="<?php echo $this->option_name; ?>[lazy_loading_custom_placeholder]" value="<?php echo esc_attr($custom_placeholder); ?>" class="regular-text" placeholder="https://example.com/placeholder.jpg" />
                                    <p class="description">URL to custom placeholder image.</p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Loading Effect</th>
                            <td>
                                <?php $effect = isset($options['lazy_loading_effect']) ? $options['lazy_loading_effect'] : 'fade'; ?>
                                <select name="<?php echo $this->option_name; ?>[lazy_loading_effect]">
                                    <option value="fade" <?php selected('fade', $effect); ?>>Fade in</option>
                                    <option value="slide" <?php selected('slide', $effect); ?>>Slide in</option>
                                    <option value="zoom" <?php selected('zoom', $effect); ?>>Zoom in</option>
                                    <option value="none" <?php selected('none', $effect); ?>>No effect</option>
                                </select>
                                <p class="description">Animation effect when images load.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('Save Performance Settings'); ?>
                </div>
                
            </form>
            
            <!-- Sidebar -->
            <div style="margin-top: 40px;">
                <div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                    <h3 style="margin-top: 0; color: #23282d; border-bottom: 1px solid #eee; padding-bottom: 10px;">Quick Start Guide</h3>
                    <ol style="padding-left: 20px;">
                        <li style="margin-bottom: 8px;">Get your OpenAI API key</li>
                        <li style="margin-bottom: 8px;">Configure optimization settings</li>
                        <li style="margin-bottom: 8px;">Enable lazy loading for performance</li>
                        <li style="margin-bottom: 8px;">Save settings</li>
                        <li style="margin-bottom: 8px;"><a href="<?php echo admin_url('upload.php?page=xpm-image-seo-bulk-update'); ?>">Go to Bulk Update</a> to update existing images</li>
                        <li style="margin-bottom: 8px;"><a href="<?php echo admin_url('upload.php?page=xpm-image-seo-optimizer'); ?>">Go to Image Optimizer</a> to optimize images</li>
                    </ol>
                </div>
                
                <div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                    <h3 style="margin-top: 0; color: #23282d; border-bottom: 1px solid #eee; padding-bottom: 10px;">About XPM Image SEO</h3>
                    <p>Complete image SEO and optimization solution. Boost your website's SEO and performance with AI-powered alt text, smart image compression, and lazy loading.</p>
                    <p><strong>Version:</strong> <?php echo defined('XPM_IMAGE_SEO_VERSION') ? XPM_IMAGE_SEO_VERSION : '1.1.0'; ?></p>
                    <p><a href="https://xploited.media" target="_blank" class="button">Visit Xploited Media</a></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Bulk update page
     */
    public function bulk_update_page() {
        ?>
        <div class="wrap">
            <h1>
                XPM Bulk Alt Text Update
                <span style="font-size: 14px; color: #666;">by <a href="https://xploited.media" target="_blank">Xploited Media</a></span>
            </h1>
            
            <div id="xpm-bulk-update-container">
                <div class="xpm-bulk-controls">
                    <button id="xpm-scan-images" class="button button-primary button-large">
                        <span class="dashicons dashicons-search"></span>
                        Scan for Images Without Alt Text
                    </button>
                    <button id="xpm-bulk-update-start" class="button button-secondary button-large" disabled>
                        <span class="dashicons dashicons-update"></span>
                        Start Bulk Update
                    </button>
                    <button id="xpm-bulk-update-stop" class="button button-large" style="display: none;">
                        <span class="dashicons dashicons-no"></span>
                        Stop Update
                    </button>
                </div>
                
                <div id="xpm-scan-results" style="display: none;">
                    <div class="xpm-results-header">
                        <h3>Images without Alt Text: <span id="xpm-images-count" class="count">0</span></h3>
                        <p class="description">These images will benefit from SEO-optimized alt text.</p>
                    </div>
                    <div id="xpm-images-preview"></div>
                </div>
                
                <div id="xpm-progress-container" style="display: none;">
                    <h3>Update Progress</h3>
                    <div class="xpm-progress-bar">
                        <div id="xpm-progress-fill"></div>
                    </div>
                    <div class="xpm-progress-info">
                        <span id="xpm-progress-text">0 of 0 images processed</span>
                        <span id="xpm-estimated-time"></span>
                    </div>
                </div>
                
                <div id="xpm-results-log"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Optimizer page
     */
    public function optimizer_page() {
        ?>
        <div class="wrap">
            <h1>
                XPM Image Optimizer
                <span style="font-size: 14px; color: #666;">by <a href="https://xploited.media" target="_blank">Xploited Media</a></span>
            </h1>
            
            <div id="xpm-optimizer-container">
                <div class="xpm-optimizer-controls">
                    <button id="xpm-scan-unoptimized" class="button button-primary button-large">
                        <span class="dashicons dashicons-images-alt2"></span>
                        Scan for Unoptimized Images
                    </button>
                    <button id="xpm-optimize-start" class="button button-secondary button-large" disabled>
                        <span class="dashicons dashicons-performance"></span>
                        Start Bulk Optimization
                    </button>
                    <button id="xpm-optimize-stop" class="button button-large" style="display: none;">
                        <span class="dashicons dashicons-no"></span>
                        Stop Optimization
                    </button>
                    <button id="xpm-restore-images" class="button button-link">
                        <span class="dashicons dashicons-backup"></span>
                        Restore from Backups
                    </button>
                </div>
                
                <div id="xpm-optimizer-stats" class="xpm-stats-grid" style="display: none;">
                    <div class="stat-card">
                        <h3>Images Found</h3>
                        <span class="stat-number" id="total-images">0</span>
                    </div>
                    <div class="stat-card">
                        <h3>Total Size</h3>
                        <span class="stat-number" id="total-size">0 MB</span>
                    </div>
                    <div class="stat-card">
                        <h3>Potential Savings</h3>
                        <span class="stat-number" id="potential-savings">~0%</span>
                    </div>
                    <div class="stat-card">
                        <h3>Processing</h3>
                        <span class="stat-number" id="processing-count">0</span>
                    </div>
                </div>
                
                <div id="xpm-optimizer-results" style="display: none;">
                    <div class="xpm-results-header">
                        <h3>Images to Optimize: <span id="xpm-optimizer-count" class="count">0</span></h3>
                        <p class="description">These images can be optimized to improve website performance and loading speeds.</p>
                    </div>
                    <div id="xpm-optimizer-preview"></div>
                </div>
                
                <div id="xpm-optimizer-progress" style="display: none;">
                    <h3>Optimization Progress</h3>
                    <div class="xpm-progress-bar">
                        <div id="xpm-optimizer-progress-fill"></div>
                    </div>
                    <div class="xpm-progress-info">
                        <span id="xpm-optimizer-progress-text">0 of 0 images optimized</span>
                        <span id="xpm-optimizer-savings">Saved: 0 MB</span>
                    </div>
                </div>
                
                <div id="xpm-optimizer-log"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        if (get_transient('xpm_image_seo_activated')) {
            delete_transient('xpm_image_seo_activated');
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong>XPM Image SEO activated!</strong>
                    Configure your settings in <a href="<?php echo admin_url('options-general.php?page=xpm-image-seo-settings'); ?>">Settings</a> to get started.
                </p>
            </div>
            <?php
        }
        
        $options = get_option($this->option_name);
        $api_key = isset($options['api_key']) ? trim($options['api_key']) : '';
        
        if (empty($api_key) && isset($_GET['page']) && in_array($_GET['page'], array('xpm-image-seo-settings', 'xpm-image-seo-bulk-update', 'xpm-image-seo-optimizer'))) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong>XPM Image SEO:</strong>
                    Please configure your OpenAI API key in <a href="<?php echo admin_url('options-general.php?page=xpm-image-seo-settings'); ?>">Settings</a> to enable alt text generation.
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Enqueue lazy loading scripts
     */
    public function enqueue_lazy_loading() {
        $options = get_option($this->option_name);
        $enable_lazy = isset($options['enable_lazy_loading']) ? $options['enable_lazy_loading'] : 0;
        
        if (!$enable_lazy) {
            return;
        }
        
        wp_enqueue_script(
            'xpm-lazy-loading',
            XPM_IMAGE_SEO_PLUGIN_URL . 'assets/js/lazy-loading.js',
            array(),
            XPM_IMAGE_SEO_VERSION,
            true
        );
        
        $threshold = isset($options['lazy_loading_threshold']) ? $options['lazy_loading_threshold'] : 200;
        $effect = isset($options['lazy_loading_effect']) ? $options['lazy_loading_effect'] : 'fade';
        
        wp_localize_script('xpm-lazy-loading', 'xpmLazy', array(
            'threshold' => $threshold,
            'effect' => $effect
        ));
        
        wp_enqueue_style(
            'xpm-lazy-loading',
            XPM_IMAGE_SEO_PLUGIN_URL . 'assets/css/lazy-loading.css',
            array(),
            XPM_IMAGE_SEO_VERSION
        );
    }
    
    /**
     * Add lazy loading to images in content
     */
    public function add_lazy_loading_to_images($content) {
        $options = get_option($this->option_name);
        $enable_lazy = isset($options['enable_lazy_loading']) ? $options['enable_lazy_loading'] : 0;
        
        if (!$enable_lazy || is_admin() || is_feed()) {
            return $content;
        }
        
        $placeholder = $this->get_lazy_placeholder();
        
        // Replace img tags with lazy loading
        $content = preg_replace_callback(
            '/<img([^>]+)>/i',
            function($matches) use ($placeholder) {
                $img_tag = $matches[0];
                $attributes = $matches[1];
                
                // Skip if already has loading attribute or data-src
                if (strpos($attributes, 'loading=') !== false || strpos($attributes, 'data-src=') !== false) {
                    return $img_tag;
                }
                
                // Extract src attribute
                if (preg_match('/src=["\']([^"\']+)["\']/', $attributes, $src_matches)) {
                    $src = $src_matches[1];
                    
                    // Replace src with data-src and add placeholder
                    $new_attributes = str_replace($src_matches[0], 'src="' . $placeholder . '" data-src="' . $src . '"', $attributes);
                    $new_attributes .= ' class="xpm-lazy" loading="lazy"';
                    
                    return '<img' . $new_attributes . '>';
                }
                
                return $img_tag;
            },
            $content
        );
        
        return $content;
    }
    
    /**
     * Add lazy loading to post thumbnails
     */
    public function add_lazy_loading_to_thumbnails($html, $post_id, $post_thumbnail_id, $size, $attr) {
        $options = get_option($this->option_name);
        $enable_lazy = isset($options['enable_lazy_loading']) ? $options['enable_lazy_loading'] : 0;
        
        if (!$enable_lazy || is_admin()) {
            return $html;
        }
        
        $placeholder = $this->get_lazy_placeholder();
        
        // Add lazy loading to thumbnail
        if (preg_match('/src=["\']([^"\']+)["\']/', $html, $src_matches)) {
            $src = $src_matches[1];
            $html = str_replace($src_matches[0], 'src="' . $placeholder . '" data-src="' . $src . '"', $html);
            $html = str_replace('<img ', '<img class="xpm-lazy" loading="lazy" ', $html);
        }
        
        return $html;
    }
    
    /**
     * Get lazy loading placeholder
     */
    private function get_lazy_placeholder() {
        $options = get_option($this->option_name);
        $placeholder_type = isset($options['lazy_loading_placeholder']) ? $options['lazy_loading_placeholder'] : 'blur';
        
        switch ($placeholder_type) {
            case 'grey':
                return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300"><rect width="100%" height="100%" fill="#f0f0f0"/></svg>');
            
            case 'transparent':
                return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
            
            case 'custom':
                $custom_url = isset($options['lazy_loading_custom_placeholder']) ? $options['lazy_loading_custom_placeholder'] : '';
                if (!empty($custom_url)) {
                    return $custom_url;
                }
                // Fallback to grey if custom URL is empty
                return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300"><rect width="100%" height="100%" fill="#f0f0f0"/></svg>');
            
            case 'blur':
            default:
                return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300"><defs><filter id="blur"><feGaussianBlur stdDeviation="10"/></filter></defs><rect width="100%" height="100%" fill="#e0e0e0" filter="url(#blur)"/></svg>');
        }
    }
}