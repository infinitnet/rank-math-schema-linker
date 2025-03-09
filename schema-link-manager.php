<?php
/**
 * Plugin Name: Schema Link Manager
 * Description: Adds and manages significant and related links to JSON-LD WebPage schema
 * Version: 1.1.1
 * Author: Infinitnet
 * Author URI: https://infinitnet.io/
 * License: GPLv3
 * Text Domain: schema-link-manager
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include admin functionality
require_once plugin_dir_path(__FILE__) . 'admin/class-schema-link-manager-admin.php';

class Schema_Link_Manager {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register hooks
        add_action('init', array($this, 'register_meta'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
        
        // Setup integrations with SEO plugins
        add_action('plugins_loaded', array($this, 'setup_seo_plugin_integrations'));
    }
    
    /**
     * Setup integrations with SEO plugins
     */
    public function setup_seo_plugin_integrations() {
        $has_seo_plugin = false;
        
        // Check for Rank Math
        if (class_exists('RankMath')) {
            add_filter('rank_math/json_ld', array($this, 'add_links_to_schema'), 99, 2);
            $has_seo_plugin = true;
        }
        
        // Check for Yoast SEO
        if (defined('WPSEO_VERSION')) {
            add_filter('wpseo_schema_webpage', array($this, 'add_links_to_yoast_schema'), 10, 1);
            $has_seo_plugin = true;
        }
        
        // If no supported SEO plugin is active, use the fallback method
        if (!$has_seo_plugin) {
            // Add our filter to various places where schema might be output
            add_action('wp_head', array($this, 'inject_schema_links'), 99);
            add_action('wp_footer', array($this, 'inject_schema_links'), 99);
            add_filter('the_content', array($this, 'process_content_for_schema'), 99);
        }
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
    
    /**
     * Add links to Yoast SEO schema
     * 
     * @param array $data WebPage schema data
     * @return array Modified WebPage schema data
     */
    public function add_links_to_yoast_schema($data) {
        $post_id = get_the_ID();
        if (!$post_id) {
            return $data;
        }
        
        $significant_links = $this->process_links($post_id, 'schema_significant_links');
        $related_links = $this->process_links($post_id, 'schema_related_links');
        
        if (!empty($significant_links)) {
            $data['significantLink'] = $significant_links;
        }
        
        if (!empty($related_links)) {
            $data['relatedLink'] = $related_links;
        }
        
        return $data;
    }
    /**
     * Process content for schema
     * 
     * @param string $content The post content
     * @return string Modified content
     */
    public function process_content_for_schema($content) {
        return $this->inject_links_into_json_ld($content);
    }

    /**
     * Inject schema links directly
     */
    public function inject_schema_links() {
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }
        
        $significant_links = $this->process_links($post_id, 'schema_significant_links');
        $related_links = $this->process_links($post_id, 'schema_related_links');
        
        if (empty($significant_links) && empty($related_links)) {
            return;
        }
        
        // Start output buffering
        ob_start();
        
        // Get the current buffer contents and pass it to inject_links_into_json_ld
        $self = $this;
        add_action('wp_footer', function() use ($self) {
            // Get current buffer
            $content = ob_get_contents();
            // Clean the buffer
            ob_clean();
            // Output modified content
            echo $self->inject_links_into_json_ld($content);
        }, 999);
    }

    /**
     * Fallback method to inject links into JSON-LD schema
     * 
     * @param string $html The HTML content
     * @return string Modified HTML content
     */
    public function inject_links_into_json_ld($html) {
        if (empty($html)) {
            return $html;
        }
        
        $post_id = get_the_ID();
        if (!$post_id) {
            return $html;
        }
        
        $significant_links = $this->process_links($post_id, 'schema_significant_links');
        $related_links = $this->process_links($post_id, 'schema_related_links');
        
        if (empty($significant_links) && empty($related_links)) {
            return $html;
        }
        
        // Use preg_replace_callback to find and modify JSON-LD scripts
        return preg_replace_callback(
            '/<script type=["\']application\/ld\+json["\'].*?>(.*?)<\/script>/s',
            function($matches) use ($significant_links, $related_links) {
                $json = trim($matches[1]);
                
                // Try to decode the JSON
                $data = json_decode($json, true);
                if (empty($data) || json_last_error() !== JSON_ERROR_NONE) {
                    return $matches[0]; // Return original if not valid JSON
                }
                
                $modified = false;
                
                // Check if this is a WebPage schema
                if (isset($data['@type'])) {
                    // Handle both string and array @type values
                    $types = is_array($data['@type']) ? $data['@type'] : [$data['@type']];
                    
                    if (in_array('WebPage', $types)) {
                        if (!empty($significant_links)) {
                            $data['significantLink'] = $significant_links;
                        }
                        if (!empty($related_links)) {
                            $data['relatedLink'] = $related_links;
                        }
                        $modified = true;
                    }
                }
                
                // Check for WebPage within a graph
                if (isset($data['@graph']) && is_array($data['@graph'])) {
                    foreach ($data['@graph'] as $key => $entity) {
                        if (isset($entity['@type'])) {
                            // Handle both string and array @type values
                            $types = is_array($entity['@type']) ? $entity['@type'] : [$entity['@type']];
                            
                            if (in_array('WebPage', $types)) {
                                if (!empty($significant_links)) {
                                    $data['@graph'][$key]['significantLink'] = $significant_links;
                                }
                                if (!empty($related_links)) {
                                    $data['@graph'][$key]['relatedLink'] = $related_links;
                                }
                                $modified = true;
                            }
                        }
                    }
                }
                
                // Only modify if we actually changed something
                if ($modified) {
                    return '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
                }
                
                return $matches[0]; // Return original if no WebPage found
            },
            $html
        );
    }
}

// Initialize the plugin
new Schema_Link_Manager();
