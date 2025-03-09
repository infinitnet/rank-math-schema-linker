<?php
/**
 * Schema Link Manager Admin Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Schema_Link_Manager_Admin {
    
    /**
     * Search term for permalink filter
     * @var string
     */
    private $search_permalink_term = '';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_schema_link_manager_update', array($this, 'ajax_update_links'));
        add_action('wp_ajax_schema_link_manager_remove_all', array($this, 'ajax_remove_all_links'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Schema Link Manager', 'schema-link-manager'),
            __('Schema Links', 'schema-link-manager'),
            'manage_options',
            'schema-link-manager',
            array($this, 'render_admin_page'),
            'dashicons-admin-links',
            30
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_schema-link-manager' !== $hook) {
            return;
        }
        
        // Enqueue WordPress dashicons
        wp_enqueue_style('dashicons');
        
        // Enqueue Select2 CSS and JS (from local plugin files)
        wp_enqueue_style(
            'select2',
            plugins_url('/css/select2.min.css', dirname(__FILE__)),
            array(),
            '4.1.0-rc.0'
        );
        
        wp_enqueue_script(
            'select2',
            plugins_url('/js/select2.min.js', dirname(__FILE__)),
            array('jquery'),
            '4.1.0-rc.0',
            true
        );
        
        // Enqueue admin CSS
        wp_enqueue_style(
            'schema-link-manager-admin',
            plugins_url('/css/schema-link-manager-admin.css', dirname(__FILE__)),
            array('select2'),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'css/schema-link-manager-admin.css')
        );
        
        // Enqueue admin JS
        wp_enqueue_script(
            'schema-link-manager-admin',
            plugins_url('/js/schema-link-manager-admin.js', dirname(__FILE__)),
            array('jquery', 'select2'),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'js/schema-link-manager-admin.js'),
            true
        );
        
        // Localize script with ajax url and nonce
        wp_localize_script('schema-link-manager-admin', 'schemaLinkManager', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('schema_link_manager_nonce'),
            'strings' => array(
                'confirmRemoveAll' => __('Are you sure you want to remove all schema links from this post?', 'schema-link-manager'),
                'linkAdded' => __('Link added successfully!', 'schema-link-manager'),
                'linkRemoved' => __('Link removed successfully!', 'schema-link-manager'),
                'allLinksRemoved' => __('All links removed successfully!', 'schema-link-manager'),
                'error' => __('An error occurred. Please try again.', 'schema-link-manager')
            )
        ));
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Verify nonce if form was submitted
        if (isset($_REQUEST['_wpnonce']) && !empty($_REQUEST['_wpnonce'])) {
            $nonce = sanitize_text_field($_REQUEST['_wpnonce']);
            if (!wp_verify_nonce($nonce, 'schema_link_manager_filter_nonce')) {
                wp_die(__('Security check failed', 'schema-link-manager'));
            }
        }
        
        // Get current page, posts per page, and search parameters
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $posts_per_page = isset($_GET['per_page']) ? max(10, min(100, intval($_GET['per_page']))) : 20;
        $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $search_column = isset($_GET['search_column']) ? sanitize_key($_GET['search_column']) : 'all';
        $post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : 'all';
        $category = isset($_GET['category']) ? sanitize_key($_GET['category']) : 'all';
        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'title';
        $order = isset($_GET['order']) ? sanitize_key($_GET['order']) : 'asc';
        
        // Get available post types
        $post_types = $this->get_available_post_types();
        
        // Get available categories
        $categories = get_categories(array('hide_empty' => true));
        
        // Get posts with pagination
        $posts_data = $this->get_posts_with_schema_data($current_page, $posts_per_page, $search_term, $search_column, $post_type, $category, $orderby, $order);
        
        // Calculate pagination
        $total_posts = $posts_data['total'];
        $total_pages = ceil($total_posts / $posts_per_page);
        
        // Include the admin page template
        include plugin_dir_path(dirname(__FILE__)) . 'templates/admin-page.php';
    }
    
    /**
     * Get available post types
     * 
     * @return array Post types
     */
    private function get_available_post_types() {
        $post_types = get_post_types(array('public' => true), 'objects');
        $available_types = array();
        
        foreach ($post_types as $type) {
            $available_types[$type->name] = $type->labels->singular_name;
        }
        
        return $available_types;
    }
    
    /**
     * Get posts with schema data
     * 
     * @param int $page Current page
     * @param int $per_page Posts per page
     * @param string $search Search term
     * @param string $search_column Column to search in
     * @param string $post_type Post type to filter
     * @return array Posts data with pagination info
     */
    private function get_posts_with_schema_data($page = 1, $per_page = 20, $search = '', $search_column = 'all', $post_type = 'all', $category = '', $orderby = 'title', $order = 'ASC') {
        $args = array(
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => $orderby === 'type' ? 'post_type' : ($orderby === 'url' ? 'name' : 'title'),
            'order' => in_array(strtoupper($order), array('ASC', 'DESC')) ? strtoupper($order) : 'ASC',
        );
        
        // Handle post type filtering - fix for post type filter not working
        if ($post_type === 'all') {
            $args['post_type'] = get_post_types(array('public' => true));
        } else {
            $args['post_type'] = $post_type;
        }
        
        // Add category filter if selected
        if (!empty($category) && $category !== 'all') {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'category',
                    'field'    => 'slug',
                    'terms'    => $category,
                ),
            );
        }
        
        // Add search parameters
        if (!empty($search)) {
            if ($search_column === 'schema_links') {
                // For schema links, we need to use meta query
                $args['meta_query'] = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'schema_significant_links',
                        'value' => $search,
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => 'schema_related_links',
                        'value' => $search,
                        'compare' => 'LIKE',
                    ),
                );
            } elseif ($search_column === 'title') {
                $args['s'] = $search;
            } elseif ($search_column === 'url') {
                // We need a custom filter to search by permalink
                add_filter('posts_where', array($this, 'filter_posts_by_permalink'));
                $this->search_permalink_term = $search; // Store search term for the filter
            } elseif ($search_column === 'all') {
                // Search in title, content AND meta (schema links)
                $args['s'] = $search; // This will search in title and content
                
                // Also search in meta (schema links)
                $args['meta_query'] = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'schema_significant_links',
                        'value' => $search,
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => 'schema_related_links',
                        'value' => $search,
                        'compare' => 'LIKE',
                    ),
                );
                
                // For URL, we'll use the custom filter
                add_filter('posts_where', array($this, 'filter_posts_by_permalink'));
                $this->search_permalink_term = $search; // Store search term for the filter
            } else {
                // Default to searching in title and content
                $args['s'] = $search;
            }
        }
        
        $query = new WP_Query($args);
        
        // Remove the permalink filter if it was added
        if (!empty($search) && ($search_column === 'url' || $search_column === 'all')) {
            remove_filter('posts_where', array($this, 'filter_posts_by_permalink'));
        }
        
        $posts = array();
        
        foreach ($query->posts as $post) {
            $significant_links = get_post_meta($post->ID, 'schema_significant_links', true);
            $related_links = get_post_meta($post->ID, 'schema_related_links', true);
            
            $posts[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'post_type' => get_post_type_object($post->post_type)->labels->singular_name,
                'significant_links' => !empty($significant_links) ? explode("\n", $significant_links) : array(),
                'related_links' => !empty($related_links) ? explode("\n", $related_links) : array(),
            );
        }
        
        return array(
            'posts' => $posts,
            'total' => $query->found_posts,
            'pages' => ceil($query->found_posts / $per_page),
        );
    }
    
    /**
     * AJAX handler for updating links
     */
    public function ajax_update_links() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'schema_link_manager_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        // Get parameters
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $link_type = isset($_POST['link_type']) ? sanitize_key($_POST['link_type']) : '';
        $action = isset($_POST['action_type']) ? sanitize_key($_POST['action_type']) : '';
        $link = isset($_POST['link']) ? esc_url_raw($_POST['link']) : '';
        
        if (!$post_id || !in_array($link_type, array('significant', 'related')) || !in_array($action, array('add', 'remove'))) {
            wp_send_json_error('Invalid parameters');
        }
        
        $meta_key = $link_type === 'significant' ? 'schema_significant_links' : 'schema_related_links';
        $current_links = get_post_meta($post_id, $meta_key, true);
        $links_array = !empty($current_links) ? explode("\n", $current_links) : array();
        
        if ($action === 'add' && !empty($link)) {
            // Add link if it doesn't exist
            if (!in_array($link, $links_array)) {
                $links_array[] = $link;
                update_post_meta($post_id, $meta_key, implode("\n", $links_array));
                wp_send_json_success(array(
                    'message' => __('Link added successfully!', 'schema-link-manager'),
                    'links' => $links_array,
                ));
            } else {
                wp_send_json_error(__('Link already exists!', 'schema-link-manager'));
            }
        } elseif ($action === 'remove' && !empty($link)) {
            // Remove specific link
            $links_array = array_filter($links_array, function($item) use ($link) {
                return $item !== $link;
            });
            update_post_meta($post_id, $meta_key, implode("\n", $links_array));
            wp_send_json_success(array(
                'message' => __('Link removed successfully!', 'schema-link-manager'),
                'links' => $links_array,
            ));
        }
        
        wp_send_json_error('Invalid action');
    }
    
    /**
     * AJAX handler for removing all links
     */
    public function ajax_remove_all_links() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'schema_link_manager_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        // Get parameters
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $link_type = isset($_POST['link_type']) ? sanitize_key($_POST['link_type']) : '';
        
        if (!$post_id || !in_array($link_type, array('significant', 'related', 'all'))) {
            wp_send_json_error('Invalid parameters');
        }
        
        if ($link_type === 'all') {
            // Remove both types of links
            update_post_meta($post_id, 'schema_significant_links', '');
            update_post_meta($post_id, 'schema_related_links', '');
            wp_send_json_success(array(
                'message' => __('All links removed successfully!', 'schema-link-manager'),
                'significant_links' => array(),
                'related_links' => array(),
            ));
        } else {
            // Remove specific type of links
            $meta_key = $link_type === 'significant' ? 'schema_significant_links' : 'schema_related_links';
            update_post_meta($post_id, $meta_key, '');
            wp_send_json_success(array(
                'message' => __('Links removed successfully!', 'schema-link-manager'),
                'links' => array(),
            ));
        }
    }
    
    /**
     * Filter posts by permalink
     * This function is used as a callback for the 'posts_where' filter
     *
     * @param string $where The WHERE clause of the query
     * @return string Modified WHERE clause
     */
    public function filter_posts_by_permalink($where) {
        global $wpdb;
        
        if (!empty($this->search_permalink_term)) {
            // Escape the search term for SQL
            $search_term = '%' . $wpdb->esc_like($this->search_permalink_term) . '%';
            
            // Add permalink search to WHERE clause
            // We have to search in post_name (slug) and guid which contains the original URL
            $where .= $wpdb->prepare(
                " OR ($wpdb->posts.post_name LIKE %s OR $wpdb->posts.guid LIKE %s)",
                $search_term,
                $search_term
            );
        }
        
        return $where;
    }
}

// Initialize the admin page
new Schema_Link_Manager_Admin();
