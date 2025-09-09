<?php
/**
 * XPM Image SEO Admin Interface - WITH TABS AND LAZY LOADING
 * 
 * @package XPM_Image_SEO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin interface functionality with tabbed interface
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
        
        // Add custom CSS for tabs
        wp_add_inline_style('xpm-image-seo-admin', $this->get_tab_styles());
    }
    
    /**
     * Get custom CSS for tabs
     */
    private function get_tab_styles() {
        return "
        .xpm-tabs {
            border-bottom: 1px solid #ccd0d4;
            margin: 0 0 20px 0;
        }
        .xmp-tabs ul {
            margin: 0;
            padding: 0;
            list-style: none;
            display: flex;
        }
        .xpm-tabs li {
            margin: 0;
            padding: 0;
        }
        .xpm-tabs a {
            display: block;
            padding: 12px 20px;
            text-decoration: none;
            color: #0073aa;
            border: 1px solid transparent;
            border-bottom: none;
            background: #f1f1f1;
            margin-right: 5px;
            transition: all 0.3s ease;
        }
        .xpm-tabs a:hover {
            background: #e1e1e1;
            color: #005a87;
        }
        .xpm-tabs a.active {
            background: #fff;
            border-color: #ccd0d4;
            color: #23282d;
            font-weight: 600;
        }
        .xpm-tab-content {
            display: none;
        }
        .xpm-tab-content.active {
            display: block;
        }
        .xpm-tab-content .form-table {
            margin-top: 20px;
        }
        ";
    }
    
    /**
     * Initialize settings with tabs
     */
    public function settings_init() {
        register_setting('xpm_image_seo', $this->option_name, array($this, 'sanitize_settings'));
        
        // ALT TEXT TAB SECTIONS
        add_settings_section(
            'xpm_image_seo_api_section',
            __('OpenAI API Configuration', 'xpm-image-seo'),
            array($this, 'api_section_callback'),
            'xpm_image_seo_alt_text'
        );
        
        add_settings_section(
            'xpm_image_seo_keywords_section',
            __('Smart Keywords', 'xpm-image-seo'),
            array($this, 'keywords_section_callback'),
            'xpm_image_seo_alt_text'
        );
        
        add_settings_section(
            'xpm_image_seo_behavior_section',
            __('AI Behavior Settings', 'xpm-image-seo'),
            array($this, 'behavior_section_callback'),
            'xpm_image_seo_alt_text'
        );
        
        add_settings_section(
            'xpm_image_seo_fields_section',
            __('Fields to Update', 'xpm-image-seo'),
            array($this, 'fields_section_callback'),
            'xpm_image_seo_alt_text'
        );
        
        // IMAGE OPTIMIZATION TAB SECTIONS
        add_settings_section(
            'xpm_image_seo_optimization_section',
            __('Image Optimization Settings', 'xpm-image-seo'),
            array($this, 'optimization_section_callback'),
            'xpm_image_seo_optimization'
        );
        
        add_settings_section(
            'xpm_image_seo_compression_section',
            __('Compression & Quality', 'xpm-image-seo'),
            array($this, 'compression_section_callback'),
            'xpm_image_seo_optimization'
        );
        
        add_settings_section(
            'xpm_image_seo_advanced_section',
            __('Advanced Optimization', 'xpm-image-seo'),
            array($this, 'advanced_section_callback'),
            'xpm_image_seo_optimization'
        );
        
        // PERFORMANCE TAB SECTIONS
        add_settings_section(
            'xpm_image_seo_lazy_section',
            __('Lazy Loading', 'xpm-image-seo'),
            array($this, 'lazy_section_callback'),
            'xpm_image_seo_performance'
        );
        
        // ALT TEXT TAB FIELDS
        add_settings_field('api_key', __('OpenAI API Key', 'xpm-image-seo'), array($this, 'api_key_render'), 'xpm_image_seo_alt_text', 'xpm_image_seo_api_section');
        add_settings_field('global_keywords', __('Global Keywords', 'xpm-image-seo'), array($this, 'global_keywords_render'), 'xpm_image_seo_alt_text', 'xpm_image_seo_keywords_section');
        add_settings_field('use_contextual_keywords', __('Use Contextual Keywords', 'xpm-image-seo'), array($this, 'use_contextual_keywords_render'), 'xpm_image_seo_alt_text', 'xpm_image_seo_keywords_section');
        add_settings_field('keyword_priority', __('Keyword Priority', 'xpm-image-seo'), array($this, 'keyword_priority_render'), 'xpm_image_seo_alt_text', 'xpm_image_seo_keywords_section');
        add_settings_field('max_keywords', __('Max Keywords Per Image', 'xpm-image-seo'), array($this, 'max_keywords_render'), 'xpm_image_seo_alt_text', 'xpm_image_seo_keywords_section');
        add_settings_field('auto_generate', __('Auto-generate for new uploads', 'xpm-image-seo'), array($this, 'auto_generate_render'), 'xpm_image_seo_alt_text', 'xpm_image_seo_behavior_section');
        add_settings_field('prompt', __('Custom Prompt', 'xpm-image-seo'), array($this, 'prompt_render'), 'xpm_image_seo_alt_text', 'xpm_image_seo_behavior_section');
        add_settings_field('update_title', __('Update Image Title', 'xpm-image-seo'), array($this, 'update_title_render'), 'xpm_image_seo_alt_text', 'xpm_image_seo_fields_section');
        add_settings_field('update_description', __('Update Image Description', 'xpm-image-seo'), array($this, 'update_description_render'), 'xpm_image_seo_alt_text', 'xpm_image_seo_fields_section');
        add_settings_field('skip_existing', __('Skip Existing Content', 'xpm-image-seo'), array($this, 'skip_existing_render'), 'xpm_image_seo_alt_text', 'xpm_image_seo_fields_section');
        
        // IMAGE OPTIMIZATION TAB FIELDS
        add_settings_field('auto_optimize', __('Auto-optimize on Upload', 'xpm-image-seo'), array($this, 'auto_optimize_render'), 'xpm_image_seo_optimization', 'xpm_image_seo_optimization_section');
        add_settings_field('backup_originals', __('Backup Original Images', 'xpm-image-seo'), array($this, 'backup_originals_render'), 'xpm_image_seo_optimization', 'xpm_image_seo_optimization_section');
        add_settings_field('compression_quality', __('Compression Quality', 'xpm-image-seo'), array($this, 'compression_quality_render'), 'xpm_image_seo_optimization', 'xpm_image_seo_compression_section');
        add_settings_field('max_width', __('Maximum Width', 'xpm-image-seo'), array($this, 'max_width_render'), 'xpm_image_seo_optimization', 'xpm_image_seo_compression_section');
        add_settings_field('max_height', __('Maximum Height', 'xpm-image-seo'), array($this, 'max_height_render'), 'xpm_image_seo_optimization', 'xpm_image_seo_compression_section');
        add_settings_field('convert_to_webp', __('Convert to WebP', 'xpm-image-seo'), array($this, 'convert_to_webp_render'), 'xpm_image_seo_optimization', 'xpm_image_seo_advanced_section');
        
        // PERFORMANCE TAB FIELDS
        add_settings_field('enable_lazy_loading', __('Enable Lazy Loading', 'xpm-image-seo'), array($this, 'enable_lazy_loading_render'), 'xpm_image_seo_performance', 'xpm_image_seo_lazy_section');
        add_settings_field('lazy_loading_threshold', __('Loading Threshold', 'xpm-image-seo'), array($this, 'lazy_loading_threshold_render'), 'xpm_image_seo_performance', 'xpm_image_seo_lazy_section');
        add_settings_field('lazy_loading_placeholder', __('Placeholder Image', 'xpm-image-seo'), array($this, 'lazy_loading_placeholder_render'), 'xpm_image_seo_performance', 'xpm_image_seo_lazy_section');
        add_settings_field('lazy_loading_effect', __('Loading Effect', 'xpm-image-seo'), array($this, 'lazy_loading_effect_render'), 'xpm_image_seo_performance', 'xpm_image_seo_lazy_section');
    }
    
    // Section callbacks
    public function api_section_callback() {
        echo '<p>' . __('Configure your OpenAI API settings to enable automatic alt text generation.', 'xpm-image-seo') . '</p>';
    }
    
    public function keywords_section_callback() {
        echo '<p>' . __('Configure smart keyword integration to optimize alt text for SEO. Keywords help make your images more discoverable in search engines.', 'xpm-image-seo') . '</p>';
    }
    
    public function behavior_section_callback() {
        echo '<p>' . __('Control how the AI generates alt text for your images.', 'xpm-image-seo') . '</p>';
    }
    
    public function fields_section_callback() {
        echo '<p>' . __('Choose which image fields to update with the generated content.', 'xpm-image-seo') . '</p>';
    }
    
    public function optimization_section_callback() {
        echo '<p>' . __('Configure automatic image optimization to improve website speed and SEO rankings.', 'xpm-image-seo') . '</p>';
        
        $gd_available = extension_loaded('gd');
        $imagick_available = extension_loaded('imagick');
        
        if (!$gd_available && !$imagick_available) {
            echo '<div class="notice notice-warning inline"><p><strong>' . __('Warning:', 'xpm-image-seo') . '</strong> ' . __('Neither GD nor Imagick extensions are available. Image optimization will be limited.', 'xpm-image-seo') . '</p></div>';
        } else {
            $library = $imagick_available ? 'Imagick' : 'GD';
            echo '<div class="notice notice-success inline"><p><strong>' . sprintf(__('Image library detected: %s', 'xpm-image-seo'), $library) . '</strong></p></div>';
        }
    }
    
    public function compression_section_callback() {
        echo '<p>' . __('Configure compression settings and image sizing limits.', 'xpm-image-seo') . '</p>';
    }
    
    public function advanced_section_callback() {
        echo '<p>' . __('Advanced optimization features for better performance.', 'xpm-image-seo') . '</p>';
    }
    
    public function lazy_section_callback() {
        echo '<p>' . __('Lazy loading improves page speed by only loading images when they come into view.', 'xpm-image-seo') . '</p>';
    }
    
    // Field renders - ALT TEXT TAB
    public function api_key_render() {
        $options = get_option($this->option_name);
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        $masked_key = !empty($api_key) ? str_repeat('*', max(0, strlen($api_key) - 8)) . substr($api_key, -8) : '';
        
        echo '<input type="password" name="' . $this->option_name . '[api_key]" value="' . esc_attr($api_key) . '" class="regular-text" autocomplete="new-password" />';
        if (!empty($masked_key)) {
            echo '<p class="description">' . sprintf(__('Current key: %s', 'xpm-image-seo'), esc_html($masked_key)) . '</p>';
        }
        echo '<p class="description">' . sprintf(__('Get your API key from %s. Make sure you have sufficient credits in your OpenAI account.', 'xpm-image-seo'), '<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>') . '</p>';
    }
    
    public function global_keywords_render() {
        $options = get_option($this->option_name);
        $global_keywords = isset($options['global_keywords']) ? $options['global_keywords'] : '';
        
        echo '<textarea name="' . $this->option_name . '[global_keywords]" rows="3" class="large-text" placeholder="e.g. your brand name, main services, target keywords">' . esc_textarea($global_keywords) . '</textarea>';
        echo '<p class="description">' . __('Enter keywords separated by commas. These will be considered for all images. Example: "Xploited Media, digital marketing, web design, SEO services"', 'xpm-image-seo') . '</p>';
    }
    
    public function use_contextual_keywords_render() {
        $options = get_option($this->option_name);
        $use_contextual = isset($options['use_contextual_keywords']) ? $options['use_contextual_keywords'] : 1;
        
        echo '<label><input type="checkbox" name="' . $this->option_name . '[use_contextual_keywords]" value="1" ' . checked(1, $use_contextual, false) . ' /> ';
        echo __('Extract keywords from pages where images are used', 'xpm-image-seo') . '</label>';
        echo '<p class="description">' . __('When enabled, the plugin will analyze the page title, content, tags, and categories to find relevant keywords for each image.', 'xpm-image-seo') . '</p>';
    }
    
    public function keyword_priority_render() {
        $options = get_option($this->option_name);
        $priority = isset($options['keyword_priority']) ? $options['keyword_priority'] : 'contextual_first';
        
        echo '<select name="' . $this->option_name . '[keyword_priority]">';
        echo '<option value="contextual_first" ' . selected('contextual_first', $priority, false) . '>' . __('Contextual keywords first', 'xpm-image-seo') . '</option>';
        echo '<option value="global_first" ' . selected('global_first', $priority, false) . '>' . __('Global keywords first', 'xpm-image-seo') . '</option>';
        echo '<option value="mixed" ' . selected('mixed', $priority, false) . '>' . __('Mix both intelligently', 'xpm-image-seo') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('Choose how to prioritize keywords when both global and contextual keywords are available.', 'xpm-image-seo') . '</p>';
    }
    
    public function max_keywords_render() {
        $options = get_option($this->option_name);
        $max_keywords = isset($options['max_keywords']) ? $options['max_keywords'] : 3;
        
        echo '<input type="number" name="' . $this->option_name . '[max_keywords]" value="' . esc_attr($max_keywords) . '" min="1" max="10" class="small-text" />';
        echo '<p class="description">' . __('Maximum number of keywords to incorporate per image (1-10). More keywords = longer alt text.', 'xpm-image-seo') . '</p>';
    }
    
    public function auto_generate_render() {
        $options = get_option($this->option_name);
        $auto_generate = isset($options['auto_generate']) ? $options['auto_generate'] : 0;
        
        echo '<label><input type="checkbox" name="' . $this->option_name . '[auto_generate]" value="1" ' . checked(1, $auto_generate, false) . ' /> ';
        echo __('Automatically generate alt text for new image uploads', 'xpm-image-seo') . '</label>';
        echo '<p class="description">' . __('When enabled, alt text will be automatically generated for images uploaded to your media library.', 'xpm-image-seo') . '</p>';
    }
    
    public function prompt_render() {
        $options = get_option($this->option_name);
        $prompt = isset($options['prompt']) ? $options['prompt'] : '';
        
        echo '<textarea name="' . $this->option_name . '[prompt]" rows="4" class="large-text">' . esc_textarea($prompt) . '</textarea>';
        echo '<p class="description">' . __('Customize the prompt sent to OpenAI. Leave blank to use the default SEO-optimized prompt.', 'xpm-image-seo') . '</p>';
        
        $default_prompt = __("Analyze this image and provide a concise, SEO-friendly alt text description. Focus on the main subject, important visual elements, and context that would be valuable for both screen readers and search engines. Keep it descriptive but under 125 characters.", 'xpm-image-seo');
        echo '<details><summary>' . __('Show default prompt', 'xpm-image-seo') . '</summary><code>' . esc_html($default_prompt) . '</code></details>';
    }
    
    public function update_title_render() {
        $options = get_option($this->option_name);
        $update_title = isset($options['update_title']) ? $options['update_title'] : 1;
        
        echo '<label><input type="checkbox" name="' . $this->option_name . '[update_title]" value="1" ' . checked(1, $update_title, false) . ' /> ';
        echo __('Update image title with generated alt text', 'xpm-image-seo') . '</label>';
        echo '<p class="description">' . __('The image title helps with SEO and appears in media library listings.', 'xpm-image-seo') . '</p>';
    }
    
    public function update_description_render() {
        $options = get_option($this->option_name);
        $update_description = isset($options['update_description']) ? $options['update_description'] : 1;
        
        echo '<label><input type="checkbox" name="' . $this->option_name . '[update_description]" value="1" ' . checked(1, $update_description, false) . ' /> ';
        echo __('Update image description with generated alt text', 'xpm-image-seo') . '</label>';
        echo '<p class="description">' . __('The description provides additional context and SEO value for the image.', 'xpm-image-seo') . '</p>';
    }
    
    public function skip_existing_render() {
        $options = get_option($this->option_name);
        $skip_existing = isset($options['skip_existing']) ? $options['skip_existing'] : 1;
        
        echo '<label><input type="checkbox" name="' . $this->option_name . '[skip_existing]" value="1" ' . checked(1, $skip_existing, false) . ' /> ';
        echo __('Skip fields that already have content', 'xpm-image-seo') . '</label>';
        echo '<p class="description">' . __('When enabled, only empty fields will be updated. Disable to overwrite existing content.', 'xpm-image-seo') . '</p>';
    }
    
    // Field renders - IMAGE OPTIMIZATION TAB
    public function auto_optimize_render() {
        $options = get_option($this->option_name);
        $auto_optimize = isset($options['auto_optimize']) ? $options['auto_optimize'] : 1; // Default to enabled
        
        echo '<label><input type="checkbox" name="' . $this->option_name . '[auto_optimize]" value="1" ' . checked(1, $auto_optimize, false) . ' /> ';
        echo __('Automatically optimize images when uploaded', 'xpm-image-seo') . '</label>';
        echo '<p class="description">' . __('When enabled, all new image uploads will be automatically optimized according to your settings below.', 'xpm-image-seo') . '</p>';
    }
    
    public function backup_originals_render() {
        $options = get_option($this->option_name);
        $backup = isset($options['backup_originals']) ? $options['backup_originals'] : 1;
        
        echo '<label><input type="checkbox" name="' . $this->option_name . '[backup_originals]" value="1" ' . checked(1, $backup, false) . ' /> ';
        echo __('Keep backup copies of original images', 'xpm-image-seo') . '</label>';
        echo '<p class="description">' . __('Recommended: Creates backup copies in uploads/xpm-backups/ folder. Allows restoration if needed.', 'xpm-image-seo') . '</p>';
    }
    
    public function compression_quality_render() {
        $options = get_option($this->option_name);
        $quality = isset($options['compression_quality']) ? $options['compression_quality'] : 85;
        
        echo '<input type="range" name="' . $this->option_name . '[compression_quality]" value="' . esc_attr($quality) . '" min="60" max="100" step="5" class="compression-slider" />';
        echo '<span class="quality-display">' . $quality . '%</span>';
        echo '<p class="description">' . __('Compression quality: 60% = Maximum compression, 100% = Best quality. Recommended: 85%', 'xpm-image-seo') . '</p>';
        
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const slider = document.querySelector(".compression-slider");
            if (slider) {
                slider.addEventListener("input", function() {
                    const display = document.querySelector(".quality-display");
                    if (display) display.textContent = this.value + "%";
                });
            }
        });
        </script>';
    }
    
    public function max_width_render() {
        $options = get_option($this->option_name);
        $max_width = isset($options['max_width']) ? $options['max_width'] : 2048;
        
        echo '<input type="number" name="' . $this->option_name . '[max_width]" value="' . esc_attr($max_width) . '" min="500" max="4000" step="100" class="small-text" /> px';
        echo '<p class="description">' . __('Images wider than this will be resized. Common values: 1920px (Full HD), 2048px (2K), 2560px (1440p)', 'xpm-image-seo') . '</p>';
    }
    
    public function max_height_render() {
        $options = get_option($this->option_name);
        $max_height = isset($options['max_height']) ? $options['max_height'] : 2048;
        
        echo '<input type="number" name="' . $this->option_name . '[max_height]" value="' . esc_attr($max_height) . '" min="0" max="4000" step="100" class="small-text" /> px';
        echo '<p class="description">' . __('Images taller than this will be resized. Set to 0 to disable height restrictions.', 'xpm-image-seo') . '</p>';
    }
    
    public function convert_to_webp_render() {
        $options = get_option($this->option_name);
        $convert_webp = isset($options['convert_to_webp']) ? $options['convert_to_webp'] : 0;
        
        $webp_supported = function_exists('imagewebp') || (extension_loaded('imagick') && class_exists('Imagick'));
        
        if (!$webp_supported) {
            echo '<input type="checkbox" disabled /> ' . __('Convert images to WebP format', 'xpm-image-seo') . ' <em>(' . __('Not supported on this server', 'xpm-image-seo') . ')</em>';
            echo '<p class="description">' . __('WebP format provides superior compression. Your server does not support WebP conversion.', 'xpm-image-seo') . '</p>';
        } else {
            echo '<label><input type="checkbox" name="' . $this->option_name . '[convert_to_webp]" value="1" ' . checked(1, $convert_webp, false) . ' /> ';
            echo __('Convert images to WebP format for better compression', 'xpm-image-seo') . '</label>';
            echo '<p class="description">' . __('WebP provides 25-35% better compression than JPEG. Creates both original and WebP versions.', 'xpm-image-seo') . '</p>';
        }
    }
    
    // Field renders - PERFORMANCE TAB
    public function enable_lazy_loading_render() {
        $options = get_option($this->option_name);
        $enable_lazy = isset($options['enable_lazy_loading']) ? $options['enable_lazy_loading'] : 0;
        
        echo '<label><input type="checkbox" name="' . $this->option_name . '[enable_lazy_loading]" value="1" ' . checked(1, $enable_lazy, false) . ' /> ';
        echo __('Enable lazy loading for images', 'xpm-image-seo') . '</label>';
        echo '<p class="description">' . __('Images will only load when they come into the viewport, improving page load speed.', 'xpm-image-seo') . '</p>';
    }
    
    public function lazy_loading_threshold_render() {
        $options = get_option($this->option_name);
        $threshold = isset($options['lazy_loading_threshold']) ? $options['lazy_loading_threshold'] : 200;
        
        echo '<input type="number" name="' . $this->option_name . '[lazy_loading_threshold]" value="' . esc_attr($threshold) . '" min="0" max="1000" step="50" class="small-text" /> px';
        echo '<p class="description">' . __('Distance from viewport when images should start loading. 0 = exactly when visible, 200 = 200px before visible.', 'xpm-image-seo') . '</p>';
    }
    
    public function lazy_loading_placeholder_render() {
        $options = get_option($this->option_name);
        $placeholder = isset($options['lazy_loading_placeholder']) ? $options['lazy_loading_placeholder'] : 'blur';
        
        echo '<select name="' . $this->option_name . '[lazy_loading_placeholder]">';
        echo '<option value="blur" ' . selected('blur', $placeholder, false) . '>' . __('Blurred placeholder', 'xpm-image-seo') . '</option>';
        echo '<option value="grey" ' . selected('grey', $placeholder, false) . '>' . __('Grey placeholder', 'xpm-image-seo') . '</option>';
        echo '<option value="transparent" ' . selected('transparent', $placeholder, false) . '>' . __('Transparent', 'xpm-image-seo') . '</option>';
        echo '<option value="custom" ' . selected('custom', $placeholder, false) . '>' . __('Custom placeholder URL', 'xpm-image-seo') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('What to show while images are loading.', 'xpm-image-seo') . '</p>';
        
        $custom_placeholder = isset($options['lazy_loading_custom_placeholder']) ? $options['lazy_loading_custom_placeholder'] : '';
        echo '<div id="custom-placeholder-field" style="margin-top: 10px; ' . ($placeholder !== 'custom' ? 'display: none;' : '') . '">';
        echo '<input type="url" name="' . $this->option_name . '[lazy_loading_custom_placeholder]" value="' . esc_attr($custom_placeholder) . '" class="regular-text" placeholder="https://example.com/placeholder.jpg" />';
        echo '<p class="description">' . __('URL to custom placeholder image.', 'xpm-image-seo') . '</p>';
        echo '</div>';
        
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const select = document.querySelector("select[name=\'' . $this->option_name . '[lazy_loading_placeholder]\']");
            const customField = document.getElementById("custom-placeholder-field");
            if (select && customField) {
                select.addEventListener("change", function() {
                    customField.style.display = this.value === "custom" ? "block" : "none";
                });
            }
        });
        </script>';
    }
    
    public function lazy_loading_effect_render() {
        $options = get_option($this->option_name);
        $effect = isset($options['lazy_loading_effect']) ? $options['lazy_loading_effect'] : 'fade';
        
        echo '<select name="' . $this->option_name . '[lazy_loading_effect]">';
        echo '<option value="fade" ' . selected('fade', $effect, false) . '>' . __('Fade in', 'xpm-image-seo') . '</option>';
        echo '<option value="slide" ' . selected('slide', $effect, false) . '>' . __('Slide in', 'xpm-image-seo') . '</option>';
        echo '<option value="zoom" ' . selected('zoom', $effect, false) . '>' . __('Zoom in', 'xpm-image-seo') . '</option>';
        echo '<option value="none" ' . selected('none', $effect, false) . '>' . __('No effect', 'xpm-image-seo') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('Animation effect when images load.', 'xpm-image-seo') . '</p>';
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
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
     * Settings page with tabs
     */
    public function settings_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'alt_text';
        ?>
        <div class="wrap">
            <h1>
                <?php _e('XPM Image SEO Settings', 'xpm-image-seo'); ?>
                <span class="xpm-brand">by <a href="https://xploited.media" target="_blank">Xploited Media</a></span>
            </h1>
            
            <div class="xpm-tabs">
                <ul>
                    <li><a href="?page=xpm-image-seo-settings&tab=alt_text" class="<?php echo $active_tab === 'alt_text' ? 'active' : ''; ?>"><?php _e('AI Alt Text', 'xpm-image-seo'); ?></a></li>
                    <li><a href="?page=xpm-image-seo-settings&tab=optimization" class="<?php echo $active_tab === 'optimization' ? 'active' : ''; ?>"><?php _e('Image Optimization', 'xpm-image-seo'); ?></a></li>
                    <li><a href="?page=xpm-image-seo-settings&tab=performance" class="<?php echo $active_tab === 'performance' ? 'active' : ''; ?>"><?php _e('Performance', 'xpm-image-seo'); ?></a></li>
                </ul>
            </div>
            
            <div class="xpm-settings-container">
                <div class="xpm-settings-main">
                    <form action="options.php" method="post">
                        <?php settings_fields('xpm_image_seo'); ?>
                        
                        <div id="alt_text_tab" class="xmp-tab-content <?php echo $active_tab === 'alt_text' ? 'active' : ''; ?>">
                            <?php do_settings_sections('xpm_image_seo_alt_text'); ?>
                        </div>
                        
                        <div id="optimization_tab" class="xpm-tab-content <?php echo $active_tab === 'optimization' ? 'active' : ''; ?>">
                            <?php do_settings_sections('xpm_image_seo_optimization'); ?>
                        </div>
                        
                        <div id="performance_tab" class="xpm-tab-content <?php echo $active_tab === 'performance' ? 'active' : ''; ?>">
                            <?php do_settings_sections('xpm_image_seo_performance'); ?>
                        </div>
                        
                        <?php submit_button(__('Save Settings', 'xpm-image-seo')); ?>
                    </form>
                </div>
                
                <div class="xpm-settings-sidebar">
                    <div class="xpm-info-box">
                        <h3><?php _e('Quick Start Guide', 'xpm-image-seo'); ?></h3>
                        <ol>
                            <li><?php _e('Get your OpenAI API key', 'xpm-image-seo'); ?></li>
                            <li><?php _e('Configure optimization settings', 'xpm-image-seo'); ?></li>
                            <li><?php _e('Enable lazy loading for performance', 'xpm-image-seo'); ?></li>
                            <li><?php _e('Save settings', 'xpm-image-seo'); ?></li>
                            <li><?php printf(__('Go to %s to update existing images', 'xpm-image-seo'), '<a href="' . admin_url('upload.php?page=xpm-image-seo-bulk-update') . '">Bulk Update</a>'); ?></li>
                            <li><?php printf(__('Go to %s to optimize images', 'xpm-image-seo'), '<a href="' . admin_url('upload.php?page=xpm-image-seo-optimizer') . '">Image Optimizer</a>'); ?></li>
                        </ol>
                    </div>
                    
                    <div class="xpm-info-box">
                        <h3><?php _e('About XPM Image SEO', 'xpm-image-seo'); ?></h3>
                        <p><?php _e('Complete image SEO and optimization solution. Boost your website\'s SEO and performance with AI-powered alt text, smart image compression, and lazy loading.', 'xpm-image-seo'); ?></p>
                        <p><strong><?php _e('Version:', 'xpm-image-seo'); ?></strong> <?php echo XPM_IMAGE_SEO_VERSION; ?></p>
                        <p><a href="https://xploited.media" target="_blank" class="button"><?php _e('Visit Xploited Media', 'xpm-image-seo'); ?></a></p>
                    </div>
                    
                    <?php if ($active_tab === 'performance'): ?>
                    <div class="xpm-info-box">
                        <h3><?php _e('Lazy Loading Benefits', 'xpm-image-seo'); ?></h3>
                        <ul>
                            <li><?php _e('Faster page load times', 'xpm-image-seo'); ?></li>
                            <li><?php _e('Reduced bandwidth usage', 'xpm-image-seo'); ?></li>
                            <li><?php _e('Better Core Web Vitals scores', 'xpm-image-seo'); ?></li>
                            <li><?php _e('Improved SEO rankings', 'xpm-image-seo'); ?></li>
                            <li><?php _e('Better user experience', 'xpm-image-seo'); ?></li>
                        </ul>
                    </div>
                    <?php endif; ?>
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
                <?php _e('XPM Bulk Alt Text Update', 'xpm-image-seo'); ?>
                <span class="xpm-brand">by <a href="https://xploited.media" target="_blank">Xploited Media</a></span>
            </h1>
            
            <div id="xpm-bulk-update-container">
                <div class="xpm-bulk-controls">
                    <button id="xpm-scan-images" class="button button-primary button-large">
                        <span class="dashicons dashicons-search"></span>
                        <?php _e('Scan for Images Without Alt Text', 'xpm-image-seo'); ?>
                    </button>
                    <button id="xpm-bulk-update-start" class="button button-secondary button-large" disabled>
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Start Bulk Update', 'xpm-image-seo'); ?>
                    </button>
                    <button id="xpm-bulk-update-stop" class="button button-large" style="display: none;">
                        <span class="dashicons dashicons-no"></span>
                        <?php _e('Stop Update', 'xpm-image-seo'); ?>
                    </button>
                </div>
                
                <div id="xpm-scan-results" style="display: none;">
                    <div class="xpm-results-header">
                        <h3><?php _e('Images without Alt Text:', 'xpm-image-seo'); ?> <span id="xpm-images-count" class="count">0</span></h3>
                        <p class="description"><?php _e('These images will benefit from SEO-optimized alt text.', 'xpm-image-seo'); ?></p>
                    </div>
                    <div id="xpm-images-preview"></div>
                </div>
                
                <div id="xpm-progress-container" style="display: none;">
                    <h3><?php _e('Update Progress', 'xpm-image-seo'); ?></h3>
                    <div class="xpm-progress-bar">
                        <div id="xpm-progress-fill"></div>
                    </div>
                    <div class="xpm-progress-info">
                        <span id="xpm-progress-text"><?php _e('0 of 0 images processed', 'xpm-image-seo'); ?></span>
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
                <?php _e('XPM Image Optimizer', 'xpm-image-seo'); ?>
                <span class="xpm-brand">by <a href="https://xploited.media" target="_blank">Xploited Media</a></span>
            </h1>
            
            <div id="xpm-optimizer-container">
                <div class="xpm-optimizer-controls">
                    <button id="xpm-scan-unoptimized" class="button button-primary button-large">
                        <span class="dashicons dashicons-images-alt2"></span>
                        <?php _e('Scan for Unoptimized Images', 'xpm-image-seo'); ?>
                    </button>
                    <button id="xpm-optimize-start" class="button button-secondary button-large" disabled>
                        <span class="dashicons dashicons-performance"></span>
                        <?php _e('Start Bulk Optimization', 'xpm-image-seo'); ?>
                    </button>
                    <button id="xpm-optimize-stop" class="button button-large" style="display: none;">
                        <span class="dashicons dashicons-no"></span>
                        <?php _e('Stop Optimization', 'xpm-image-seo'); ?>
                    </button>
                    <button id="xpm-restore-images" class="button button-link">
                        <span class="dashicons dashicons-backup"></span>
                        <?php _e('Restore from Backups', 'xpm-image-seo'); ?>
                    </button>
                </div>
                
                <div id="xpm-optimizer-stats" class="xpm-stats-grid" style="display: none;">
                    <div class="stat-card">
                        <h3><?php _e('Images Found', 'xpm-image-seo'); ?></h3>
                        <span class="stat-number" id="total-images">0</span>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('Total Size', 'xpm-image-seo'); ?></h3>
                        <span class="stat-number" id="total-size">0 MB</span>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('Potential Savings', 'xpm-image-seo'); ?></h3>
                        <span class="stat-number" id="potential-savings">~0%</span>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('Processing', 'xpm-image-seo'); ?></h3>
                        <span class="stat-number" id="processing-count">0</span>
                    </div>
                </div>
                
                <div id="xpm-optimizer-results" style="display: none;">
                    <div class="xpm-results-header">
                        <h3><?php _e('Images to Optimize:', 'xpm-image-seo'); ?> <span id="xpm-optimizer-count" class="count">0</span></h3>
                        <p class="description"><?php _e('These images can be optimized to improve website performance and loading speeds.', 'xpm-image-seo'); ?></p>
                    </div>
                    <div id="xpm-optimizer-preview"></div>
                </div>
                
                <div id="xpm-optimizer-progress" style="display: none;">
                    <h3><?php _e('Optimization Progress', 'xpm-image-seo'); ?></h3>
                    <div class="xpm-progress-bar">
                        <div id="xpm-optimizer-progress-fill"></div>
                    </div>
                    <div class="xpm-progress-info">
                        <span id="xpm-optimizer-progress-text"><?php _e('0 of 0 images optimized', 'xpm-image-seo'); ?></span>
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
                    <strong><?php _e('XPM Image SEO activated!', 'xpm-image-seo'); ?></strong>
                    <?php printf(__('Configure your settings in %s to get started.', 'xpm-image-seo'), '<a href="' . admin_url('options-general.php?page=xpm-image-seo-settings') . '">' . __('Settings', 'xpm-image-seo') . '</a>'); ?>
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
                    <strong><?php _e('XPM Image SEO:', 'xpm-image-seo'); ?></strong>
                    <?php printf(__('Please configure your OpenAI API key in %s to enable alt text generation.', 'xpm-image-seo'), '<a href="' . admin_url('options-general.php?page=xpm-image-seo-settings') . '">' . __('Settings', 'xpm-image-seo') . '</a>'); ?>
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
            $html = str_replace('<img ', '<img class="xmp-lazy" loading="lazy" ', $html);
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