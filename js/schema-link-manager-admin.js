(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Add link functionality
        $('.schema-link-manager-table').on('click', '.add-link-button', function() {
            const row = $(this).closest('tr');
            const postId = row.data('post-id');
            const linkType = row.find('.link-type-select').val();
            const linkUrl = row.find('.new-link-input').val().trim();
            
            if (!linkUrl) {
                alert('Please enter a valid URL');
                return;
            }
            
            // Simple URL validation
            if (!linkUrl.match(/^(https?:\/\/)/i)) {
                alert('URL must start with http:// or https://');
                return;
            }
            
            // Send AJAX request
            $.ajax({
                url: schemaLinkManager.ajaxUrl,
                type: 'POST',
                 {
                    action: 'schema_link_manager_update',
                    nonce: schemaLinkManager.nonce,
                    post_id: postId,
                    link_type: linkType,
                    action_type: 'add',
                    link: linkUrl
                },
                beforeSend: function() {
                    row.find('.add-link-button').prop('disabled', true).text('Adding...');
                },
                success: function(response) {
                    if (response.success) {
                        // Clear input
                        row.find('.new-link-input').val('');
                        
                        // Update the links list
                        const linksContainer = row.find(`.column-${linkType}-links .schema-links-container`);
                        const linksList = linksContainer.find('.schema-links-list');
                        
                        if (linksList.length === 0) {
                            // Create new list if it doesn't exist
                            linksContainer.empty().append(
                                $('<ul>', {
                                    class: `schema-links-list ${linkType}-links`
                                })
                            );
                        }
                        
                        // Add the new link to the list
                        const newLinkItem = $('<li>', {
                            class: 'schema-link-item'
                        }).append(
                            $('<span>', {
                                class: 'link-url',
                                text: linkUrl
                            }),
                            $('<button>', {
                                type: 'button',
                                class: 'remove-link',
                                'data-link-type': linkType,
                                'data-link': linkUrl
                            }).append(
                                $('<span>', {
                                    class: 'dashicons dashicons-trash'
                                })
                            )
                        );
                        
                        linksContainer.find('.schema-links-list').append(newLinkItem);
                        linksContainer.find('.no-links').remove();
                        
                        // Show success message
                        alert(schemaLinkManager.strings.linkAdded);
                    } else {
                        alert(response.data || schemaLinkManager.strings.error);
                    }
                },
                error: function() {
                    alert(schemaLinkManager.strings.error);
                },
                complete: function() {
                    row.find('.add-link-button').prop('disabled', false).text('Add');
                }
            });
        });
        
        // Remove individual link
        $('.schema-link-manager-table').on('click', '.remove-link', function() {
            const row = $(this).closest('tr');
            const postId = row.data('post-id');
            const linkType = $(this).data('link-type');
            const link = $(this).data('link');
            const linkItem = $(this).closest('.schema-link-item');
            
            // Send AJAX request
            $.ajax({
                url: schemaLinkManager.ajaxUrl,
                type: 'POST',
                 {
                    action: 'schema_link_manager_update',
                    nonce: schemaLinkManager.nonce,
                    post_id: postId,
                    link_type: linkType,
                    action_type: 'remove',
                    link: link
                },
                beforeSend: function() {
                    linkItem.css('opacity', '0.5');
                },
                success: function(response) {
                    if (response.success) {
                        // Remove the link from the list
                        linkItem.remove();
                        
                        // If no links left, show the "no links" message
                        const linksList = row.find(`.column-${linkType}-links .schema-links-list`);
                        if (linksList.children().length === 0) {
                            row.find(`.column-${linkType}-links .schema-links-container`).html(
                                $('<p>', {
                                    class: 'no-links',
                                    text: linkType === 'significant' ? 'No significant links' : 'No related links'
                                })
                            );
                        }
                        
                        // Show success message
                        alert(schemaLinkManager.strings.linkRemoved);
                    } else {
                        alert(response.data || schemaLinkManager.strings.error);
                        linkItem.css('opacity', '1');
                    }
                },
                error: function() {
                    alert(schemaLinkManager.strings.error);
                    linkItem.css('opacity', '1');
                }
            });
        });
        
        // Remove all links of a specific type
        $('.schema-link-manager-table').on('click', '.remove-significant-links, .remove-related-links, .remove-all-links-button', function() {
            const row = $(this).closest('tr');
            const postId = row.data('post-id');
            const linkType = $(this).data('link-type');
            
            if (!confirm(schemaLinkManager.strings.confirmRemoveAll)) {
                return;
            }
            
            // Send AJAX request
            $.ajax({
                url: schemaLinkManager.ajaxUrl,
                type: 'POST',
                 {
                    action: 'schema_link_manager_remove_all',
                    nonce: schemaLinkManager.nonce,
                    post_id: postId,
                    link_type: linkType
                },
                beforeSend: function() {
                    row.find('.remove-all-links .button').prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        if (linkType === 'all' || linkType === 'significant') {
                            // Clear significant links
                            row.find('.column-significant-links .schema-links-container').html(
                                $('<p>', {
                                    class: 'no-links',
                                    text: 'No significant links'
                                })
                            );
                        }
                        
                        if (linkType === 'all' || linkType === 'related') {
                            // Clear related links
                            row.find('.column-related-links .schema-links-container').html(
                                $('<p>', {
                                    class: 'no-links',
                                    text: 'No related links'
                                })
                            );
                        }
                        
                        // Show success message
                        alert(schemaLinkManager.strings.allLinksRemoved);
                    } else {
                        alert(response.data || schemaLinkManager.strings.error);
                    }
                },
                error: function() {
                    alert(schemaLinkManager.strings.error);
                },
                complete: function() {
                    row.find('.remove-all-links .button').prop('disabled', false);
                }
            });
        });
    });
})(jQuery);
