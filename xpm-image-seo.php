<?php
/**
 * Plugin Name: XPM Image SEO
 * Plugin URI: https://xploited.media/plugins/xpm-image-seo
 * Description: Complete image SEO and optimization solution. Generate AI-powered alt text with smart keywords, plus lossless image compression and resizing for better performance.
 * Version: 1.1.0
 * Author: Xploited Media
 * Author URI: https://xploited.media
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: xpm-image-seo
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('XPM_IMAGE_SEO_VERSION', '1.1.0');
define('XPM_IMAGE_SEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('XPM_IMAGE_SEO_PLUGIN_URL', plugin_dir_url(__FILE__));

class XPM_Image_SEO {
    
    private $option_name = 'xpm_image_seo_settings';
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
        
        // Load required classes
        $this->load_dependencies();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once XPM_IMAGE_SEO_PLUGIN_DIR . 'includes/class-xpm-performance-security.php';
        require_once XPM_IMAGE_SEO_PLUGIN_DIR . 'includes/class-xpm-database.php';
        require_once XPM_IMAGE_SEO_PLUGIN_DIR . 'includes/class-xpm-keywords.php';
        require_once XPM_IMAGE_SEO_PLUGIN_DIR . 'includes/class-xpm-alt-text-generator.php';
        require_once XPM_IMAGE_SEO_PLUGIN_DIR . 'includes/class-xpm-image-optimizer.php';
        require_once XPM_IMAGE_SEO_PLUGIN_DIR . 'includes/class-xpm-ajax-handlers.php';
        require_once XPM_IMAGE_SEO_PLUGIN_DIR . 'includes/class-xpm-media-library.php';
        require_once XPM_IMAGE_SEO_PLUGIN_DIR . 'includes/class-xpm-admin.php';
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('xpm-image-seo', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function activate() {
        $default_options = array(
            // Alt Text Settings
            'api_key' => '',
            'global_keywords' => '',
            'use_contextual_keywords' => 1,
            'keyword_priority' => 'contextual_first',
            'max_keywords' => 3,
            'auto_generate' => 0,
            'prompt' => '',
            'update_title' => 1,
            'update_description' => 1,
            'skip_existing' => 1,
            
            // Image Optimization Settings (AUTO-OPTIMIZE NOW ENABLED BY DEFAULT)
            'auto_optimize' => 1,  // CHANGED: Now enabled by default
            'compression_quality' => 85,
            'max_width' => 2048,
            'max_height' => 2048,
            'backup_originals' => 1,
            'convert_to_webp' => 0,
            
            // Performance Settings (NEW: Lazy Loading)
            'enable_lazy_loading' => 0,
            'lazy_loading_threshold' => 200,
            'lazy_loading_placeholder' => 'blur',
            'lazy_loading_custom_placeholder' => '',
            'lazy_loading_effect' => 'fade'
        );
        
        add_option($this->option_name, $default_options);
        
        // Create database tables
        XPM_Database::create_database_tables();
        
        // Create backup directory
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/xpm-backups';
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
            $htaccess_content = "Options -Indexes\nDeny from all";
            file_put_contents($backup_dir . '/.htaccess', $htaccess_content);
        }
        
        // Schedule cleanup
        if (!wp_next_scheduled('xpm_image_seo_cleanup')) {
            wp_schedule_event(time(), 'weekly', 'xpm_image_seo_cleanup');
        }
        
        set_transient('xpm_image_seo_activated', true, 30);
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('xpm_image_seo_cleanup');
        wp_clear_scheduled_hook('xpm_optimize_image');
        wp_clear_scheduled_hook('xpm_generate_alt_text');
    }
    
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=xpm-image-seo-settings') . '">' . __('Settings', 'xpm-image-seo') . '</a>';
        $bulk_update_link = '<a href="' . admin_url('upload.php?page=xpm-image-seo-bulk-update') . '">' . __('Bulk Update', 'xpm-image-seo') . '</a>';
        $optimizer_link = '<a href="' . admin_url('upload.php?page=xpm-image-seo-optimizer') . '">' . __('Optimizer', 'xpm-image-seo') . '</a>';
        array_unshift($links, $settings_link, $bulk_update_link, $optimizer_link);
        return $links;
    }
    
    public function init() {
        // Initialize error handler
        XPM_Error_Handler::init();
        
        // Initialize components
        new XPM_AJAX_Handlers();
        new XPM_Media_Library_Integration();
        new XPM_Dashboard_Widget();
        
        // Auto-processing hooks
        add_action('add_attachment', array($this, 'auto_optimize_on_upload'));
        add_action('add_attachment', array($this, 'auto_generate_alt_text'));
        
        // Scheduled event handlers
        add_action('xpm_optimize_image', array($this, 'optimize_single_image'));
        add_action('xpm_generate_alt_text', array($this, 'generate_alt_text_async'));
        add_action('xpm_image_seo_cleanup', array($this, 'scheduled_cleanup'));
        
        // Admin interface
        if (is_admin()) {
            new XPM_Image_SEO_Admin();
        }
    }
    
    /**
     * Auto-optimize images on upload
     */
    public function auto_optimize_on_upload($attachment_id) {
        $options = get_option($this->option_name);
        
        if (!isset($options['auto_optimize']) || !$options['auto_optimize']) {
            return;
        }
        
        if (!wp_attachment_is_image($attachment_id)) {
            return;
        }
        
        wp_schedule_single_event(time() + 5, 'xpm_optimize_image', array($attachment_id));
    }
    
    /**
     * Auto-generate alt text on upload
     */
    public function auto_generate_alt_text($attachment_id) {
        $options = get_option($this->option_name);
        
        if (!isset($options['auto_generate']) || !$options['auto_generate']) {
            return;
        }
        
        if (!wp_attachment_is_image($attachment_id)) {
            return;
        }
        
        $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if (!empty($existing_alt)) {
            return;
        }
        
        wp_schedule_single_event(time() + 10, 'xpm_generate_alt_text', array($attachment_id));
    }
    
    /**
     * Handle scheduled image optimization
     */
    public function optimize_single_image($attachment_id) {
        $optimizer = new XPM_Image_Optimizer();
        $result = $optimizer->optimize_single_image($attachment_id);
        
        if (!$result['success']) {
            XPM_Error_Handler::log_error('Scheduled optimization failed: ' . $result['message'], array(
                'attachment_id' => $attachment_id
            ));
        }
    }
    
    /**
     * Handle scheduled alt text generation
     */
    public function generate_alt_text_async($attachment_id) {
        $generator = new XPM_Alt_Text_Generator();
        $result = $generator->generate_alt_text($attachment_id);
        
        if (!$result['success']) {
            XPM_Error_Handler::log_error('Scheduled alt text generation failed: ' . $result['message'], array(
                'attachment_id' => $attachment_id
            ));
        }
    }
    
    /**
     * Scheduled cleanup tasks
     */
    public function scheduled_cleanup() {
        // Clean up old logs (90 days)
        XPM_Database::cleanup_old_logs(90);
        
        // Clean up old backups (30 days)
        $optimizer = new XPM_Image_Optimizer();
        $optimizer->cleanup_old_backups();
        
        // Clean up rate limit data
        delete_option('xpm_rate_limit_data');
    }
}

