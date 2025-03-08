<?php
/**
 * Plugin Name: Rank Math Schema Linker
 * Description: Adds significant and related links to Rank Math's WebPage schema
 * Version: 1.0.3
 * Author: Infinitnet
 * Text Domain: rank-math-schema-linker
 * License: GPL v2 or later
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Rank_Math_Schema_Linker {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register hooks
        add_action('init', array($this, 'register_meta'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
        
        // Hook into Rank Math schema filters
        add_filter('rank_math/schema/webpage', array($this, 'add_links_to_webpage_schema'), 20, 2);
        // Fallback for general schema filter
        add_filter('rank_math/schema/graph', array($this, 'add_links_to_graph_schema'), 20);
    }
    
    /**
     * Register post meta for storing links
     */
    public function register_meta() {
        $post_types = get_post_types(['public' => true]);
        
        foreach ($post_types as $post_type) {
            register_post_meta($post_type, 'rank_math_significant_links', array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'auth_callback' => function() {
                    return current_user_can('edit_posts');
                }
            ));
            
            register_post_meta($post_type, 'rank_math_related_links', array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'auth_callback' => function() {
                    return current_user_can('edit_posts');
                }
            ));
        }
    }
    
    /**
     * Enqueue assets for the block editor
     */
    public function enqueue_editor_assets() {
        // Enqueue JS
        wp_enqueue_script(
            'rank-math-schema-linker',
            plugins_url('/js/schema-linker.js', __FILE__),
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-plugins', 'wp-i18n'),
            filemtime(plugin_dir_path(__FILE__) . 'js/schema-linker.js'),
            true
        );
        
        // Enqueue CSS
        wp_enqueue_style(
            'rank-math-schema-linker',
            plugins_url('/css/schema-linker.css', __FILE__),
            array(),
            filemtime(plugin_dir_path(__FILE__) . 'css/schema-linker.css')
        );
        
        // Add inline translations
        wp_set_script_translations('rank-math-schema-linker', 'rank-math-schema-linker');
    }
    
    /**
     * Process links from post meta
     * 
     * @param int $post_id Post ID
     * @param string $meta_key Meta key to retrieve
     * @return array Processed links
     */
    private function process_links($post_id, $meta_key) {
        $links_text = get_post_meta($post_id, $meta_key, true);
        
        if (empty($links_text)) {
            return [];
        }
        
        $links = array_map('trim', explode("\n", $links_text));
        $links = array_filter($links, function($link) {
            return !empty($link) && filter_var($link, FILTER_VALIDATE_URL);
        });
        
        return array_values(array_map('esc_url_raw', $links));
    }
    
    /**
     * Add links to WebPage schema
     * 
     * @param array $schema Schema data
     * @param int $post_id Post ID
     * @return array Modified schema
     */
    public function add_links_to_webpage_schema($schema, $post_id) {
        // Define all possible WebPage types
        $webpage_types = ['WebPage', 'SearchResultsPage', 'ProfilePage', 'CollectionPage', 'AboutPage', 'ContactPage'];
        
        // Only modify WebPage schema and its subtypes
        if (!isset($schema['@type']) || !in_array($schema['@type'], $webpage_types)) {
            return $schema;
        }
        
        $significant_links = $this->process_links($post_id, 'rank_math_significant_links');
        $related_links = $this->process_links($post_id, 'rank_math_related_links');
        
        if (!empty($significant_links)) {
            $schema['significantLink'] = $significant_links;
        }
        
        if (!empty($related_links)) {
            $schema['relatedLink'] = $related_links;
        }
        
        return $schema;
    }
    
    /**
     * Add links to general graph schema as fallback
     * 
     * @param array $schema Schema data
     * @return array Modified schema
     */
    public function add_links_to_graph_schema($schema) {
        if (!is_array($schema)) {
            return $schema;
        }
        
        $post_id = get_the_ID();
        if (!$post_id) {
            return $schema;
        }
        
        // Define all possible WebPage types
        $webpage_types = ['WebPage', 'SearchResultsPage', 'ProfilePage', 'CollectionPage', 'AboutPage', 'ContactPage'];
        
        // Find WebPage entity in the schema graph
        foreach ($schema as $index => $entity) {
            if (isset($entity['@type']) && in_array($entity['@type'], $webpage_types)) {
                $significant_links = $this->process_links($post_id, 'rank_math_significant_links');
                $related_links = $this->process_links($post_id, 'rank_math_related_links');
                
                if (!empty($significant_links)) {
                    $schema[$index]['significantLink'] = $significant_links;
                }
                
                if (!empty($related_links)) {
                    $schema[$index]['relatedLink'] = $related_links;
                }
                
                break;
            }
        }
        
        return $schema;
    }
}

// Initialize the plugin
new Rank_Math_Schema_Linker();
