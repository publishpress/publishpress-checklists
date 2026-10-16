const DOCUMENT_SIDEBAR = 'edit-post/document';
const PANEL_LOOKUP_ATTEMPTS = 20;
const PANEL_LOOKUP_INTERVAL = 100;

/**
 * Returns the first store, among the given names, that implements the method.
 */
const getStoreWith = (storeNames, method, isDispatch) => {
    for (const storeName of storeNames) {
        const store = isDispatch ? wp.data.dispatch(storeName) : wp.data.select(storeName);
        if (store && typeof store[method] === 'function') {
            return store;
        }
    }

    return null;
};

/**
 * Opens the regular document sidebar, which also closes the Checklists sidebar,
 * since only one general sidebar can be open at a time.
 */
const openDocumentSidebar = (sidebarName = DOCUMENT_SIDEBAR) => {
    const store = getStoreWith(['core/edit-post', 'core/editor'], 'openGeneralSidebar', true);
    if (store) {
        store.openGeneralSidebar(sidebarName);
    }
};

/**
 * Makes sure the given editor panel is enabled and expanded.
 */
const openEditorPanel = (panel) => {
    if (!panel) {
        return;
    }

    const select = getStoreWith(['core/editor', 'core/edit-post'], 'isEditorPanelOpened', false);
    const dispatch = getStoreWith(['core/editor', 'core/edit-post'], 'toggleEditorPanelOpened', true);

    if (!select || !dispatch) {
        return;
    }

    if (typeof select.isEditorPanelEnabled === 'function'
        && !select.isEditorPanelEnabled(panel)
        && typeof dispatch.toggleEditorPanelEnabled === 'function') {
        dispatch.toggleEditorPanelEnabled(panel);
    }

    if (!select.isEditorPanelOpened(panel)) {
        dispatch.toggleEditorPanelOpened(panel);
    }
};

const normalizeTitle = (value) => (value || '').replace(/\s+/g, ' ').trim().toLowerCase();

/**
 * Finds a document sidebar panel by its visible title.
 */
const findPanelByTitles = (titles) => {
    const wanted = (titles || []).map(normalizeTitle).filter(Boolean);
    if (wanted.length === 0) {
        return null;
    }

    const panels = document.querySelectorAll('.components-panel__body, .editor-post-panel__row');

    for (const panel of panels) {
        const titleNode = panel.querySelector('.components-panel__body-title, .components-panel__body-toggle, .editor-post-panel__row-label, button');
        const title = normalizeTitle(titleNode ? titleNode.textContent : '');

        if (title && wanted.some((candidate) => title === candidate || title.startsWith(candidate))) {
            return panel;
        }
    }

    return null;
};

const scrollAndFocus = (panel, focusSelector) => {
    panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
    panel.classList.add('pp-checklists-highlight-target');
    window.setTimeout(() => panel.classList.remove('pp-checklists-highlight-target'), 2000);

    const target = focusSelector ? panel.querySelector(focusSelector) : null;
    const focusable = target || panel.querySelector('input, textarea, select, button');

    if (focusable && typeof focusable.focus === 'function') {
        focusable.focus({ preventScroll: true });
    }
};

/**
 * Panels are rendered asynchronously, so we retry for a short while.
 */
const waitForPanel = (titles, focusSelector, attempt = 0) => {
    const panel = findPanelByTitles(titles);

    if (panel) {
        scrollAndFocus(panel, focusSelector);
        return;
    }

    if (attempt < PANEL_LOOKUP_ATTEMPTS) {
        window.setTimeout(() => waitForPanel(titles, focusSelector, attempt + 1), PANEL_LOOKUP_INTERVAL);
    }
};

const focusPostTitle = () => {
    const title = document.querySelector('.editor-post-title__input, .wp-block-post-title, #post-title-0');

    if (title) {
        title.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (typeof title.focus === 'function') {
            title.focus({ preventScroll: true });
        }
    }
};

const focusPostContent = () => {
    const blockEditor = wp.data.select('core/block-editor');
    const blockDispatch = wp.data.dispatch('core/block-editor');

    if (blockEditor && blockDispatch && typeof blockEditor.getBlockOrder === 'function') {
        const blocks = blockEditor.getBlockOrder();
        if (blocks && blocks.length > 0) {
            blockDispatch.selectBlock(blocks[0]);
        }
    }

    const content = document.querySelector('.block-editor-writing-flow, .editor-styles-wrapper');
    if (content) {
        content.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

/**
 * Runs the direct action of a requirement: leaves the Checklists sidebar and
 * takes the user to the place where the requirement can be resolved.
 */
const runRequirementAction = (action) => {
    if (!action) {
        return;
    }

    if (action.target === 'title') {
        openDocumentSidebar();
        focusPostTitle();
        return;
    }

    if (action.target === 'content') {
        openDocumentSidebar();
        focusPostContent();
        return;
    }

    if (action.target === 'yoast') {
        openDocumentSidebar('yoast-seo/seo-sidebar');
        return;
    }

    openDocumentSidebar();
    openEditorPanel(action.panel);
    waitForPanel(action.titles, action.focus);
};

export { runRequirementAction };
