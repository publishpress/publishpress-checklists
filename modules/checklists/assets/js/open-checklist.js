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

const STORES = ['core/editor', 'core/edit-post'];

/**
 * The cancel button of the pre-publish panel.
 */
const PUBLISH_PANEL_CANCEL_BUTTON = 'div.editor-post-publish-panel__header-cancel-button button';

const callFirstAvailable = (methodName, ...args) => {
    for (const store of STORES) {
        const dispatcher = wp.data.dispatch(store);

        if (dispatcher && typeof dispatcher[methodName] === 'function') {
            dispatcher[methodName](...args);
            return true;
        }
    }

    return false;
};

export const isPublishSidebarOpened = () => {
    for (const store of STORES) {
        const selector = wp.data.select(store);

        if (selector && typeof selector.isPublishSidebarOpened === 'function') {
            return selector.isPublishSidebarOpened() === true;
        }
    }

    return false;
};

export const closePublishSidebar = () => {
    if (!isPublishSidebarOpened()) {
        return;
    }

    /*
     * Close the panel through its own cancel button whenever there is one.
     *
     * PublishPress Statuses does not render the header button as a component:
     * it hides the stock toggle and inserts a jQuery clone of it captioned
     * "Workflow" next to it. The only place it puts that clone back is the
     * click handler of this cancel button. Closing the panel through the store
     * skips that handler, React drops the orphaned clone on its next render,
     * and the toggle it re-creates still carries the class and the
     * z-index: -999 that Statuses left behind, so the header is left without a
     * button the user can reach.
     *
     * @see https://github.com/publishpress/publishpress-checklists/issues/1215
     */
    const cancelButton = document.querySelector(PUBLISH_PANEL_CANCEL_BUTTON);

    if (cancelButton) {
        cancelButton.click();
        return;
    }

    callFirstAvailable('closePublishSidebar');
};

/**
 * The header toggle that PublishPress Statuses puts in place of the stock one.
 */
const CLONED_PUBLISH_TOGGLE = 'span.presspermit-editor-toggle button';

const openPublishSidebar = () => callFirstAvailable('openPublishSidebar');

/**
 * Keep the header publish toggle usable after we have moved the user to the
 * Checklists sidebar.
 *
 * PublishPress Statuses does not render its "Workflow" button as a component:
 * it hides the stock toggle and inserts a jQuery clone captioned "Workflow".
 * That clone lives outside React's tree, so it has no click handler of its own,
 * and once React re-renders the header the button is left looking enabled while
 * doing nothing at all. The user is then stuck with no way back to the
 * publishing workflow short of reloading the page.
 *
 * The listener below does not replace the button's own behaviour: it waits to
 * see whether the click opened the panel, and only opens it when nothing else
 * did.
 *
 * @see https://github.com/publishpress/publishpress-checklists/issues/1215
 */
const ensurePublishToggleStaysUsable = () => {
    if (window.ppChecklistsPublishToggleWatched) {
        return;
    }

    window.ppChecklistsPublishToggleWatched = true;

    document.addEventListener('click', (event) => {
        const target = event.target;

        if (!target || typeof target.closest !== 'function' || !target.closest(CLONED_PUBLISH_TOGGLE)) {
            return;
        }

        if (isPublishSidebarOpened()) {
            return;
        }

        window.setTimeout(() => {
            if (!isPublishSidebarOpened()) {
                openPublishSidebar();
            }
        }, 200);
    }, true);
};

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
    ensurePublishToggleStaysUsable();
    closePublishSidebar();

    // Deferred so the sidebar opens after the publish panel has been unmounted,
    // otherwise the publish panel keeps ownership of the sidebar region.
    window.setTimeout(openChecklistsSidebar, 0);
};
