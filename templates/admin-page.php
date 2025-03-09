<?php
/**
 * Admin page template for Schema Link Manager
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap schema-link-manager-admin">
    <h1><?php _e('Schema Link Manager', 'schema-link-manager'); ?></h1>
    
    <div class="schema-link-manager-filters">
        <form method="get">
            <input type="hidden" name="page" value="schema-link-manager">
            <?php wp_nonce_field('schema_link_manager_filter_nonce', '_wpnonce'); ?>
            
            <div class="filters-row">
                <div class="filter-group">
                    <label for="post_type"><?php _e('Post Type:', 'schema-link-manager'); ?></label>
                    <select name="post_type" id="post_type">
                        <option value="all" <?php selected($post_type, 'all'); ?>><?php _e('All Post Types', 'schema-link-manager'); ?></option>
                        <?php foreach ($post_types as $type_name => $type_label): ?>
                            <option value="<?php echo esc_attr($type_name); ?>" <?php selected($post_type, $type_name); ?>>
                                <?php echo esc_html($type_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="search_column"><?php _e('Search In:', 'schema-link-manager'); ?></label>
                    <select name="search_column" id="search_column">
                        <option value="all" <?php selected($search_column, 'all'); ?>><?php _e('All Columns', 'schema-link-manager'); ?></option>
                        <option value="title" <?php selected($search_column, 'title'); ?>><?php _e('Title', 'schema-link-manager'); ?></option>
                        <option value="url" <?php selected($search_column, 'url'); ?>><?php _e('URL', 'schema-link-manager'); ?></option>
                        <option value="schema_links" <?php selected($search_column, 'schema_links'); ?>><?php _e('Schema Links', 'schema-link-manager'); ?></option>
                    </select>
                </div>
                
                <div class="filter-group search-group">
                    <input type="search" name="s" value="<?php echo esc_attr($search_term); ?>" placeholder="<?php esc_attr_e('Search...', 'schema-link-manager'); ?>">
                    <button type="submit" class="button"><?php _e('Search', 'schema-link-manager'); ?></button>
                </div>
                
                <div class="filter-group">
                    <label for="per_page"><?php _e('Per Page:', 'schema-link-manager'); ?></label>
                    <select name="per_page" id="per_page">
                        <option value="10" <?php selected($posts_per_page, 10); ?>>10</option>
                        <option value="20" <?php selected($posts_per_page, 20); ?>>20</option>
                        <option value="50" <?php selected($posts_per_page, 50); ?>>50</option>
                        <option value="100" <?php selected($posts_per_page, 100); ?>>100</option>
                    </select>
                </div>
            </div>
        </form>
    </div>
    
    <div class="schema-link-manager-table-container">
        <?php if (empty($posts_data['posts'])): ?>
            <div class="schema-link-manager-no-results">
                <p><?php _e('No posts found matching your criteria.', 'schema-link-manager'); ?></p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped schema-link-manager-table">
                <thead>
                    <tr>
                        <th class="column-title"><?php _e('Title', 'schema-link-manager'); ?></th>
                        <th class="column-type"><?php _e('Type', 'schema-link-manager'); ?></th>
                        <th class="column-url"><?php _e('URL', 'schema-link-manager'); ?></th>
                        <th class="column-significant-links"><?php _e('Significant Links', 'schema-link-manager'); ?></th>
                        <th class="column-related-links"><?php _e('Related Links', 'schema-link-manager'); ?></th>
                        <th class="column-actions"><?php _e('Actions', 'schema-link-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts_data['posts'] as $post): ?>
                        <tr data-post-id="<?php echo esc_attr($post['id']); ?>">
                            <td class="column-title">
                                <strong>
                                    <a href="<?php echo esc_url(get_edit_post_link($post['id'])); ?>" target="_blank">
                                        <?php echo esc_html($post['title']); ?>
                                    </a>
                                </strong>
                            </td>
                            <td class="column-type">
                                <?php echo esc_html($post['post_type']); ?>
                            </td>
                            <td class="column-url">
                                <a href="<?php echo esc_url($post['url']); ?>" target="_blank">
                                    <?php echo esc_url($post['url']); ?>
                                </a>
                            </td>
                            <td class="column-significant-links">
                                <div class="schema-links-container">
                                    <?php if (empty($post['significant_links'])): ?>
                                        <p class="no-links"><?php _e('No significant links', 'schema-link-manager'); ?></p>
                                    <?php else: ?>
                                        <ul class="schema-links-list significant-links">
                                            <?php foreach ($post['significant_links'] as $link): ?>
                                                <li class="schema-link-item">
                                                    <span class="link-url"><?php echo esc_url($link); ?></span>
                                                    <button type="button" class="remove-link" data-link-type="significant" data-link="<?php echo esc_attr($link); ?>">
                                                        <span class="dashicons dashicons-trash"></span>
                                                    </button>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="column-related-links">
                                <div class="schema-links-container">
                                    <?php if (empty($post['related_links'])): ?>
                                        <p class="no-links"><?php _e('No related links', 'schema-link-manager'); ?></p>
                                    <?php else: ?>
                                        <ul class="schema-links-list related-links">
                                            <?php foreach ($post['related_links'] as $link): ?>
                                                <li class="schema-link-item">
                                                    <span class="link-url"><?php echo esc_url($link); ?></span>
                                                    <button type="button" class="remove-link" data-link-type="related" data-link="<?php echo esc_attr($link); ?>">
                                                        <span class="dashicons dashicons-trash"></span>
                                                    </button>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="column-actions">
                                <div class="schema-link-actions">
                                    <div class="add-link-form">
                                        <select class="link-type-select">
                                            <option value="significant"><?php _e('Significant', 'schema-link-manager'); ?></option>
                                            <option value="related"><?php _e('Related', 'schema-link-manager'); ?></option>
                                        </select>
                                        <input type="url" class="new-link-input" placeholder="<?php esc_attr_e('https://example.com', 'schema-link-manager'); ?>">
                                        <button type="button" class="button add-link-button"><?php _e('Add', 'schema-link-manager'); ?></button>
                                    </div>
                                    <div class="remove-all-links">
                                        <button type="button" class="button remove-significant-links" data-link-type="significant">
                                            <?php _e('Remove All Significant', 'schema-link-manager'); ?>
                                        </button>
                                        <button type="button" class="button remove-related-links" data-link-type="related">
                                            <?php _e('Remove All Related', 'schema-link-manager'); ?>
                                        </button>
                                        <button type="button" class="button remove-all-links-button" data-link-type="all">
                                            <?php _e('Remove All Links', 'schema-link-manager'); ?>
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
                <div class="schema-link-manager-pagination">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(
                                _n('%s item', '%s items', $total_posts, 'schema-link-manager'),
                                number_format_i18n($total_posts)
                            ); ?>
                        </span>
                        
                        <span class="pagination-links">
                            <?php
                            // First page link
                            if ($current_page > 1) {
                                printf(
                                    '<a class="first-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">«</span></a>',
                                    esc_url(add_query_arg(array('paged' => 1))),
                                    __('First page', 'schema-link-manager')
                                );
                            } else {
                                echo '<span class="first-page button disabled"><span class="screen-reader-text">' . __('First page', 'schema-link-manager') . '</span><span aria-hidden="true">«</span></span>';
                            }
                            
                            // Previous page link
                            if ($current_page > 1) {
                                printf(
                                    '<a class="prev-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">‹</span></a>',
                                    esc_url(add_query_arg(array('paged' => max(1, $current_page - 1)))),
                                    __('Previous page', 'schema-link-manager')
                                );
                            } else {
                                echo '<span class="prev-page button disabled"><span class="screen-reader-text">' . __('Previous page', 'schema-link-manager') . '</span><span aria-hidden="true">‹</span></span>';
                            }
                            
                            // Current page text
                            printf(
                                '<span class="paging-input"><span class="tablenav-paging-text">%s / <span class="total-pages">%s</span></span></span>',
                                $current_page,
                                $total_pages
                            );
                            
                            // Next page link
                            if ($current_page < $total_pages) {
                                printf(
                                    '<a class="next-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">›</span></a>',
                                    esc_url(add_query_arg(array('paged' => min($total_pages, $current_page + 1)))),
                                    __('Next page', 'schema-link-manager')
                                );
                            } else {
                                echo '<span class="next-page button disabled"><span class="screen-reader-text">' . __('Next page', 'schema-link-manager') . '</span><span aria-hidden="true">›</span></span>';
                            }
                            
                            // Last page link
                            if ($current_page < $total_pages) {
                                printf(
                                    '<a class="last-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">»</span></a>',
                                    esc_url(add_query_arg(array('paged' => $total_pages))),
                                    __('Last page', 'schema-link-manager')
                                );
                            } else {
                                echo '<span class="last-page button disabled"><span class="screen-reader-text">' . __('Last page', 'schema-link-manager') . '</span><span aria-hidden="true">»</span></span>';
                            }
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
