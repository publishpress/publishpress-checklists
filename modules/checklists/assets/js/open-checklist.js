/**
 * Close the publishing workflow (pre-publish) panel and open the Checklists
 * sidebar so the user can immediately resolve the unmet requirements.
 *
 * While the publish panel is open, the Checklists toolbar button is removed
 * from the editor header, so this is the only route back to the checklist.
 *
 * The publish sidebar action lived in core/edit-post before WordPress 6.6 and
 * moved to core/editor afterwards, so we handle both.
 */
export const CHECKLISTS_SIDEBAR = 'publishpress-checklists-panel/checklists-sidebar';

const callFirstAvailable = (methodName, ...args) => {
    const stores = ['core/editor', 'core/edit-post'];

    for (const store of stores) {
        const dispatcher = wp.data.dispatch(store);

        if (dispatcher && typeof dispatcher[methodName] === 'function') {
            dispatcher[methodName](...args);
            return true;
        }
    }

    return false;
};

export const closePublishSidebar = () => callFirstAvailable('closePublishSidebar');

export const openChecklistsSidebar = () => {
    if (callFirstAvailable('openGeneralSidebar', CHECKLISTS_SIDEBAR)) {
        return;
    }

    // Fallback for editors that only expose the interface store.
    const interfaceDispatch = wp.data.dispatch('core/interface');
    if (interfaceDispatch && typeof interfaceDispatch.enableComplementaryArea === 'function') {
        interfaceDispatch.enableComplementaryArea('core/edit-post', CHECKLISTS_SIDEBAR);
    }
};

/**
 * Leave the publishing workflow and reveal the Checklists sidebar.
 */
export const openChecklistFromWarning = () => {
    closePublishSidebar();

    // Deferred so the sidebar opens after the publish panel has been unmounted,
    // otherwise the publish panel keeps ownership of the sidebar region.
    window.setTimeout(openChecklistsSidebar, 0);
};
