<?php
/**
 * XPM Image SEO Admin Interface
 * 
 * @package XPM_Image_SEO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin interface functionality
 */
class XPM_Image_SEO_Admin {
    
    private $option_name = 'xpm_image_seo_settings';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'admin_notices'));
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
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (!in_array($hook, array('settings_page_xpm-image-seo-settings', 'media_page_xpm-image-seo-bulk-update'))) {
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
            'strings' => array(
                'scanning' => __('Scanning...', 'xpm-image-seo'),
                'scan_button' => __('Scan for Images Without Alt Text', 'xpm-image-seo'),
                'no_images' => __('No images to update', 'xpm-image-seo'),
                'stopped' => __('Bulk update stopped by user', 'xpm-image-seo'),
                'completed' => __('Bulk update completed!', 'xpm-image-seo'),
                'network_error' => __('Network error', 'xpm-image-seo'),
                'scan_failed' => __('Failed to scan images', 'xpm-image-seo'),
                'confirm_stop' => __('Are you sure you want to stop the bulk update?', 'xpm-image-seo')
            )
        ));
        
        wp_enqueue_style(
            'xpm-image-seo-admin', 
            XPM_IMAGE_SEO_PLUGIN_URL . 'assets/css/admin.css', 
            array(), 
            XPM_IMAGE_SEO_VERSION
        );
    }
    
    /**
     * Initialize settings
     */
    public function settings_init() {
        register_setting('xpm_image_seo', $this->option_name, array($this, 'sanitize_settings'));
        
        add_settings_section(
            'xpm_image_seo_api_section',
            __('OpenAI API Configuration', 'xpm-image-seo'),
            array($this, 'api_section_callback'),
            'xpm_image_seo'
        );
        
        add_settings_section(
            'xpm_image_seo_behavior_section',
            __('Behavior Settings', 'xpm-image-seo'),
            array($this, 'behavior_section_callback'),
            'xpm_image_seo'
        );
        
        add_settings_field(
            'api_key',
            __('OpenAI API Key', 'xpm-image-seo'),
            array($this, 'api_key_render'),
            'xpm_image_seo',
            'xmp_image_seo_api_section'
        );
        
        add_settings_field(
            'auto_generate',
            __('Auto-generate for new uploads', 'xpm-image-seo'),
            array($this, 'auto_generate_render'),
            'xpm_image_seo',
            'xpm_image_seo_behavior_section'
        );
        
        add_settings_field(
            'prompt',
            __('Custom Prompt', 'xpm-image-seo'),
            array($this, 'prompt_render'),
            'xpm_image_seo',
            'xpm_image_seo_behavior_section'
        );
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field($input['api_key']);
        }
        
        if (isset($input['auto_generate'])) {
            $sanitized['auto_generate'] = intval($input['auto_generate']);
        }
        
        if (isset($input['prompt'])) {
            $sanitized['prompt'] = sanitize_textarea_field($input['prompt']);
        }
        
        return $sanitized;
    }
    
    /**
     * API section callback
     */
    public function api_section_callback() {
        echo '<p>' . __('Configure your OpenAI API settings to enable automatic alt text generation.', 'xpm-image-seo') . '</p>';
    }
    
    /**
     * Behavior section callback
     */
    public function behavior_section_callback() {
        echo '<p>' . __('Control how XPM Image SEO behaves when processing your images.', 'xpm-image-seo') . '</p>';
    }
    
    /**
     * Render API key field
     */
    public function api_key_render() {
        $options = get_option($this->option_name);
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        $masked_key = !empty($api_key) ? str_repeat('*', strlen($api_key) - 8) . substr($api_key, -8) : '';
        
        echo '<input type="password" name="' . $this->option_name . '[api_key]" value="' . esc_attr($api_key) . '" class="regular-text" autocomplete="new-password" />';
        if (!empty($masked_key)) {
            echo '<p class="description">' . sprintf(__('Current key: %s', 'xpm-image-seo'), $masked_key) . '</p>';
        }
        echo '<p class="description">' . sprintf(
            __('Get your API key from %s. Make sure you have sufficient credits in your OpenAI account.', 'xpm-image-seo'),
            '<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>'
        ) . '</p>';
    }
    
    /**
     * Render auto-generate field
     */
    public function auto_generate_render() {
        $options = get_option($this->option_name);
        $auto_generate = isset($options['auto_generate']) ? $options['auto_generate'] : 0;
        
        echo '<label><input type="checkbox" name="' . $this->option_name . '[auto_generate]" value="1" ' . checked(1, $auto_generate, false) . ' /> ';
        echo __('Automatically generate alt text for new image uploads', 'xpm-image-seo') . '</label>';
        echo '<p class="description">' . __('When enabled, alt text will be automatically generated for images uploaded to your media library (only if they don\'t already have alt text).', 'xpm-image-seo') . '</p>';
    }
    
    /**
     * Render prompt field
     */
    public function prompt_render() {
        $options = get_option($this->option_name);
        $prompt = isset($options['prompt']) ? $options['prompt'] : '';
        
        echo '<textarea name="' . $this->option_name . '[prompt]" rows="4" class="large-text">' . esc_textarea($prompt) . '</textarea>';
        echo '<p class="description">' . __('Customize the prompt sent to OpenAI. Leave blank to use the default SEO-optimized prompt. The prompt should instruct the AI on how to analyze images and generate alt text.', 'xpm-image-seo') . '</p>';
        
        $default_prompt = __("Analyze this image and provide a concise, SEO-friendly alt text description. Focus on the main subject, important visual elements, and context that would be valuable for both screen readers and search engines. Keep it descriptive but under 125 characters.", 'xpm-image-seo');
        echo '<details><summary>' . __('Show default prompt', 'xpm-image-seo') . '</summary><code>' . esc_html($default_prompt) . '</code></details>';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>
                <?php _e('XPM Image SEO Settings', 'xpm-image-seo'); ?>
                <span class="xpm-brand">by <a href="https://xploited.media" target="_blank">Xploited Media</a></span>
            </h1>
            
            <div class="xpm-settings-container">
                <div class="xpm-settings-main">
                    <form action="options.php" method="post">
                        <?php
                        settings_fields('xpm_image_seo');
                        do_settings_sections('xpm_image_seo');
                        submit_button(__('Save Settings', 'xpm-image-seo'));
                        ?>
                    </form>
                </div>
                
                <div class="xpm-settings-sidebar">
                    <div class="xpm-info-box">
                        <h3><?php _e('Quick Start Guide', 'xpm-image-seo'); ?></h3>
                        <ol>
                            <li><?php _e('Get your OpenAI API key', 'xpm-image-seo'); ?></li>
                            <li><?php _e('Enter it in the field above', 'xpm-image-seo'); ?></li>
                            <li><?php _e('Save settings', 'xpm-image-seo'); ?></li>
                            <li><?php printf(__('Go to %s to update existing images', 'xpm-image-seo'), '<a href="' . admin_url('upload.php?page=xpm-image-seo-bulk-update') . '">Bulk Update</a>'); ?></li>
                        </ol>
                    </div>
                    
                    <div class="xpm-info-box">
                        <h3><?php _e('About XPM Image SEO', 'xpm-image-seo'); ?></h3>
                        <p><?php _e('Boost your website\'s SEO and accessibility with AI-powered alt text generation.', 'xpm-image-seo'); ?></p>
                        <p><strong><?php _e('Version:', 'xpm-image-seo'); ?></strong> <?php echo XPM_IMAGE_SEO_VERSION; ?></p>
                        <p><a href="https://xploited.media" target="_blank" class="button"><?php _e('Visit Xploited Media', 'xpm-image-seo'); ?></a></p>
                    </div>
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
                        <p class="description"><?php _e('These images will benefit from SEO-optimized alt text to improve accessibility and search engine rankings.', 'xpm-image-seo'); ?></p>
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
                        <span id="xmp-estimated-time"></span>
                    </div>
                </div>
                
                <div id="xpm-results-log"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Show activation notice
        if (get_transient('xmp_image_seo_activated')) {
            delete_transient('xmp_image_seo_activated');
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php _e('XPM Image SEO activated!', 'xpm-image-seo'); ?></strong>
                    <?php printf(__('Configure your OpenAI API key in %s to get started.', 'xpm-image-seo'), '<a href="' . admin_url('options-general.php?page=xpm-image-seo-settings') . '">' . __('Settings', 'xpm-image-seo') . '</a>'); ?>
                </p>
            </div>
            <?php
        }
        
        // Check if API key is configured
        $options = get_option($this->option_name);
        $api_key = isset($options['api_key']) ? trim($options['api_key']) : '';
        
        if (empty($api_key) && isset($_GET['page']) && in_array($_GET['page'], array('xpm-image-seo-settings', 'xpm-image-seo-bulk-update'))) {
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
}