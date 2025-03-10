// Global function to handle tab switching directly
function switchTab(button, tabType) {
    const row = jQuery(button).closest('tr');
    
    // Update active tab
    row.find('.link-tab-button').removeClass('active');
    jQuery(button).addClass('active');
    
    // Show corresponding input container
    row.find('.link-input-container').removeClass('active');
    row.find('.' + tabType + '-link-container').addClass('active');
}

(function($) {
    'use strict';
    
    /**
     * Show notification message
     *
     * @param {string} message - Message to display
     * @param {string} type - Type of notification (success, error, warning)
     * @param {number} duration - Duration in milliseconds
     */
    function showNotification(message, type = 'success', duration = 3000) {
        // Remove any existing notifications
        $('.schema-notification').remove();
        
        // Create notification element
        const notification = $('<div>', {
            'class': `schema-notification schema-notification-${type}`,
            'text': message
        });
        
        // Add to DOM
        $('body').append(notification);
        
        // Trigger animation to show
        setTimeout(function() {
            notification.addClass('show');
        }, 10);
        
        // Set timeout to hide and remove
        setTimeout(function() {
            notification.removeClass('show');
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, duration);
    }
    
    $(document).ready(function() {
        
        // Initialize search functionality
        $('.schema-link-manager-filters form').on('submit', function(event) {
            // Ensure empty search fields are not included in the URL
            const searchInput = $(this).find('input[name="s"]');
            if (searchInput.val().trim() === '') {
                searchInput.prop('disabled', true);
                setTimeout(function() {
                    searchInput.prop('disabled', false);
                }, 100);
            }
            
            // Reset pagination to page 1 when searching
            $(this).find('input[name="paged"]').val(1);
        });
        
        // Enhance filter dropdowns with select2
        if ($.fn.select2) {
            $('#post_type, #category, #search_column, #per_page').select2({
                minimumResultsForSearch: 10,
                width: '100%'
            });
        }
        
        // Handle table sorting - already set up with URL parameters in template
        
        // Single link addition
        $('.schema-link-manager-table').on('click', '.add-link-button', function() {
            const row = $(this).closest('tr');
            const postId = row.data('post-id');
            const linkType = row.find('.link-type-select').val();
            const linkUrl = row.find('.new-link-input').val().trim();
            
            if (!linkUrl) {
                showNotification('Please enter a valid URL', 'error');
                return;
            }
            
            // Simple URL validation
            if (!linkUrl.match(/^(https?:\/\/)/i)) {
                showNotification('URL must start with http:// or https://', 'error');
                return;
            }
            
            addSingleLink(row, postId, linkType, linkUrl);
        });
        
        // Bulk links addition
        $('.schema-link-manager-table').on('click', '.add-bulk-links-button', function() {
            const row = $(this).closest('tr');
            const postId = row.data('post-id');
            const linkType = row.find('.link-type-select').val();
            const linksText = row.find('.bulk-links-input').val().trim();
            
            if (!linksText) {
                showNotification('Please enter at least one URL', 'error');
                return;
            }
            
            // Split by newlines and process
            const links = linksText.split('\n').map(link => link.trim()).filter(link => link);
            
            if (links.length === 0) {
                showNotification('Please enter at least one URL', 'error');
                return;
            }
            
            // Validate URLs
            const validLinks = links.filter(link => link.match(/^(https?:\/\/)/i));
            
            if (validLinks.length === 0) {
                showNotification('No valid URLs found. URLs must start with http:// or https://', 'error');
                return;
            }
            
            if (validLinks.length !== links.length) {
                showNotification('Some URLs were invalid and will be skipped.', 'warning');
            }
            
            // Disable button during operation
            const bulkAddButton = $(this);
            bulkAddButton.prop('disabled', true).text('Adding...');
            
            // Process links sequentially
            let processedCount = 0;
            let successCount = 0;
            
            function processNextLink(index) {
                if (index >= validLinks.length) {
                    // All links processed
                    bulkAddButton.prop('disabled', false).text('Add All');
                    row.find('.bulk-links-input').val('');
                    
                    if (successCount > 0) {
                        showNotification(`Added ${successCount} links successfully!`, 'success');
                    } else {
                        showNotification('No new links were added. They may already exist.', 'warning');
                    }
                    return;
                }
                
                const link = validLinks[index];
                
                // Send AJAX request
                $.ajax({
                    url: schemaLinkManager.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'schema_link_manager_update',
                        nonce: schemaLinkManager.nonce,
                        post_id: postId,
                        link_type: linkType,
                        action_type: 'add',
                        link: link
                    },
                    success: function(response) {
                        processedCount++;
                        
                        if (response.success) {
                            successCount++;
                            
                            // Update the UI with the new link
                            updateLinksList(row, linkType, link);
                        }
                        
                        // Process next link
                        processNextLink(index + 1);
                    },
                    error: function() {
                        processedCount++;
                        // Continue with next link even if there's an error
                        processNextLink(index + 1);
                    }
                });
            }
            
            // Start processing
            processNextLink(0);
        });
        
        /**
         * Add a single link to the schema
         *
         * @param {jQuery} row The table row element
         * @param {number} postId The post ID
         * @param {string} linkType The link type (significant or related)
         * @param {string} linkUrl The URL to add
         */
        function addSingleLink(row, postId, linkType, linkUrl) {
            // Send AJAX request
            $.ajax({
                url: schemaLinkManager.ajaxUrl,
                type: 'POST',
                data: {
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
                        updateLinksList(row, linkType, linkUrl);
                        
                        // Show success message
                        showNotification(schemaLinkManager.strings.linkAdded, 'success');
                    } else {
                        showNotification(response.data || schemaLinkManager.strings.error, 'error');
                    }
                },
                error: function() {
                    showNotification(schemaLinkManager.strings.error, 'error');
                },
                complete: function() {
                    row.find('.add-link-button').prop('disabled', false).text('Add');
                }
            });
        }
        
        /**
         * Update the links list in the UI
         *
         * @param {jQuery} row The table row element
         * @param {string} linkType The link type (significant or related)
         * @param {string} linkUrl The URL to add
         */
        function updateLinksList(row, linkType, linkUrl) {
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
        }
        
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
                data: {
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
                        showNotification(schemaLinkManager.strings.linkRemoved, 'success');
                    } else {
                        showNotification(response.data || schemaLinkManager.strings.error, 'error');
                        linkItem.css('opacity', '1');
                    }
                },
                error: function() {
                    showNotification(schemaLinkManager.strings.error, 'error');
                    linkItem.css('opacity', '1');
                }
            });
        });
        
        // Remove all links of a specific type
        $('.schema-link-manager-table').on('click', '.remove-significant-links, .remove-related-links, .remove-all-links-button', function() {
            const row = $(this).closest('tr');
            const postId = row.data('post-id');
            const linkType = $(this).data('link-type');
            
            // Show confirmation UI
            const confirmMessage = schemaLinkManager.strings.confirmRemoveAll;
            const confirmAction = function() {
                removeAllLinks(row, postId, linkType);
            };
            
            // Create and show confirmation
            showConfirmationDialog(confirmMessage, confirmAction);
            return;
        });
        
        /**
         * Shows a confirmation dialog with Yes/No options
         *
         * @param {string} message - Confirmation message
         * @param {Function} onConfirm - Function to execute on confirm
         */
        function showConfirmationDialog(message, onConfirm) {
            // Remove any existing dialogs
            $('.schema-confirmation-dialog').remove();
            
            // Create the dialog
            const dialog = $('<div>', {
                'class': 'schema-notification schema-confirmation-dialog',
                'css': {
                    'max-width': '400px',
                    'background-color': '#f0f0f1',
                    'color': '#3c434a',
                    'border-left': '4px solid #72aee6',
                    'padding': '15px'
                }
            });
            
            // Add message
            dialog.append(
                $('<p>', {
                    text: message,
                    'css': {
                        'margin-bottom': '15px'
                    }
                })
            );
            
            // Add buttons container
            const buttonsContainer = $('<div>', {
                'css': {
                    'display': 'flex',
                    'gap': '10px',
                    'justify-content': 'flex-end'
                }
            });
            
            // Add Yes button
            buttonsContainer.append(
                $('<button>', {
                    'class': 'button button-primary',
                    text: 'Yes',
                    'click': function() {
                        dialog.removeClass('show');
                        setTimeout(function() {
                            dialog.remove();
                            onConfirm();
                        }, 300);
                    }
                })
            );
            
            // Add No button
            buttonsContainer.append(
                $('<button>', {
                    'class': 'button',
                    text: 'No',
                    'click': function() {
                        dialog.removeClass('show');
                        setTimeout(function() {
                            dialog.remove();
                        }, 300);
                    }
                })
            );
            
            // Add buttons to dialog
            dialog.append(buttonsContainer);
            
            // Add to DOM
            $('body').append(dialog);
            
            // Show the dialog
            setTimeout(function() {
                dialog.addClass('show');
            }, 10);
        }
        
        /**
         * Removes all links of a specific type
         */
        function removeAllLinks(row, postId, linkType) {
            
            // Send AJAX request
            $.ajax({
                url: schemaLinkManager.ajaxUrl,
                type: 'POST',
                data: {
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
                        showNotification(schemaLinkManager.strings.allLinksRemoved, 'success');
                    } else {
                        showNotification(response.data || schemaLinkManager.strings.error, 'error');
                    }
                },
                error: function() {
                    showNotification(schemaLinkManager.strings.error, 'error');
                },
                complete: function() {
                    row.find('.remove-all-links .button').prop('disabled', false);
                }
            });
        }
    });
})(jQuery);
