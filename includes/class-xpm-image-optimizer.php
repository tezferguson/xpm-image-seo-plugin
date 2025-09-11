<?php
/**
 * XPM Image SEO Image Optimizer - COMPLETE ALL SIZES VERSION WITH PNG TO JPEG CONVERSION
 * 
 * @package XPM_Image_SEO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle image optimization functionality - Optimizes ALL image sizes with PNG to JPEG conversion
 */
class XPM_Image_Optimizer {
    
    private $option_name = 'xpm_image_seo_settings';
    
    public function __construct() {
        // Hook for scheduled optimization
        add_action('xpm_optimize_image', array($this, 'optimize_single_image'));
        
        // Force cache clearing after optimization
        add_action('wp_update_attachment_metadata', array($this, 'clear_all_caches'), 10, 2);
        
        // Add filter to serve optimized images
        add_filter('wp_get_attachment_url', array($this, 'force_optimized_url'), 10, 2);
    }
    
    /**
     * Force optimized image URL to include timestamp to break cache
     */
    public function force_optimized_url($url, $attachment_id) {
        $optimized = get_post_meta($attachment_id, '_xpm_optimized', true);
        $optimization_date = get_post_meta($attachment_id, '_xpm_optimization_date', true);
        
        if ($optimized && $optimization_date) {
            $timestamp = strtotime($optimization_date);
            $separator = strpos($url, '?') !== false ? '&' : '?';
            $url = $url . $separator . 'xmp_v=' . $timestamp;
        }
        
        return $url;
    }
    
    /**
     * Clear all caches after attachment metadata update
     */
    public function clear_all_caches($data, $attachment_id) {
        // Clear WordPress object cache
        wp_cache_delete($attachment_id, 'posts');
        wp_cache_delete($attachment_id, 'post_meta');
        
        // Clear any caching plugins
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Clear popular caching plugins
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        
        return $data;
    }
    
    /**
     * Check if PNG has transparency
     */
    private function png_has_transparency($file_path) {
        try {
            // Try with Imagick first (more reliable)
            if (extension_loaded('imagick') && class_exists('Imagick')) {
                $imagick = new Imagick($file_path);
                $has_alpha = $imagick->getImageAlphaChannel() !== Imagick::ALPHACHANNEL_UNDEFINED;
                
                if ($has_alpha) {
                    // Check if alpha channel is actually used
                    $histogram = $imagick->getImageHistogram();
                    foreach ($histogram as $pixel) {
                        if ($pixel->getColorValue(Imagick::COLOR_ALPHA) < 1.0) {
                            $imagick->destroy();
                            return true; // Has transparency
                        }
                    }
                }
                
                $imagick->destroy();
                return false;
            }
            
            // Fallback to GD
            if (extension_loaded('gd')) {
                $image = imagecreatefrompng($file_path);
                if (!$image) {
                    return true; // Assume has transparency if can't read
                }
                
                $width = imagesx($image);
                $height = imagesy($image);
                
                // Sample check - check every 10th pixel to avoid performance issues
                for ($x = 0; $x < $width; $x += 10) {
                    for ($y = 0; $y < $height; $y += 10) {
                        $rgba = imagecolorat($image, $x, $y);
                        $alpha = ($rgba & 0x7F000000) >> 24;
                        
                        if ($alpha > 0) {
                            imagedestroy($image);
                            return true; // Has transparency
                        }
                    }
                }
                
                imagedestroy($image);
                return false;
            }
            
            // If no image libraries available, assume has transparency for safety
            return true;
            
        } catch (Exception $e) {
            error_log('PNG transparency check failed: ' . $e->getMessage());
            return true; // Assume has transparency on error for safety
        }
    }
    
