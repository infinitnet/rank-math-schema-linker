(function(wp) {
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editor;
    const { PanelBody, TextareaControl, RadioControl, Button, Notice } = wp.components;
    const { useSelect, useDispatch } = wp.data;
    const { useState, useEffect } = wp.element;
    const { __ } = wp.i18n;

    const SchemaLinkerPanel = () => {
        // Get current values from post meta
        const { significantLinks, relatedLinks, postType } = useSelect(select => {
            const { getEditedPostAttribute } = select('core/editor');
            const meta = getEditedPostAttribute('meta') || {};
            return {
                significantLinks: meta.rank_math_significant_links || '',
                relatedLinks: meta.rank_math_related_links || '',
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
                    message: __('Please enter at least one URL.', 'rank-math-schema-linker'),
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
                    message: __('No valid URLs found. URLs must start with http:// or https://.', 'rank-math-schema-linker'),
                    type: 'error'
                });
                return;
            }

            if (validLinks.length !== links.length) {
                setNotice({
                    show: true,
                    message: __('Some URLs were invalid and have been removed.', 'rank-math-schema-linker'),
                    type: 'warning'
                });
            }

            const sanitizedLinks = validLinks.join('\n');
            const metaKey = linkType === 'significant' ? 'rank_math_significant_links' : 'rank_math_related_links';
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
                message: __('Links added successfully!', 'rank-math-schema-linker'),
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
            return (
                <PanelBody>
                    <Notice status="error" isDismissible={false}>
                        {__('Rank Math SEO plugin is required for Schema Linker to work.', 'rank-math-schema-linker')}
                    </Notice>
                </PanelBody>
            );
        }

        return (
            <PanelBody title={__('Add Links to Schema', 'rank-math-schema-linker')} initialOpen={true}>
                {notice.show && (
                    <Notice status={notice.type} isDismissible={true} onRemove={() => setNotice({ ...notice, show: false })}>
                        {notice.message}
                    </Notice>
                )}
                
                <RadioControl
                    label={__('Link Type', 'rank-math-schema-linker')}
                    selected={linkType}
                    options={[
                        { label: __('Significant Links', 'rank-math-schema-linker'), value: 'significant' },
                        { label: __('Related Links', 'rank-math-schema-linker'), value: 'related' }
                    ]}
                    onChange={setLinkType}
                    help={
                        linkType === 'significant' 
                            ? __('Important links related to this content', 'rank-math-schema-linker')
                            : __('Other related content links', 'rank-math-schema-linker')
                    }
                />
                
                <TextareaControl
                    label={__('Enter URLs (one per line)', 'rank-math-schema-linker')}
                    value={linksInput}
                    onChange={setLinksInput}
                    rows={5}
                    placeholder="https://example.com"
                    help={__('Enter complete URLs including https://', 'rank-math-schema-linker')}
                />
                
                <Button isPrimary onClick={addLinks}>
                    {__('Add Links', 'rank-math-schema-linker')}
                </Button>
                
                <hr style={{ margin: '20px 0' }} />
                
                <h3>{__('Current Significant Links', 'rank-math-schema-linker')}</h3>
                <TextareaControl
                    value={significantLinks}
                    onChange={(value) => editPost({ meta: { rank_math_significant_links: value } })}
                    rows={5}
                />
                
                <h3>{__('Current Related Links', 'rank-math-schema-linker')}</h3>
                <TextareaControl
                    value={relatedLinks}
                    onChange={(value) => editPost({ meta: { rank_math_related_links: value } })}
                    rows={5}
                />
            </PanelBody>
        );
    };

    const SchemaLinkerSidebar = () => {
        return (
            <>
                <PluginSidebarMoreMenuItem
                    target="rank-math-schema-linker-sidebar"
                    icon="admin-links"
                >
                    {__('Schema Links', 'rank-math-schema-linker')}
                </PluginSidebarMoreMenuItem>
                <PluginSidebar
                    name="rank-math-schema-linker-sidebar"
                    title={__('Schema Links', 'rank-math-schema-linker')}
                    icon="admin-links"
                >
                    <SchemaLinkerPanel />
                </PluginSidebar>
            </>
        );
    };

    registerPlugin('rank-math-schema-linker', {
        render: SchemaLinkerSidebar,
        icon: 'admin-links'
    });
})(window.wp);
