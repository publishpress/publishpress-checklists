/**
 * @package PublishPress Checklists
 *
 * Block editor compatibility bridge.
 *
 * The core requirement engine was rewritten to run entirely off wp.data (see
 * checklist-evaluators.js) so that no classic meta box is registered and
 * WordPress Real Time Collaboration stays enabled. See issue #1208.
 *
 * However, satellite integrations (Yoast SEO, Permalinks, and PublishPress
 * Checklists Pro) ship their own scripts that are hard-wired to the old engine:
 * they read/write `#pp-checklists-req-<id>` DOM nodes, listen for the
 * `pp-checklists:tic` event, and call helpers on `window.PP_Checklists`.
 *
 * This bridge keeps those scripts working without a registered meta box:
 *   - It exposes the `window.PP_Checklists` public surface they rely on.
 *   - It renders hidden (not registered as a meta box, so RTC-safe) requirement
 *     nodes for the requirements the React panel does not own.
 *   - It relays their status updates to the React panel via a wp.hooks action.
 */

import { helpers, isPanelOwned } from './checklist-evaluators.js';

const EVENTS = {
    EVENT_VALIDATE_REQUIREMENTS: 'pp-checklists:validate_requirements',
    EVENT_TIC: 'pp-checklists:tic',
    EVENT_UPDATE_REQUIREMENT_STATE: 'pp-checklists:update_requirement_state',
    EVENT_TOGGLE_CUSTOM_ITEM: 'pp-checklists:toggle_custom_item',
    EVENT_TINYMCE_LOADED: 'pp-checklists:tinymce_loaded',
};

const TIC_INTERVAL = 300;

// Action fired when a bridged (third-party) requirement changes status, so the
// React panel can reflect it.
export const BRIDGE_STATUS_ACTION = 'pp-checklists.bridge-requirement-updated';

const editor = () => wp.data.select('core/editor');

function getPostStatus() {
    const post = editor().getCurrentPost();
    return post && post.status ? post.status : '';
}

/**
 * Define window.PP_Checklists with the public API third-party scripts use.
 * In the Classic Editor meta-box.js defines this; in the block editor this
 * bridge provides a DOM-free equivalent.
 */
function definePublicApi() {
    if (typeof window === 'undefined') {
        return;
    }

    // If meta-box.js already defined the full object (should not happen in the
    // block editor), leave it untouched.
    if (window.PP_Checklists && window.PP_Checklists.__ppchBridge !== true && window.PP_Checklists.EVENT_TIC) {
        return;
    }

    window.PP_Checklists = {
        __ppchBridge: true,

        ...EVENTS,
        TIC_INTERVAL,

        is_gutenberg_active() {
            return (
                typeof wp !== 'undefined' &&
                typeof wp.data !== 'undefined' &&
                typeof wp.data.select('core/editor') !== 'undefined'
            );
        },

        getEditor() {
            return wp.data.select('core/editor');
        },

        get_editor_content: helpers.getContent,

        is_published() {
            return getPostStatus() === 'publish';
        },
        is_pending() {
            return getPostStatus() === 'pending';
        },
        is_draft() {
            const status = getPostStatus();
            return status === 'draft' || status === 'auto-draft';
        },

        // Pure helpers reused by third-party scripts and block-highlighting.
        check_valid_quantity: helpers.check_valid_quantity,
        extract_internal_links: helpers.extract_internal_links,
        extract_external_links: helpers.extract_external_links,
        missing_alt_images: helpers.missing_alt_images,
        get_image_alt_lengths: helpers.get_image_alt_lengths,
        extract_links_from_content: helpers.extract_links_from_content,
        is_valid_link: helpers.is_valid_link,
        validate_links_format: helpers.validate_links_format,
        hasFeaturedImage: helpers.hasFeaturedImage,
    };
}

/**
 * Render hidden requirement nodes (matching the classic meta box markup) for the
 * requirements the React panel does not own, so third-party scripts can find
 * and update them. This container is a plain hidden element, NOT a registered
 * meta box, so it does not disable Real Time Collaboration.
 */
function buildBridgeNodes() {
    if (typeof document === 'undefined') {
        return;
    }

    const source = (typeof ppChecklists !== 'undefined' && ppChecklists.requirements)
        ? ppChecklists.requirements
        : {};

    const bridged = Object.entries(source).filter(([, config]) => !isPanelOwned(config));

    if (!bridged.length) {
        return;
    }

    let container = document.getElementById('pp-checklists-bridge');
    if (!container) {
        container = document.createElement('div');
        container.id = 'pp-checklists-bridge';
        container.style.display = 'none';
        container.setAttribute('aria-hidden', 'true');
    }

    const list = document.createElement('ul');
    list.id = 'pp-checklists-req-box';

    bridged.forEach(([key, config]) => {
        const id = config.id || key;
        const status = config.status ? 'yes' : 'no';

        const li = document.createElement('li');
        li.id = 'pp-checklists-req-' + id;
        li.className = 'pp-checklists-req metabox-req pp-checklists-' + (config.rule || '') + ' status-' + status;
        li.setAttribute('data-id', id);
        li.setAttribute('data-type', config.type || '');
        li.setAttribute('data-source', config.source || '');
        li.setAttribute('data-extra', config.extra || '');

        const label = document.createElement('div');
        label.className = 'status-label';
        label.innerHTML = config.label || '';
        li.appendChild(label);

        const input = document.createElement('input');
        input.type = 'hidden';
        input.className = 'ppch_item_requirement';
        input.id = 'ppch_item_' + id;
        input.name = 'ppch_item_' + id;
        input.value = status;
        li.appendChild(input);

        list.appendChild(li);
    });

    container.appendChild(list);
    document.body.appendChild(container);
}

/**
 * Wire the classic status-update event so third-party triggers update the hidden
 * node and notify the React panel.
 */
function wireStatusRelay() {
    if (typeof window.jQuery === 'undefined') {
        return;
    }

    const $ = window.jQuery;

    $(document).on(EVENTS.EVENT_UPDATE_REQUIREMENT_STATE, '#pp-checklists-bridge li[id^="pp-checklists-req-"]', function (event, isCompleted) {
        const $el = $(this);
        const completed = !!isCompleted;

        $el.toggleClass('status-yes', completed).toggleClass('status-no', !completed);
        $el.find('.ppch_item_requirement').val(completed ? 'yes' : 'no');

        const id = $el.attr('data-id');
        if (id) {
            wp.hooks.doAction(BRIDGE_STATUS_ACTION, id, completed);
        }
    });
}

/**
 * Start the periodic tic event third-party scripts listen to for re-evaluation.
 */
function startTicLoop() {
    if (typeof window.jQuery === 'undefined') {
        return;
    }

    const $ = window.jQuery;
    setInterval(function () {
        $(document).trigger(EVENTS.EVENT_TIC);
    }, TIC_INTERVAL);
}

let initialized = false;

/**
 * Initialize the compatibility bridge. Safe to call more than once.
 */
export function initChecklistBridge() {
    if (initialized) {
        return;
    }
    initialized = true;

    definePublicApi();
    buildBridgeNodes();
    wireStatusRelay();
    startTicLoop();
}

// Initialize as soon as the bundle is evaluated so the hidden nodes and the
// PP_Checklists global exist before the dependent third-party scripts run.
initChecklistBridge();
