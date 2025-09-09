<?php
/**
 * XPM Image SEO Keywords Manager
 * 
 * @package XPM_Image_SEO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle smart keyword extraction and management
 */
class XPM_Keywords {
    
    private $option_name = 'xpm_image_seo_settings';
    
    /**
     * Extract keywords for a specific image
     */
    public function extract_keywords_for_image($attachment_id) {
        $options = get_option($this->option_name);
        $keywords = array();
        
        // Get global keywords
        $global_keywords = $this->get_global_keywords();
        
        // Get contextual keywords if enabled
        $contextual_keywords = array();
        if (!empty($options['use_contextual_keywords'])) {
            $contextual_keywords = $this->extract_contextual_keywords($attachment_id);
        }
        
        // Combine based on priority
        $priority = $options['keyword_priority'] ?? 'contextual_first';
        $max_keywords = intval($options['max_keywords'] ?? 3);
        
        switch ($priority) {
            case 'global_first':
                $keywords = array_merge($global_keywords, $contextual_keywords);
                break;
            case 'mixed':
                $keywords = $this->mix_keywords($global_keywords, $contextual_keywords);
                break;
            default: // contextual_first
                $keywords = array_merge($contextual_keywords, $global_keywords);
                break;
        }
        
        // Limit to max keywords and return unique values
        return array_slice(array_unique($keywords), 0, $max_keywords);
    }
    
    /**
     * Get global keywords from settings
     */
    public function get_global_keywords() {
        $options = get_option($this->option_name);
        $global_keywords = isset($options['global_keywords']) ? $options['global_keywords'] : '';
        
        if (empty($global_keywords)) {
            return array();
        }
        
        $keywords = array_map('trim', explode(',', $global_keywords));
        return array_filter($keywords, function($keyword) {
            return !empty($keyword) && strlen($keyword) > 2;
        });
    }
    
    /**
     * Extract contextual keywords from posts using this image
     */
    public function extract_contextual_keywords($attachment_id) {
        global $wpdb;
        
        // Find posts containing this image
        $posts = $wpdb->get_results($wpdb->prepare("
            SELECT p.* 
            FROM {$wpdb->posts} p 
            WHERE (p.post_content LIKE %s OR p.post_content LIKE %s)
            AND p.post_status = 'publish'
            AND p.post_type IN ('post', 'page')
            LIMIT 5
        ", '%wp-image-' . $attachment_id . '%', '%-' . $attachment_id . '.%'));
        
        $keywords = array();
        
        foreach ($posts as $post) {
            // Extract from title (high priority)
            $title_words = $this->extract_meaningful_words($post->post_title);
            $keywords = array_merge($keywords, $title_words);
            
            // Extract from categories
            $categories = get_the_category($post->ID);
            foreach ($categories as $cat) {
                $keywords[] = $cat->name;
            }
            
            // Extract from tags
            $tags = get_the_tags($post->ID);
            if ($tags) {
                foreach ($tags as $tag) {
                    $keywords[] = $tag->name;
                }
            }
            
            // Extract key phrases from content (lower priority)
            $content_keywords = $this->extract_key_phrases($post->post_content);
            $keywords = array_merge($keywords, $content_keywords);
            
            // Extract from custom fields that might contain keywords
            $custom_keywords = $this->extract_from_custom_fields($post->ID);
            $keywords = array_merge($keywords, $custom_keywords);
        }
        
        // Also check if image is used in galleries or attachments
        $gallery_keywords = $this->extract_from_galleries($attachment_id);
        $keywords = array_merge($keywords, $gallery_keywords);
        
        // Clean and filter keywords
        $keywords = array_filter(array_unique($keywords), function($keyword) {
            return strlen($keyword) > 2 && strlen($keyword) < 30 && !$this->is_stop_word($keyword);
        });
        
        // Sort by relevance (frequency)
        $keyword_counts = array_count_values($keywords);
        arsort($keyword_counts);
        
        return array_keys($keyword_counts);
    }
    
    /**
     * Extract meaningful words from text
     */
    private function extract_meaningful_words($text) {
        // Convert to lowercase and remove special characters
        $text = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $text));
        
        $words = str_word_count($text, 1);
        
        // Filter out stop words and short words
        $words = array_filter($words, function($word) {
            return strlen($word) > 3 && !$this->is_stop_word($word);
        });
        
        return array_values($words);
    }
    
    /**
     * Extract key phrases from content
     */
    private function extract_key_phrases($content) {
        // Remove HTML tags and shortcodes
        $content = wp_strip_all_tags($content);
        $content = strip_shortcodes($content);
        
        // Split into sentences
        $sentences = preg_split('/[.!?]+/', $content);
        
        $phrases = array();
        foreach ($sentences as $sentence) {
            $words = $this->extract_meaningful_words($sentence);
            
            // Create 2-3 word phrases
            for ($i = 0; $i < count($words) - 1; $i++) {
                if ($i < count($words) - 2) {
                    $phrase = $words[$i] . ' ' . $words[$i + 1] . ' ' . $words[$i + 2];
                    if (strlen($phrase) < 50) {
                        $phrases[] = $phrase;
                    }
                }
                
                $phrase = $words[$i] . ' ' . $words[$i + 1];
                if (strlen($phrase) < 30) {
                    $phrases[] = $phrase;
                }
            }
        }
        
        return array_slice($phrases, 0, 10); // Limit phrases
    }
    
