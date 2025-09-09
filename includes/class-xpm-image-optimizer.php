<?php
/**
 * XPM Image SEO Image Optimizer
 * 
 * @package XPM_Image_SEO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle image optimization functionality
 */
class XPM_Image_Optimizer {
    
    private $option_name = 'xpm_image_seo_settings';
    
    public function __construct() {
        // Hook for scheduled optimization
        add_action('xpm_optimize_image', array($this, 'optimize_single_image'));
    }
    
    /**
     * Optimize single image
     */
    public function optimize_single_image($attachment_id) {
        $start_time = microtime(true);
        $options = get_option($this->option_name);
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            return array('success' => false, 'message' => __('File not found', 'xpm-image-seo'));
        }
        
        // Validate image file
        if (!XPM_Security::validate_image_file($file_path)) {
            return array('success' => false, 'message' => __('Invalid image file', 'xpm-image-seo'));
        }
        
        // Check if already optimized
        $already_optimized = get_post_meta($attachment_id, '_xpm_optimized', true);
        if ($already_optimized) {
            return array('success' => false, 'message' => __('Already optimized', 'xpm-image-seo'));
        }
        
        $original_size = filesize($file_path);
        $backup_created = false;
        
        try {
            // Create backup if enabled
            if (!empty($options['backup_originals'])) {
                $backup_created = $this->create_backup($file_path, $attachment_id);
            }
            
            // Get current image info
            $image_info = getimagesize($file_path);
            if (!$image_info) {
                throw new Exception(__('Invalid image file', 'xpm-image-seo'));
            }
            
            list($width, $height, $type) = $image_info;
            $max_width = intval($options['max_width'] ?? 2048);
            $max_height = intval($options['max_height'] ?? 2048);
            $quality = intval($options['compression_quality'] ?? 85);
            
            // Check if resizing is needed
            $new_width = $width;
            $new_height = $height;
            $resized = false;
            
            if ($width > $max_width || ($max_height > 0 && $height > $max_height)) {
                if ($max_height > 0) {
                    $ratio = min($max_width / $width, $max_height / $height);
                } else {
                    $ratio = $max_width / $width;
                }
                
                $new_width = round($width * $ratio);
                $new_height = round($height * $ratio);
                $resized = true;
            }
            
            // Process image using available library
            if (extension_loaded('imagick') && class_exists('Imagick')) {
                $result = $this->optimize_with_imagick($file_path, $new_width, $new_height, $quality, $type, $resized);
            } else {
                $result = $this->optimize_with_gd($file_path, $new_width, $new_height, $quality, $type, $resized);
            }
            
            if (!$result) {
                throw new Exception(__('Could not save optimized image', 'xpm-image-seo'));
            }
            
            // Convert to WebP if enabled
            $webp_path = null;
            if (!empty($options['convert_to_webp']) && $type != IMAGETYPE_WEBP) {
                $webp_path = $this->convert_to_webp($file_path, $quality);
            }
            
            $new_size = filesize($file_path);
            $savings = $original_size - $new_size;
            $savings_percent = $original_size > 0 ? round(($savings / $original_size) * 100) : 0;
            $processing_time = microtime(true) - $start_time;
            
            // Update metadata
            update_post_meta($attachment_id, '_xpm_optimized', 1);
            update_post_meta($attachment_id, '_xpm_original_size', $original_size);
            update_post_meta($attachment_id, '_xpm_optimized_size', $new_size);
            update_post_meta($attachment_id, '_xpm_bytes_saved', $savings);
            update_post_meta($attachment_id, '_xpm_optimization_date', current_time('mysql'));
            update_post_meta($attachment_id, '_xpm_processing_time', $processing_time);
            
            if ($resized) {
                update_post_meta($attachment_id, '_xpm_resized', 1);
                update_post_meta($attachment_id, '_xpm_original_dimensions', $width . 'x' . $height);
            }
            
            if ($webp_path) {
                update_post_meta($attachment_id, '_xpm_webp_path', $webp_path);
            }
            
            // Clear cache
            XPM_Cache::clear_image_cache($attachment_id);
            
            // Regenerate WordPress thumbnails
            wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $file_path));
            
            return array(
                'success' => true,
                'original_size' => $original_size,
                'new_size' => $new_size,
                'savings' => $savings,
                'savings_percent' => $savings_percent,
                'resized' => $resized,
                'backup_created' => $backup_created,
                'webp_created' => !empty($webp_path),
                'webp_path' => $webp_path,
                'processing_time' => $processing_time
            );
            
        } catch (Exception $e) {
            XPM_Error_Handler::log_error('Image optimization failed: ' . $e->getMessage(), array(
                'attachment_id' => $attachment_id,
                'file_path' => $file_path
            ));
            
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Optimize image using Imagick
     */
    private function optimize_with_imagick($file_path, $new_width, $new_height, $quality, $type, $resized) {
        try {
            $imagick = new Imagick($file_path);
            
            // Strip metadata to reduce file size
            $imagick->stripImage();
            
            // Resize if needed
            if ($resized) {
                $imagick->resizeImage($new_width, $new_height, Imagick::FILTER_LANCZOS, 1);
            }
            
            // Set compression quality
            $imagick->setImageCompressionQuality($quality);
            
            // Optimize based on image type
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $imagick->setImageFormat('jpeg');
                    $imagick->setInterlaceScheme(Imagick::INTERLACE_PLANE);
                    break;
                case IMAGETYPE_PNG:
                    $imagick->setImageFormat('png');
                    // PNG specific optimizations
                    break;
                case IMAGETYPE_WEBP:
                    $imagick->setImageFormat('webp');
                    break;
            }
            
            // Write optimized image
            $result = $imagick->writeImage($file_path);
            $imagick->destroy();
            
            return $result;
            
        } catch (Exception $e) {
            XPM_Error_Handler::log_error('Imagick optimization failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Optimize image using GD
     */
    private function optimize_with_gd($file_path, $new_width, $new_height, $quality, $type, $resized) {
        try {
            // Load image based on type
            $image_resource = null;
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $image_resource = imagecreatefromjpeg($file_path);
                    break;
                case IMAGETYPE_PNG:
                    $image_resource = imagecreatefrompng($file_path);
                    break;
                case IMAGETYPE_WEBP:
                    if (function_exists('imagecreatefromwebp')) {
                        $image_resource = imagecreatefromwebp($file_path);
                    }
                    break;
                case IMAGETYPE_GIF:
                    $image_resource = imagecreatefromgif($file_path);
                    break;
            }
            
            if (!$image_resource) {
                return false;
            }
            
            // Create new image if resizing is needed
            if ($resized) {
                $new_image = imagecreatetruecolor($new_width, $new_height);
                
                // Preserve transparency for PNG/GIF
                if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
                    imagealphablending($new_image, false);
                    imagesavealpha($new_image, true);
                    $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
                    imagefill($new_image, 0, 0, $transparent);
                }
                
                imagecopyresampled($new_image, $image_resource, 0, 0, 0, 0, $new_width, $new_height, imagesx($image_resource), imagesy($image_resource));
                imagedestroy($image_resource);
                $image_resource = $new_image;
            }
            
            // Save optimized image
            $save_success = false;
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $save_success = imagejpeg($image_resource, $file_path, $quality);
                    break;
                case IMAGETYPE_PNG:
                    // PNG compression level (0-9, where 9 is maximum compression)
                    $png_quality = round((100 - $quality) / 10);
                    $save_success = imagepng($image_resource, $file_path, $png_quality);
                    break;
                case IMAGETYPE_WEBP:
                    if (function_exists('imagewebp')) {
                        $save_success = imagewebp($image_resource, $file_path, $quality);
                    }
                    break;
                case IMAGETYPE_GIF:
                    $save_success = imagegif($image_resource, $file_path);
                    break;
            }
            
            imagedestroy($image_resource);
            return $save_success;
            
        } catch (Exception $e) {
            XPM_Error_Handler::log_error('GD optimization failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create backup of original image
     */
    private function create_backup($file_path, $attachment_id) {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/xpm-backups';
        
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
            // Create .htaccess to protect backups
            $htaccess_content = "Options -Indexes\nDeny from all";
            file_put_contents($backup_dir . '/.htaccess', $htaccess_content);
        }
        
        $file_info = pathinfo($file_path);
        $backup_filename = $attachment_id . '_' . time() . '.' . $file_info['extension'];
        $backup_path = $backup_dir . '/' . $backup_filename;
        
        if (copy($file_path, $backup_path)) {
            update_post_meta($attachment_id, '_xpm_backup_path', $backup_path);
            return true;
        }
        
        return false;
    }
    
    /**
     * Convert image to WebP format
     */
    private function convert_to_webp($file_path, $quality = 85) {
        if (!function_exists('imagewebp')) {
            return false;
        }
        
        $file_info = pathinfo($file_path);
        $webp_path = $file_info['dirname'] . '/' . $file_info['filename'] . '.webp';
        
        // Load original image
        $image_info = getimagesize($file_path);
        if (!$image_info) return false;
        
        $image_resource = null;
        switch ($image_info[2]) {
            case IMAGETYPE_JPEG:
                $image_resource = imagecreatefromjpeg($file_path);
                break;
            case IMAGETYPE_PNG:
                $image_resource = imagecreatefrompng($file_path);
                // Preserve transparency
                imagealphablending($image_resource, false);
                imagesavealpha($image_resource, true);
                break;
            default:
                return false;
        }
        
        if (!$image_resource) return false;
        
        $success = imagewebp($image_resource, $webp_path, $quality);
        imagedestroy($image_resource);
        
        return $success ? $webp_path : false;
    }
    
    /**
     * Restore image from backup
     */
    public function restore_from_backup($attachment_id) {
        $backup_path = get_post_meta($attachment_id, '_xpm_backup_path', true);
        
        if (!$backup_path || !file_exists($backup_path)) {
            return array('success' => false, 'message' => __('Backup not found', 'xpm-image-seo'));
        }
        
        $original_path = get_attached_file($attachment_id);
        if (!$original_path) {
            return array('success' => false, 'message' => __('Original file not found', 'xpm-image-seo'));
        }
        
        if (copy($backup_path, $original_path)) {
            // Remove optimization metadata
            delete_post_meta($attachment_id, '_xpm_optimized');
            delete_post_meta($attachment_id, '_xpm_original_size');
            delete_post_meta($attachment_id, '_xpm_optimized_size');
            delete_post_meta($attachment_id, '_xpm_bytes_saved');
            delete_post_meta($attachment_id, '_xpm_resized');
            delete_post_meta($attachment_id, '_xpm_webp_path');
            delete_post_meta($attachment_id, '_xpm_optimization_date');
            delete_post_meta($attachment_id, '_xpm_processing_time');
            
            // Clear cache
            XPM_Cache::clear_image_cache($attachment_id);
            
            // Regenerate thumbnails
            wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $original_path));
            
            return array('success' => true, 'message' => __('Image restored from backup', 'xpm-image-seo'));
        }
        
        return array('success' => false, 'message' => __('Failed to restore from backup', 'xpm-image-seo'));
    }
    
    /**
     * Get optimization statistics
     */
    public function get_unoptimized_images() {
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_xpm_optimized',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        
        return get_posts($args);
    }
    
    /**
     * Cleanup old backups (run via cron)
     */
    public function cleanup_old_backups() {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/xpm-backups';
        
        if (!file_exists($backup_dir)) {
            return;
        }
        
        $files = glob($backup_dir . '/*');
        $cutoff_time = time() - (30 * 24 * 60 * 60); // 30 days ago
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
}