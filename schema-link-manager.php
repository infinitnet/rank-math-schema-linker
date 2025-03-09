<?php
/**
 * Plugin Name: Schema Link Manager
 * Description: Adds and manages significant and related links to JSON-LD WebPage schema
 * Version: 1.0.7
 * Author: Infinitnet
 * Text Domain: schema-link-manager
 * License: GPL v2 or later
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Schema_Link_Manager {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register hooks
        add_action('init', array($this, 'register_meta'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
        
        // Hook into the final schema output
        add_filter('rank_math/json_ld', array($this, 'add_links_to_schema'), 99, 2);
    }
    
    /**
     * Register post meta for storing links
     */
    public function register_meta() {
        $post_types = get_post_types(['public' => true]);
        
        foreach ($post_types as $post_type) {
            register_post_meta($post_type, 'schema_significant_links', array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'auth_callback' => function() {
                    return current_user_can('edit_posts');
                }
            ));
            
            register_post_meta($post_type, 'schema_related_links', array(
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
            'schema-link-manager',
            plugins_url('/js/schema-link-manager.js', __FILE__),
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-plugins', 'wp-i18n'),
            filemtime(plugin_dir_path(__FILE__) . 'js/schema-link-manager.js'),
            true
        );
        
        // Enqueue CSS
        wp_enqueue_style(
            'schema-link-manager',
            plugins_url('/css/schema-link-manager.css', __FILE__),
            array(),
            filemtime(plugin_dir_path(__FILE__) . 'css/schema-link-manager.css')
        );
        
        // Add inline translations
        wp_set_script_translations('schema-link-manager', 'schema-link-manager');
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
     * Add links to schema
     * 
     * @param array $data Schema data
     * @param object $jsonld JsonLD object
     * @return array Modified schema data
     */
    public function add_links_to_schema($data, $jsonld) {
        $post_id = get_the_ID();
        if (!$post_id) {
            return $data;
        }
        
        $significant_links = $this->process_links($post_id, 'schema_significant_links');
        $related_links = $this->process_links($post_id, 'schema_related_links');
        
        if (empty($significant_links) && empty($related_links)) {
            return $data;
        }
        
        // Process each entity in the schema
        foreach ($data as $entity_id => $entity) {
            // If this is a WebPage entity, add links directly
            if (isset($entity['@type']) && $entity['@type'] === 'WebPage') {
                if (!empty($significant_links)) {
                    $data[$entity_id]['significantLink'] = $significant_links;
                }
                if (!empty($related_links)) {
                    $data[$entity_id]['relatedLink'] = $related_links;
                }
            }
            
            // If this is an Article with a nested WebPage, add links to the WebPage
            if (isset($entity['@type']) && in_array($entity['@type'], ['Article', 'BlogPosting', 'NewsArticle']) && 
                isset($entity['isPartOf']) && isset($entity['isPartOf']['@type']) && $entity['isPartOf']['@type'] === 'WebPage') {
                
                if (!empty($significant_links)) {
                    $data[$entity_id]['isPartOf']['significantLink'] = $significant_links;
                }
                if (!empty($related_links)) {
                    $data[$entity_id]['isPartOf']['relatedLink'] = $related_links;
                }
            }
        }
        
        return $data;
    }
}

// Initialize the plugin
new Schema_Link_Manager();