/**
 * Dashboard widget for XPM Image SEO
 */
class XPM_Dashboard_Widget {
    
    public function __construct() {
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }
    
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'xpm_image_seo_dashboard',
            __('XPM Image SEO Stats', 'xpm-image-seo'),
            array($this, 'display_dashboard_widget')
        );
    }
    
    public function display_dashboard_widget() {
        // Get quick stats
        global $wpdb;
        
        $total_images = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type LIKE 'image/%'
        ");
        
        $images_with_alt = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID) 
            FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type = 'attachment' 
            AND p.post_mime_type LIKE 'image/%'
            AND pm.meta_key = '_wp_attachment_image_alt' 
            AND pm.meta_value != ''
        ");
        
        $optimized_images = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID) 
            FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type = 'attachment' 
            AND p.post_mime_type LIKE 'image/%'
            AND pm.meta_key = '_xpm_optimized' 
            AND pm.meta_value = '1'
        ");
        
        $alt_coverage = $total_images > 0 ? round(($images_with_alt / $total_images) * 100) : 0;
        $opt_coverage = $total_images > 0 ? round(($optimized_images / $total_images) * 100) : 0;
        
        // Get recent stats
        $stats = XPM_Database::get_optimization_statistics();
        $total_saved = $stats['optimization']['total_bytes_saved'] ?? 0;
        
        ?>
        <div class="xpm-dashboard-stats">
            <style>
                .xpm-dashboard-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
                .xpm-stat-box { text-align: center; padding: 15px; background: #f8f9fa; border-radius: 6px; }
                .xpm-stat-number { font-size: 24px; font-weight: bold; color: #0073aa; }
                .xpm-stat-label { font-size: 12px; color: #666; margin-top: 5px; }
                .xpm-progress-bar { background: #e0e0e0; height: 8px; border-radius: 4px; margin: 8px 0; }
                .xpm-progress-fill { height: 100%; background: #46b450; border-radius: 4px; transition: width 0.3s; }
                .xpm-dashboard-links { margin-top: 15px; }
                .xpm-dashboard-links .button { margin-right: 10px; }
            </style>
            
            <div class="xpm-stat-box">
                <div class="xpm-stat-number"><?php echo number_format($total_images); ?></div>
                <div class="xpm-stat-label"><?php _e('Total Images', 'xpm-image-seo'); ?></div>
            </div>
            
            <div class="xpm-stat-box">
                <div class="xpm-stat-number"><?php echo $alt_coverage; ?>%</div>
                <div class="xpm-stat-label"><?php _e('Alt Text Coverage', 'xpm-image-seo'); ?></div>
                <div class="xpm-progress-bar">
                    <div class="xpm-progress-fill" style="width: <?php echo $alt_coverage; ?>%;"></div>
                </div>
            </div>
            
            <div class="xpm-stat-box">
                <div class="xpm-stat-number"><?php echo number_format($optimized_images); ?></div>
                <div class="xpm-stat-label"><?php _e('Optimized Images', 'xpm-image-seo'); ?></div>
            </div>
            
            <div class="xpm-stat-box">
                <div class="xpm-stat-number"><?php echo $total_saved > 0 ? size_format($total_saved) : '0 MB'; ?></div>
                <div class="xpm-stat-label"><?php _e('Total Saved', 'xpm-image-seo'); ?></div>
            </div>
        </div>
        
        <div class="xpm-dashboard-links">
            <a href="<?php echo admin_url('upload.php?page=xpm-image-seo-bulk-update'); ?>" class="button button-primary button-small">
                <?php _e('Bulk Update Alt Text', 'xpm-image-seo'); ?>
            </a>
            <a href="<?php echo admin_url('upload.php?page=xpm-image-seo-optimizer'); ?>" class="button button-secondary button-small">
                <?php _e('Optimize Images', 'xpm-image-seo'); ?>
            </a>
            <a href="<?php echo admin_url('options-general.php?page=xpm-image-seo-settings'); ?>" class="button button-small">
                <?php _e('Settings', 'xpm-image-seo'); ?>
            </a>
        </div>
        
        <?php
        // Show recent activity
        $recent_activity = XPM_Database::get_recent_activity(5);
        if (!empty($recent_activity)) {
            echo '<h4>' . __('Recent Activity', 'xpm-image-seo') . '</h4>';
            echo '<ul style="margin: 0; padding-left: 20px; font-size: 12px;">';
            foreach ($recent_activity as $activity) {
                $icon = $activity->type === 'optimization' ? '🔧' : '✏️';
                $time_ago = human_time_diff(strtotime($activity->created_at), current_time('timestamp'));
                echo '<li>' . $icon . ' ' . esc_html($activity->description) . ' <small>(' . $time_ago . ' ago)</small></li>';
            }
            echo '</ul>';
        }
        ?>
        <?php
    }
}

// Initialize the plugin
new XPM_Image_SEO();