    /**
     * Extract keywords from custom fields
     */
    private function extract_from_custom_fields($post_id) {
        $keywords = array();
        
        // Common SEO plugin fields
        $seo_fields = array(
            '_yoast_wpseo_focuskw', // Yoast SEO
            '_yoast_wpseo_metakeywords',
            'rank_math_focus_keyword', // Rank Math
            '_aioseo_keywords', // All in One SEO
            'seopress_keywords' // SEOPress
        );
        
        foreach ($seo_fields as $field) {
            $value = get_post_meta($post_id, $field, true);
            if (!empty($value)) {
                if (is_string($value)) {
                    $field_keywords = array_map('trim', explode(',', $value));
                    $keywords = array_merge($keywords, $field_keywords);
                }
            }
        }
        
        return $keywords;
    }
    
    /**
     * Extract keywords from galleries containing this image
     */
    private function extract_from_galleries($attachment_id) {
        global $wpdb;
        
        // Find gallery shortcodes containing this image
        $galleries = $wpdb->get_results($wpdb->prepare("
            SELECT post_title, post_content 
            FROM {$wpdb->posts} 
            WHERE post_content LIKE %s 
            AND post_status = 'publish'
        ", '%[gallery%ids%' . $attachment_id . '%'));
        
        $keywords = array();
        foreach ($galleries as $gallery_post) {
            $title_words = $this->extract_meaningful_words($gallery_post->post_title);
            $keywords = array_merge($keywords, $title_words);
        }
        
        return $keywords;
    }
    
    /**
     * Check if word is a stop word
     */
    private function is_stop_word($word) {
        $stop_words = array(
            'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 
            'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 
            'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'can',
            'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they',
            'me', 'him', 'her', 'us', 'them', 'my', 'your', 'his', 'its', 'our', 'their',
            'about', 'above', 'after', 'again', 'all', 'also', 'any', 'because', 'before',
            'both', 'each', 'few', 'how', 'just', 'more', 'most', 'much', 'new', 'now',
            'only', 'other', 'some', 'such', 'than', 'too', 'very', 'what', 'when', 'where',
            'which', 'who', 'why', 'into', 'over', 'same', 'then', 'under', 'until', 'while'
        );
        
        return in_array(strtolower($word), $stop_words);
    }
    
    /**
     * Mix global and contextual keywords intelligently
     */
    private function mix_keywords($global_keywords, $contextual_keywords) {
        $mixed = array();
        $max_from_each = 2;
        
        // Take alternately from each list
        for ($i = 0; $i < $max_from_each; $i++) {
            if (isset($contextual_keywords[$i])) {
                $mixed[] = $contextual_keywords[$i];
            }
            if (isset($global_keywords[$i])) {
                $mixed[] = $global_keywords[$i];
            }
        }
        
        // Add remaining from whichever list is longer
        $remaining_contextual = array_slice($contextual_keywords, $max_from_each);
        $remaining_global = array_slice($global_keywords, $max_from_each);
        
        $mixed = array_merge($mixed, $remaining_contextual, $remaining_global);
        
        return array_unique($mixed);
    }
    
    /**
     * Get keyword analytics for dashboard
     */
    public function get_keyword_analytics() {
        global $wpdb;
        
        // Get keywords from recent alt text generations
        $recent_keywords = $wpdb->get_results("
            SELECT meta_value as keywords 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_xpm_keywords_used' 
            AND meta_value != ''
            ORDER BY meta_id DESC 
            LIMIT 500
        ");
        
        $keyword_counts = array();
        
        foreach ($recent_keywords as $row) {
            $keywords = maybe_unserialize($row->keywords);
            if (is_array($keywords)) {
                foreach ($keywords as $keyword) {
                    $keyword_counts[$keyword] = ($keyword_counts[$keyword] ?? 0) + 1;
                }
            } elseif (is_string($keywords)) {
                $keyword_list = array_map('trim', explode(',', $keywords));
                foreach ($keyword_list as $keyword) {
                    if (!empty($keyword)) {
                        $keyword_counts[$keyword] = ($keyword_counts[$keyword] ?? 0) + 1;
                    }
                }
            }
        }
        
        // Sort by usage count
        arsort($keyword_counts);
        
        return array_slice($keyword_counts, 0, 20); // Top 20 keywords
    }
    
    /**
     * Suggest keywords based on site content
     */
    public function suggest_global_keywords() {
        global $wpdb;
        
        $suggestions = array();
        
        // Get most used categories
        $categories = $wpdb->get_results("
            SELECT t.name, COUNT(*) as count
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            WHERE tt.taxonomy = 'category'
            GROUP BY t.term_id
            ORDER BY count DESC
            LIMIT 10
        ");
        
        foreach ($categories as $cat) {
            $suggestions[] = $cat->name;
        }
        
        // Get most used tags
        $tags = $wpdb->get_results("
            SELECT t.name, COUNT(*) as count
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            WHERE tt.taxonomy = 'post_tag'
            GROUP BY t.term_id
            ORDER BY count DESC
            LIMIT 10
        ");
        
        foreach ($tags as $tag) {
            $suggestions[] = $tag->name;
        }
        
        // Get site title and tagline words
        $site_title = get_bloginfo('name');
        $site_tagline = get_bloginfo('description');
        
        $title_words = $this->extract_meaningful_words($site_title);
        $tagline_words = $this->extract_meaningful_words($site_tagline);
        
        $suggestions = array_merge($suggestions, $title_words, $tagline_words);
        
        return array_unique(array_filter($suggestions));
    }
}