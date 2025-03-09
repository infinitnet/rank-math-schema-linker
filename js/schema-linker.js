(function(wp) {
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editor;
    const { PanelBody, TextareaControl, RadioControl, Button, Notice } = wp.components;
    const { useSelect, useDispatch } = wp.data;
    const { useState, useEffect, createElement } = wp.element;
    const { __ } = wp.i18n;

    const SchemaLinkerPanel = () => {
        // Get current values from post meta
        const { significantLinks, relatedLinks, postType } = useSelect(select => {
            const { getEditedPostAttribute } = select('core/editor');
            const meta = getEditedPostAttribute('meta') || {};
            return {
                significantLinks: meta.schema_significant_links || '',
                relatedLinks: meta.schema_related_links || '',
                postType: getEditedPostAttribute('type')
            };
        }, []);

        // Setup dispatch for saving data
        const { editPost } = useDispatch('core/editor');

        // State for the form
        const [linkType, setLinkType] = useState('significant');
        const [linksInput, setLinksInput] = useState('');
        const [notice, setNotice] = useState({ show: false, message: '', type: 'info' });

        // Check if Rank Math is active
        const isRankMathActive = useSelect(select => {
            // This is a simple check - in a real plugin you might want to check more thoroughly
            return true; // Placeholder - would need actual detection logic
        }, []);

        // Handle adding links
        const addLinks = () => {
            if (!linksInput.trim()) {
                setNotice({
                    show: true,
                    message: __('Please enter at least one URL.', 'schema-link-manager'),
                    type: 'error'
                });
                return;
            }

            // Validate and sanitize links
            const links = linksInput.split('\n').map(link => link.trim());
            const validLinks = links.filter(link => {
                // Simple URL validation
                return link.length > 0 && link.match(/^(https?:\/\/)/i);
            });

            if (validLinks.length === 0) {
                setNotice({
                    show: true,
                    message: __('No valid URLs found. URLs must start with http:// or https://.', 'schema-link-manager'),
                    type: 'error'
                });
                return;
            }

            if (validLinks.length !== links.length) {
                setNotice({
                    show: true,
                    message: __('Some URLs were invalid and have been removed.', 'schema-link-manager'),
                    type: 'warning'
                });
            }

            const sanitizedLinks = validLinks.join('\n');
            const metaKey = linkType === 'significant' ? 'schema_significant_links' : 'schema_related_links';
            const currentLinks = linkType === 'significant' ? significantLinks : relatedLinks;
            
            // Combine existing links with new ones, avoiding duplicates
            let combinedLinksArray = [];
            if (currentLinks) {
                combinedLinksArray = [...new Set([
                    ...currentLinks.split('\n'),
                    ...validLinks
                ])].filter(link => link.trim() !== '');
            } else {
                combinedLinksArray = validLinks;
            }
            
            const combinedLinks = combinedLinksArray.join('\n');
            
            // Update post meta
            editPost({ 
                meta: { 
                    [metaKey]: combinedLinks 
                } 
            });
            
            // Clear input and show success notice
            setLinksInput('');
            setNotice({
                show: true,
                message: __('Links added successfully!', 'schema-link-manager'),
                type: 'success'
            });
        };

        // Clear notice after 3 seconds
        useEffect(() => {
            if (notice.show) {
                const timer = setTimeout(() => {
                    setNotice({ ...notice, show: false });
                }, 3000);
                return () => clearTimeout(timer);
            }
        }, [notice]);

        if (!isRankMathActive) {
            return createElement(
                PanelBody,
                {},
                createElement(
                    Notice,
                    { status: "error", isDismissible: false },
                    __('Rank Math SEO plugin is required for Schema Link Manager to work.', 'schema-link-manager')
                )
            );
        }

        return createElement(
            PanelBody,
            { 
                title: __('Add Links to Schema', 'schema-link-manager'), 
                initialOpen: true 
            },
            [
                notice.show && createElement(
                    Notice,
                    { 
                        status: notice.type, 
                        isDismissible: true, 
                        onRemove: () => setNotice({ ...notice, show: false }) 
                    },
                    notice.message
                ),
                
                createElement(
                    RadioControl,
                    {
                        label: __('Link Type', 'schema-link-manager'),
                        selected: linkType,
                        options: [
                            { label: __('Significant Links', 'schema-link-manager'), value: 'significant' },
                            { label: __('Related Links', 'schema-link-manager'), value: 'related' }
                        ],
                        onChange: setLinkType,
                        help: linkType === 'significant' 
                            ? __('Important links related to this content', 'schema-link-manager')
                            : __('Other related content links', 'schema-link-manager')
                    }
                ),
                
                createElement(
                    TextareaControl,
                    {
                        label: __('Enter URLs (one per line)', 'schema-link-manager'),
                        value: linksInput,
                        onChange: setLinksInput,
                        rows: 5,
                        placeholder: "https://example.com",
                        help: __('Enter complete URLs including https://', 'schema-link-manager')
                    }
                ),
                
                createElement(
                    Button,
                    { 
                        isPrimary: true, 
                        onClick: addLinks 
                    },
                    __('Add Links', 'schema-link-manager')
                ),
                
                createElement('hr', { style: { margin: '20px 0' } }),
                
                // Significant Links section with header and Remove All button
                createElement(
                    'div',
                    { className: 'schema-links-header' },
                    [
                        createElement('h3', {}, __('Current Significant Links', 'schema-link-manager')),
                        significantLinks && significantLinks.split('\n').filter(link => link.trim()).length > 0 
                            ? createElement(
                                Button,
                                {
                                    isDestructive: true,
                                    isSmall: true,
                                    onClick: () => {
                                        if (confirm(__('Are you sure you want to remove all significant links?', 'schema-link-manager'))) {
                                            editPost({ meta: { schema_significant_links: '' } });
                                            setNotice({
                                                show: true,
                                                message: __('All significant links removed.', 'schema-link-manager'),
                                                type: 'success'
                                            });
                                        }
                                    }
                                },
                                __('Remove All', 'schema-link-manager')
                            )
                            : null
                    ]
                ),

                // Significant Links list
                significantLinks && significantLinks.split('\n').filter(link => link.trim()).length > 0 
                    ? createElement(
                        'ul',
                        { className: 'schema-links-list' },
                        significantLinks.split('\n')
                            .filter(link => link.trim())
                            .map((link, index) => createElement(
                                'li',
                                { key: `significant-${index}`, className: 'schema-link-item' },
                                [
                                    createElement(
                                        'span',
                                        { className: 'schema-link-url' },
                                        link
                                    ),
                                    createElement(
                                        Button,
                                        { 
                                            isDestructive: true,
                                            isSmall: true,
                                            onClick: () => {
                                                const links = significantLinks.split('\n').filter(l => l.trim());
                                                links.splice(index, 1);
                                                editPost({ meta: { schema_significant_links: links.join('\n') } });
                                            },
                                            icon: 'trash',
                                            label: __('Remove link', 'schema-link-manager')
                                        }
                                    )
                                ]
                            ))
                    )
                    : createElement(
                        'p',
                        { className: 'schema-no-links' },
                        __('No significant links added yet.', 'schema-link-manager')
                    ),
                    
                // Related Links section with header and Remove All button
                createElement(
                    'div',
                    { className: 'rank-math-schema-links-header' },
                    [
                        createElement('h3', {}, __('Current Related Links', 'schema-link-manager')),
                        relatedLinks && relatedLinks.split('\n').filter(link => link.trim()).length > 0 
                            ? createElement(
                                Button,
                                {
                                    isDestructive: true,
                                    isSmall: true,
                                    onClick: () => {
                                        if (confirm(__('Are you sure you want to remove all related links?', 'schema-link-manager'))) {
                                            editPost({ meta: { schema_related_links: '' } });
                                            setNotice({
                                                show: true,
                                                message: __('All related links removed.', 'schema-link-manager'),
                                                type: 'success'
                                            });
                                        }
                                    }
                                },
                                __('Remove All', 'schema-link-manager')
                            )
                            : null
                    ]
                ),

                // Related Links list
                relatedLinks && relatedLinks.split('\n').filter(link => link.trim()).length > 0
                    ? createElement(
                        'ul',
                        { className: 'rank-math-schema-links-list' },
                        relatedLinks.split('\n')
                            .filter(link => link.trim())
                            .map((link, index) => createElement(
                                'li',
                                { key: `related-${index}`, className: 'schema-link-item' },
                                [
                                    createElement(
                                        'span',
                                        { className: 'schema-link-url' },
                                        link
                                    ),
                                    createElement(
                                        Button,
                                        { 
                                            isDestructive: true,
                                            isSmall: true,
                                            onClick: () => {
                                                const links = relatedLinks.split('\n').filter(l => l.trim());
                                                links.splice(index, 1);
                                                editPost({ meta: { schema_related_links: links.join('\n') } });
                                            },
                                            icon: 'trash',
                                            label: __('Remove link', 'schema-link-manager')
                                        }
                                    )
                                ]
                            ))
                    )
                    : createElement(
                        'p',
                        { className: 'schema-no-links' },
                        __('No related links added yet.', 'schema-link-manager')
                    )
            ].filter(Boolean)
        );
    };

    const SchemaLinkerSidebar = () => {
        return [
            createElement(
                PluginSidebarMoreMenuItem,
                {
                    target: "schema-link-manager-sidebar",
                    icon: "admin-links",
                    key: "menu-item"
                },
                __('Schema Links', 'schema-link-manager')
            ),
            createElement(
                PluginSidebar,
                {
                    name: "schema-link-manager-sidebar",
                    title: __('Schema Links', 'schema-link-manager'),
                    icon: "admin-links",
                    key: "sidebar"
                },
                createElement(SchemaLinkerPanel, {})
            )
        ];
    };

    registerPlugin('schema-link-manager', {
        render: SchemaLinkerSidebar,
        icon: 'admin-links'
    });
})(window.wp);