    /**
     * Convert PNG to JPEG if it doesn't have transparency
     */
    private function convert_png_to_jpeg($file_path, $quality = 85) {
        try {
            // First check if PNG has transparency
            if ($this->png_has_transparency($file_path)) {
                error_log("PNG to JPEG conversion skipped: image has transparency - $file_path");
                return false; // Skip conversion to preserve transparency
            }
            
            $file_info = pathinfo($file_path);
            $jpeg_path = $file_info['dirname'] . '/' . $file_info['filename'] . '.jpg';
            
            // Convert using Imagick if available
            if (extension_loaded('imagick') && class_exists('Imagick')) {
                $imagick = new Imagick($file_path);
                
                // Set white background for PNG conversion
                $imagick->setImageBackgroundColor('#ffffff');
                $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                
                // Convert to JPEG
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality($quality);
                $imagick->setInterlaceScheme(Imagick::INTERLACE_PLANE);
                $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
                
                $success = $imagick->writeImage($jpeg_path);
                $imagick->destroy();
                
                if ($success && file_exists($jpeg_path)) {
                    error_log("PNG to JPEG conversion successful: $file_path -> $jpeg_path");
                    return $jpeg_path;
                }
            }
            
            // Fallback to GD
            if (extension_loaded('gd')) {
                $png_image = imagecreatefrompng($file_path);
                if (!$png_image) {
                    return false;
                }
                
                $width = imagesx($png_image);
                $height = imagesy($png_image);
                
                // Create a white background
                $jpeg_image = imagecreatetruecolor($width, $height);
                $white = imagecolorallocate($jpeg_image, 255, 255, 255);
                imagefill($jpeg_image, 0, 0, $white);
                
                // Copy PNG onto white background
                imagecopy($jpeg_image, $png_image, 0, 0, 0, 0, $width, $height);
                
                // Save as JPEG
                $success = imagejpeg($jpeg_image, $jpeg_path, $quality);
                
                imagedestroy($png_image);
                imagedestroy($jpeg_image);
                
                if ($success && file_exists($jpeg_path)) {
                    error_log("PNG to JPEG conversion successful (GD): $file_path -> $jpeg_path");
                    return $jpeg_path;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('PNG to JPEG conversion failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Optimize single image and ALL its sizes - COMPLETE VERSION WITH PNG TO JPEG
     */
    public function optimize_single_image($attachment_id) {
        error_log("=== XPM COMPLETE Optimization Debug Start for ID: $attachment_id ===");
        
        $start_time = microtime(true);
        $options = get_option($this->option_name);
        $file_path = get_attached_file($attachment_id);
        
        error_log("Main file path: " . $file_path);
        error_log("Original URL: " . wp_get_attachment_url($attachment_id));
        
        if (!$file_path || !file_exists($file_path)) {
            error_log("ERROR: File not found - $file_path");
            return array('success' => false, 'message' => __('File not found', 'xpm-image-seo'));
        }
        
        // Validate image file
        if (class_exists('XPM_Security') && !XPM_Security::validate_image_file($file_path)) {
            error_log("ERROR: Invalid image file - $file_path");
            return array('success' => false, 'message' => __('Invalid image file', 'xpm-image-seo'));
        }
        
        // Check if already optimized
        $already_optimized = get_post_meta($attachment_id, '_xpm_optimized', true);
        if ($already_optimized) {
            error_log("WARNING: Already optimized - ID: $attachment_id");
            return array('success' => false, 'message' => __('Already optimized', 'xpm-image-seo'));
        }
        
        $original_size = filesize($file_path);
        error_log("Original main file size: " . $original_size . " bytes (" . size_format($original_size) . ")");
        
        $backup_created = false;
        $total_original_size = 0;
        $total_optimized_size = 0;
        $optimized_sizes_count = 0;
        $png_converted_to_jpeg = false;
        $converted_file_path = $file_path;
        
        try {
            // Create backup if enabled
            if (!empty($options['backup_originals'])) {
                error_log("Creating backup...");
                $backup_created = $this->create_backup($file_path, $attachment_id);
                error_log("Backup created: " . ($backup_created ? 'YES' : 'NO'));
            }
            
            // Get current image info
            $image_info = getimagesize($file_path);
            if (!$image_info) {
                throw new Exception(__('Invalid image file', 'xpm-image-seo'));
            }
            
            list($width, $height, $type) = $image_info;
            error_log("Original dimensions: {$width}x{$height}, Type: $type");
            
            // NEW: Check for PNG to JPEG conversion
            if (!empty($options['convert_png_to_jpeg']) && $type === IMAGETYPE_PNG) {
                error_log("=== PNG TO JPEG CONVERSION CHECK ===");
                
                $quality = intval($options['compression_quality'] ?? 85);
                $jpeg_path = $this->convert_png_to_jpeg($file_path, $quality);
                
                if ($jpeg_path && file_exists($jpeg_path)) {
                    error_log("PNG to JPEG conversion successful: $jpeg_path");
                    
                    // Replace the original PNG with JPEG
                    if (unlink($file_path) && rename($jpeg_path, $file_path)) {
                        // Update the file extension in WordPress
                        $new_file_path = preg_replace('/\.png$/i', '.jpg', $file_path);
                        if (rename($file_path, $new_file_path)) {
                            $converted_file_path = $new_file_path;
                            $png_converted_to_jpeg = true;
                            
                            // Update WordPress attachment data
                            update_attached_file($attachment_id, $converted_file_path);
                            
                            // Update post data
                            $attachment = get_post($attachment_id);
                            if ($attachment) {
                                $new_filename = basename($converted_file_path);
                                wp_update_post(array(
                                    'ID' => $attachment_id,
                                    'post_title' => preg_replace('/\.png$/i', '', $attachment->post_title),
                                    'post_name' => preg_replace('/\.png$/i', '', $attachment->post_name),
                                    'guid' => str_replace('.png', '.jpg', $attachment->guid)
                                ));
                            }
                            
                            // Update MIME type
                            wp_update_post(array(
                                'ID' => $attachment_id,
                                'post_mime_type' => 'image/jpeg'
                            ));
                            
                            // Update file path and get new image info
                            $file_path = $converted_file_path;
                            $image_info = getimagesize($file_path);
                            $type = IMAGETYPE_JPEG;
                            
                            error_log("PNG to JPEG conversion completed: $file_path");
                        }
                    }
                } else {
                    error_log("PNG to JPEG conversion skipped or failed");
                }
            }
            
            $max_width = intval($options['max_width'] ?? 2048);
            $max_height = intval($options['max_height'] ?? 2048);
            $quality = intval($options['compression_quality'] ?? 85);
            
            error_log("Max dimensions: {$max_width}x{$max_height}, Quality: $quality");
            
            // Check if resizing is needed for main image
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
                error_log("MAIN IMAGE RESIZING: New dimensions will be {$new_width}x{$new_height}");
            } else {
                error_log("NO RESIZING needed for main image");
            }
            
            // Check available image processing libraries
            $has_imagick = extension_loaded('imagick') && class_exists('Imagick');
            $has_gd = extension_loaded('gd');
            error_log("Image libraries - Imagick: " . ($has_imagick ? 'YES' : 'NO') . ", GD: " . ($has_gd ? 'YES' : 'NO'));
            
            // STEP 1: Optimize main image
            error_log("=== OPTIMIZING MAIN IMAGE ===");
            $main_result = $this->optimize_single_file($file_path, $new_width, $new_height, $quality, $type, $resized, $has_imagick, $has_gd);
            
            if (!$main_result['success']) {
                throw new Exception(__('Could not optimize main image', 'xpm-image-seo'));
            }
            
            $total_original_size += $main_result['original_size'];
            $total_optimized_size += $main_result['new_size'];
            $optimized_sizes_count++;
            
            // STEP 2: Get and optimize ALL image sizes (thumbnails)
            error_log("=== OPTIMIZING ALL THUMBNAILS ===");
            $thumbnail_results = $this->optimize_all_image_sizes($attachment_id, $file_path, $quality, $has_imagick, $has_gd);
            
            foreach ($thumbnail_results as $thumb_result) {
                $total_original_size += $thumb_result['original_size'];
                $total_optimized_size += $thumb_result['new_size'];
                if ($thumb_result['success']) {
                    $optimized_sizes_count++;
                }
            }
            
            // STEP 3: Force regeneration of ALL thumbnails with optimized main image
            error_log("=== REGENERATING ALL THUMBNAILS ===");
            $this->regenerate_all_thumbnails($attachment_id, $file_path, $quality, $has_imagick, $has_gd);
            
            // Convert to WebP if enabled
            $webp_path = null;
            if (!empty($options['convert_to_webp']) && $type != IMAGETYPE_WEBP) {
                error_log("Converting to WebP...");
                $webp_path = $this->convert_to_webp($file_path, $quality);
                error_log("WebP created: " . ($webp_path ? 'YES' : 'NO'));
            }
            
            $total_savings = $total_original_size - $total_optimized_size;
            $savings_percent = $total_original_size > 0 ? round(($total_savings / $total_original_size) * 100) : 0;
            $processing_time = microtime(true) - $start_time;
            
            error_log("=== OPTIMIZATION SUMMARY ===");
            error_log("Optimized sizes count: " . $optimized_sizes_count);
            error_log("Total original size: " . $total_original_size . " bytes (" . size_format($total_original_size) . ")");
            error_log("Total optimized size: " . $total_optimized_size . " bytes (" . size_format($total_optimized_size) . ")");
            error_log("Total savings: " . $total_savings . " bytes (" . $savings_percent . "%)");
            error_log("PNG to JPEG converted: " . ($png_converted_to_jpeg ? 'YES' : 'NO'));
            
            // CRITICAL: Force complete WordPress refresh
            $this->force_complete_refresh($attachment_id, $file_path);
            
            // Update our optimization metadata
            update_post_meta($attachment_id, '_xpm_optimized', 1);
            update_post_meta($attachment_id, '_xpm_original_size', $total_original_size);
            update_post_meta($attachment_id, '_xpm_optimized_size', $total_optimized_size);
            update_post_meta($attachment_id, '_xpm_bytes_saved', $total_savings);
            update_post_meta($attachment_id, '_xpm_optimization_date', current_time('mysql'));
            update_post_meta($attachment_id, '_xpm_processing_time', $processing_time);
            update_post_meta($attachment_id, '_xpm_sizes_optimized', $optimized_sizes_count);
            
            if ($resized) {
                update_post_meta($attachment_id, '_xpm_resized', 1);
                update_post_meta($attachment_id, '_xpm_original_dimensions', $width . 'x' . $height);
            }
            
            if ($png_converted_to_jpeg) {
                update_post_meta($attachment_id, '_xpm_png_converted', 1);
            }
            
            if ($webp_path) {
                update_post_meta($attachment_id, '_xpm_webp_path', $webp_path);
            }
            
            // FINAL: Clear all caches and force refresh
            $this->nuclear_cache_clear($attachment_id);
            
            error_log("Final URL: " . wp_get_attachment_url($attachment_id));
            error_log("=== XPM COMPLETE Optimization Debug End - SUCCESS ===");
            
            return array(
                'success' => true,
                'original_size' => $total_original_size,
                'new_size' => $total_optimized_size,
                'savings' => $total_savings,
                'savings_percent' => $savings_percent,
                'resized' => $resized,
                'backup_created' => $backup_created,
                'webp_created' => !empty($webp_path),
                'webp_path' => $webp_path,
                'png_converted_to_jpeg' => $png_converted_to_jpeg,
                'processing_time' => $processing_time,
                'sizes_optimized' => $optimized_sizes_count,
                'final_url' => wp_get_attachment_url($attachment_id)
            );
            
        } catch (Exception $e) {
            error_log("=== XPM COMPLETE Optimization Debug End - ERROR: " . $e->getMessage() . " ===");
            
            if (class_exists('XPM_Error_Handler')) {
                XPM_Error_Handler::log_error('Image optimization failed: ' . $e->getMessage(), array(
                    'attachment_id' => $attachment_id,
                    'file_path' => $file_path
                ));
            }
            
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Optimize all image sizes (thumbnails) for an attachment
     */
    private function optimize_all_image_sizes($attachment_id, $main_file_path, $quality, $has_imagick, $has_gd) {
        error_log("Starting optimization of all image sizes for attachment $attachment_id");
        
        $results = array();
        
        // Get current metadata
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!$metadata || !isset($metadata['sizes'])) {
            error_log("No thumbnail metadata found");
            return $results;
        }
        
        $upload_dir = wp_upload_dir();
        $file_dir = dirname($main_file_path);
        
        error_log("Found " . count($metadata['sizes']) . " thumbnail sizes to optimize");
        
        foreach ($metadata['sizes'] as $size_name => $size_data) {
            $thumb_path = $file_dir . '/' . $size_data['file'];
            
            if (file_exists($thumb_path)) {
                error_log("Optimizing {$size_name} thumbnail: {$thumb_path}");
                
                $original_thumb_size = filesize($thumb_path);
                
                // Get image info
                $image_info = getimagesize($thumb_path);
                if ($image_info) {
                    list($width, $height, $type) = $image_info;
                    
                    // Optimize this thumbnail
                    $thumb_result = $this->optimize_single_file(
                        $thumb_path, 
                        $width, 
                        $height, 
                        $quality, 
                        $type, 
                        false, // thumbnails don't need resizing
                        $has_imagick, 
                        $has_gd
                    );
                    
                    $results[$size_name] = $thumb_result;
                    
                    if ($thumb_result['success']) {
                        error_log("✅ {$size_name} optimized: " . size_format($thumb_result['original_size']) . " → " . size_format($thumb_result['new_size']) . " (saved " . size_format($thumb_result['savings']) . ")");
                    } else {
                        error_log("❌ {$size_name} optimization failed");
                    }
                } else {
                    error_log("Could not get image info for {$thumb_path}");
                    $results[$size_name] = array(
                        'success' => false,
                        'original_size' => $original_thumb_size,
                        'new_size' => $original_thumb_size,
                        'savings' => 0
                    );
                }
            } else {
                error_log("Thumbnail file not found: {$thumb_path}");
                $results[$size_name] = array(
                    'success' => false,
                    'original_size' => 0,
                    'new_size' => 0,
                    'savings' => 0
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Regenerate all thumbnails after main image optimization
     */
    private function regenerate_all_thumbnails($attachment_id, $main_file_path, $quality, $has_imagick, $has_gd) {
        error_log("Regenerating all thumbnails for attachment $attachment_id");
        
        // Delete existing metadata to force complete regeneration
        delete_post_meta($attachment_id, '_wp_attachment_metadata');
        
        // Regenerate metadata and thumbnails
        $metadata = wp_generate_attachment_metadata($attachment_id, $main_file_path);
        
        if ($metadata) {
            wp_update_attachment_metadata($attachment_id, $metadata);
            error_log("Regenerated " . (isset($metadata['sizes']) ? count($metadata['sizes']) : 0) . " thumbnails");
            
            // Now optimize the newly generated thumbnails
            if (isset($metadata['sizes'])) {
                $file_dir = dirname($main_file_path);
                
                foreach ($metadata['sizes'] as $size_name => $size_data) {
                    $thumb_path = $file_dir . '/' . $size_data['file'];
                    
                    if (file_exists($thumb_path)) {
                        $image_info = getimagesize($thumb_path);
                        if ($image_info) {
                            list($width, $height, $type) = $image_info;
                            
                            // Optimize the newly generated thumbnail
                            $this->optimize_single_file(
                                $thumb_path, 
                                $width, 
                                $height, 
                                $quality, 
                                $type, 
                                false,
                                $has_imagick, 
                                $has_gd
                            );
                            
                            error_log("Re-optimized regenerated {$size_name} thumbnail");
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Optimize a single file (main image or thumbnail)
     */
    private function optimize_single_file($file_path, $width, $height, $quality, $type, $resize, $has_imagick, $has_gd) {
        $original_size = filesize($file_path);
        
        try {
            // Create temporary file
            $temp_file = $file_path . '.tmp_opt_' . time();
            
            // Optimize to temporary file
            if ($has_imagick) {
                $result = $this->optimize_with_imagick($file_path, $temp_file, $width, $height, $quality, $type, $resize);
            } elseif ($has_gd) {
                $result = $this->optimize_with_gd($file_path, $temp_file, $width, $height, $quality, $type, $resize);
            } else {
                return array(
                    'success' => false,
                    'original_size' => $original_size,
                    'new_size' => $original_size,
                    'savings' => 0
                );
            }
            
            if (!$result || !file_exists($temp_file)) {
                return array(
                    'success' => false,
                    'original_size' => $original_size,
                    'new_size' => $original_size,
                    'savings' => 0
                );
            }
            
            $temp_size = filesize($temp_file);
            
            // Only replace if we saved space or forced optimization
            if ($temp_size < $original_size || $resize) {
                if (rename($temp_file, $file_path)) {
                    clearstatcache(true, $file_path);
                    $new_size = filesize($file_path);
                    $savings = $original_size - $new_size;
                    
                    return array(
                        'success' => true,
                        'original_size' => $original_size,
                        'new_size' => $new_size,
                        'savings' => $savings
                    );
                }
            } else {
                // No improvement, clean up and keep original
                unlink($temp_file);
            }
            
            return array(
                'success' => false,
                'original_size' => $original_size,
                'new_size' => $original_size,
                'savings' => 0
            );
            
        } catch (Exception $e) {
            error_log("Single file optimization failed: " . $e->getMessage());
            
            return array(
                'success' => false,
                'original_size' => $original_size,
                'new_size' => $original_size,
                'savings' => 0
            );
        }
    }
    
    /**
     * Force complete WordPress refresh
     */
    private function force_complete_refresh($attachment_id, $file_path) {
        error_log("Forcing complete WordPress refresh for attachment $attachment_id");
        
        // Clear WordPress object cache
        wp_cache_delete($attachment_id, 'posts');
        wp_cache_delete($attachment_id, 'post_meta');
        clean_post_cache($attachment_id);
        
        // Delete and regenerate ALL attachment metadata
        delete_post_meta($attachment_id, '_wp_attachment_metadata');
        delete_post_meta($attachment_id, '_wp_attached_file');
        
        // Update the attached file path to force refresh
        update_post_meta($attachment_id, '_wp_attached_file', _wp_relative_upload_path($file_path));
        
        // Regenerate metadata with new file
        $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
        if ($metadata) {
            wp_update_attachment_metadata($attachment_id, $metadata);
            error_log("Regenerated attachment metadata completely");
        }
        
        // Update post modification time to break all caches
        wp_update_post(array(
            'ID' => $attachment_id,
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1)
        ));
        
        // Touch the file to update modification time
        touch($file_path);
        
        // Force action hooks
        do_action('xmp_image_optimized', $attachment_id, $file_path);
        do_action('wp_update_attachment_metadata', $metadata, $attachment_id);
    }
    
    /**
     * Nuclear cache clearing - clear everything possible
     */
    private function nuclear_cache_clear($attachment_id) {
        error_log("Nuclear cache clearing for attachment $attachment_id");
        
        // WordPress core
        wp_cache_flush();
        clean_post_cache($attachment_id);
        
        // Clear all transients related to this attachment
        delete_transient('xmp_attachment_' . $attachment_id);
        
        // All major caching plugins
        
        // W3 Total Cache
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        if (function_exists('w3tc_flush_url')) {
            w3tc_flush_url(wp_get_attachment_url($attachment_id));
        }
        
        // WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        
        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        if (function_exists('rocket_clean_file')) {
            $file_path = get_attached_file($attachment_id);
            rocket_clean_file($file_path);
        }
        
        // LiteSpeed Cache
        if (class_exists('LiteSpeed_Cache_API')) {
            LiteSpeed_Cache_API::purge_all();
        }
        
        // Cloudflare
        if (function_exists('cloudflare_purge_everything')) {
            cloudflare_purge_everything();
        }
        
        // WP Optimize
        if (function_exists('wpo_cache_flush')) {
            wpo_cache_flush();
        }
        
        // Autoptimize
        if (class_exists('autoptimizeCache')) {
            autoptimizeCache::clearall();
        }
        
        // SiteGround Optimizer
        if (function_exists('sg_cachepress_purge_cache')) {
            sg_cachepress_purge_cache();
        }
        
        // WP Fastest Cache
        if (function_exists('wpfc_clear_all_cache')) {
            wpfc_clear_all_cache();
        }
        
        // Comet Cache
        if (class_exists('comet_cache')) {
            comet_cache::clear();
        }
        
        // Hummingbird
        if (class_exists('Hummingbird\\WP_Hummingbird')) {
            do_action('wphb_clear_page_cache');
        }
        
        error_log("All caches cleared");
    }
    
    /**
     * Optimize image using Imagick - TEMP FILE VERSION
     */
    private function optimize_with_imagick($source_path, $output_path, $new_width, $new_height, $quality, $type, $resized) {
        try {
            $imagick = new Imagick($source_path);
            
            // Strip metadata but keep color profiles
            $profiles = $imagick->getImageProfiles("icc", true);
            $imagick->stripImage();
            if(!empty($profiles)) {
                $imagick->profileImage("icc", $profiles['icc']);
            }
            
            // Resize if needed
            if ($resized) {
                $imagick->resizeImage($new_width, $new_height, Imagick::FILTER_LANCZOS, 1);
                $imagick->unsharpMaskImage(0, 0.5, 1, 0.05);
            }
            
            // Set compression
            $imagick->setImageCompressionQuality($quality);
            
            // Format-specific optimizations
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $imagick->setImageFormat('jpeg');
                    $imagick->setInterlaceScheme(Imagick::INTERLACE_PLANE);
                    $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
                    $imagick->setSamplingFactors(array('2x2', '1x1', '1x1'));
                    break;
                    
                case IMAGETYPE_PNG:
                    $imagick->setImageFormat('png');
                    $imagick->setImageCompression(Imagick::COMPRESSION_ZIP);
                    if ($quality < 95) {
                        $imagick->quantizeImage(256, Imagick::COLORSPACE_RGB, 0, false, false);
                    }
                    break;
                    
                case IMAGETYPE_WEBP:
                    $imagick->setImageFormat('webp');
                    $imagick->setOption('webp:alpha-quality', '95');
                    $imagick->setOption('webp:method', '6');
                    break;
            }
            
            // Write to output file
            $result = $imagick->writeImage($output_path);
            $imagick->destroy();
            
            return $result;
            
        } catch (Exception $e) {
            error_log('Imagick optimization failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Optimize image using GD - TEMP FILE VERSION
     */
    private function optimize_with_gd($source_path, $output_path, $new_width, $new_height, $quality, $type, $resized) {
        try {
            // Load source image
            $image_resource = null;
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $image_resource = imagecreatefromjpeg($source_path);
                    break;
                case IMAGETYPE_PNG:
                    $image_resource = imagecreatefrompng($source_path);
                    break;
                case IMAGETYPE_WEBP:
                    if (function_exists('imagecreatefromwebp')) {
                        $image_resource = imagecreatefromwebp($source_path);
                    }
                    break;
                case IMAGETYPE_GIF:
                    $image_resource = imagecreatefromgif($source_path);
                    break;
            }
            
            if (!$image_resource) {
                return false;
            }
            
            $original_width = imagesx($image_resource);
            $original_height = imagesy($image_resource);
            
            // Resize if needed
            if ($resized) {
                $new_image = imagecreatetruecolor($new_width, $new_height);
                
                // Preserve transparency
                if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
                    imagealphablending($new_image, false);
                    imagesavealpha($new_image, true);
                    $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
                    imagefill($new_image, 0, 0, $transparent);
                }
                
                imagecopyresampled($new_image, $image_resource, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
                imagedestroy($image_resource);
                $image_resource = $new_image;
            }
            
            // Save to output file
            $save_success = false;
            switch ($type) {
                case IMAGETYPE_JPEG:
                    if (function_exists('imageinterlace')) {
                        imageinterlace($image_resource, 1);
                    }
                    $save_success = imagejpeg($image_resource, $output_path, $quality);
                    break;
                    
                case IMAGETYPE_PNG:
                    $png_quality = round((100 - $quality) / 11.11);
                    $png_quality = max(0, min(9, $png_quality));
                    $save_success = imagepng($image_resource, $output_path, $png_quality);
                    break;
                    
                case IMAGETYPE_WEBP:
                    if (function_exists('imagewebp')) {
                        $save_success = imagewebp($image_resource, $output_path, $quality);
                    }
                    break;
                    
                case IMAGETYPE_GIF:
                    $save_success = imagegif($image_resource, $output_path);
                    break;
            }
            
            imagedestroy($image_resource);
            return $save_success;
            
        } catch (Exception $e) {
            error_log('GD optimization failed: ' . $e->getMessage());
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
        
        $image_info = getimagesize($file_path);
        if (!$image_info) return false;
        
        $image_resource = null;
        switch ($image_info[2]) {
            case IMAGETYPE_JPEG:
                $image_resource = imagecreatefromjpeg($file_path);
                break;
            case IMAGETYPE_PNG:
                $image_resource = imagecreatefrompng($file_path);
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
            delete_post_meta($attachment_id, '_xpm_png_converted');
            delete_post_meta($attachment_id, '_xpm_optimization_date');
            delete_post_meta($attachment_id, '_xpm_processing_time');
            delete_post_meta($attachment_id, '_xpm_sizes_optimized');
            
            // Force complete refresh
            $this->force_complete_refresh($attachment_id, $original_path);
            $this->nuclear_cache_clear($attachment_id);
            
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
     * Cleanup old backups
     */
    public function cleanup_old_backups() {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/xpm-backups';
        
        if (!file_exists($backup_dir)) {
            return;
        }
        
        $files = glob($backup_dir . '/*');
        $cutoff_time = time() - (30 * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
}