const { registerPlugin } = wp.plugins;
const { PluginSidebarMoreMenuItem, PluginSidebar } = wp.editPost;
const { Fragment, Component } = wp.element;
const { __ } = wp.i18n;
const { hooks } = wp;

import CheckListIcon from './CheckListIcon.jsx';
import { evaluateRequirement, helpers } from './checklist-evaluators.js';
import { initChecklistBridge, BRIDGE_STATUS_ACTION } from './checklist-bridge.js';

// Ensure the compatibility bridge is set up (window.PP_Checklists, hidden nodes,
// tic loop) before third-party integration scripts run. See #1208.
initChecklistBridge();

/**
 * Small debounce so the wp.data subscribe loop does not recompute on every
 * single store tick. Kept local to avoid a lodash dependency.
 */
function debounce(fn, wait) {
    let timer = null;
    return function () {
        const args = arguments;
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), wait);
    };
}

/**
 * Strip HTML tags and collapse whitespace, returning plain text. Used to build
 * the failed-requirements messages consumed by the pre-publish warning panel.
 */
function toPlainText(html) {
    const container = document.createElement('div');
    container.innerHTML = html || '';
    return (container.textContent || container.innerText || '').trim();
}

class PPChecklistsPanel extends Component {
    isMounted = false;
    oldStatus = '';
    currentStatus = '';

    constructor(props) {
        super(props);
        this.state = {
            isSupportedContext: true,
            showRequiredLegend: false,
            requirements: [],
            failedRequirements: {
                block: [],
                warning: []
            },
        };
    }

    componentDidMount() {

        this.isMounted = true;
        const isSupportedContext = this.updateEditorContext();

        if (isSupportedContext) {
            this.setState({ requirements: this.buildInitialRequirements() }, () => this.recompute());
        }

        /**
         * Our less problematic solution till gutenberg Add a way
         * for third parties to perform additional save validation
         * in this issue https://github.com/WordPress/gutenberg/issues/13413
         * is this solution as it also solves third party conflict with
         * locking post (Rankmath, Yoast SEO etc)
         */
        let coreEditor   = wp.data.dispatch('core/editor');
        let notices  = wp.data.dispatch('core/notices');
        let coreSavePost = coreEditor.savePost;
        let coreEdiPost  = coreEditor.editPost;

        this.onEditorChange = debounce(() => {
            if (!this.isMounted) {
                return;
            }
            const currentContextSupported = this.updateEditorContext();
            if (currentContextSupported) {
                this.recompute();
            }
        }, 300);

        // Recompute requirement statuses whenever the editor state changes.
        this.contextSubscription = wp.data.subscribe(this.onEditorChange);

        // Reflect status updates coming from third-party integration scripts
        // (Yoast SEO, Permalinks, Pro) through the compatibility bridge.
        hooks.addAction(BRIDGE_STATUS_ACTION, 'publishpress/checklists', this.applyBridgeStatus, 10);

        if (!this.oldStatus || this.oldStatus == '') {
            const currentPost = wp.data.select('core/editor').getCurrentPost();
            this.oldStatus = currentPost && currentPost.status ? currentPost.status : '';
        }

        /**
        *  This is the best way to get edited post status.
        * For now, both getEditedPostAttribute('status') and
        * getCurrentPost()['status'] are not helpful because they don't usually return same
        * status or valid status between when a post Publish button is used / Save draft is clicked
        * for new and already published post.
       */

        wp.data.dispatch('core/editor').editPost = async (edits, options) => {
            options = options || {};
            if (options.pp_checklists_edit_filtered === 1 || options.pp_checklists_post_status_edit === 1) {
                return coreEdiPost(edits, options);
            }

            if (typeof edits === 'object' && edits.status) {
                // set status to be used later when preventing publish for posts that doesn't meet requirement.
                this.currentStatus = edits.status;
            }
            options.pp_checklists_edit_filtered = 1;
            return coreEdiPost(edits, options);
        };

        wp.data.dispatch('core/editor').savePost = async (options) => {
            options = options || {};

            if (!this.isSupportedContext()) {
                return coreSavePost(options);
            }

            let publishing_post = false;
            const mapStatusPublishAllowed = {
                publish: true, // already published post
                future: true, // scheduled post
            }
            if (options.isAutosave || options.isPreview) {
                publishing_post = false
            } else if (this.currentStatus !== '') {
                publishing_post = mapStatusPublishAllowed[this.currentStatus] ?? false;
            } else {
                if (!wp.data.select('core/edit-post').isPublishSidebarOpened() && wp.data.select('core/editor').getEditedPostAttribute('status') !== 'publish' && wp.data.select('core/editor').getCurrentPost()['status'] !== 'publish') {
                    publishing_post = false;
                } else if (wp.data.select('core/edit-post').isPublishSidebarOpened() && wp.data.select('core/editor').getEditedPostAttribute('status') == 'publish') {
                    publishing_post = true;
                } else if (!wp.data.select('core/edit-post').isPublishSidebarOpened() && wp.data.select('core/editor').getEditedPostAttribute('status') == 'publish') {
                    publishing_post = true;
                }
            }

            const hasBlockRequirements = this.state.failedRequirements.block && this.state.failedRequirements.block.length > 0;

            if (!publishing_post || !hasBlockRequirements) {
                return coreSavePost(options);
            } else {
                notices.createErrorNotice(i18n.completeRequirementMessage, {
                    id: 'publishpress-checklists-validation',
                    isDismissible: true
                });
                wp.data.dispatch('core/edit-post').openGeneralSidebar('publishpress-checklists-panel/checklists-sidebar');

                /**
                 * change status to draft or old status if failed to
                 * solve further save draft button not working. This is
                 * because at this state, the status has been updated to publish
                 * and further click on "Save draft" from editor UI won't work
                 * as that doesn't update the status to publish
                 */
                if (this.oldStatus !== '') {
                    wp.data.dispatch('core/editor').editPost({status: this.oldStatus, pp_checklists_post_status_edit: true});
                }
                return;
            }
        };
    }

    componentWillUnmount() {
        if (typeof this.contextSubscription === 'function') {
            this.contextSubscription();
        }

        hooks.removeAction(BRIDGE_STATUS_ACTION, 'publishpress/checklists');

        this.isMounted = false;
    }

    /**
     * Apply a status update relayed from a third-party integration script via
     * the compatibility bridge, keeping the panel in sync for requirements the
     * React engine does not evaluate itself.
     */
    applyBridgeStatus = (id, status) => {
        if (!this.isMounted) {
            return;
        }

        let changed = false;
        const updated = this.state.requirements.map((req) => {
            if (req.id === id && !!req.status !== !!status) {
                changed = true;
                return { ...req, status: !!status };
            }
            return req;
        });

        if (changed) {
            this.setState({ requirements: updated }, () => this.computeFailedRequirements());
        }
    };

    getCurrentPostType = () => {
        const selectedPostType = wp.data.select('core/editor').getCurrentPostType();
        if (selectedPostType) {
            return selectedPostType;
        }

        const currentPost = wp.data.select('core/editor').getCurrentPost();
        return currentPost && currentPost.type ? currentPost.type : '';
    };

    getEditorRenderingMode = () => {
        const editorStore = wp.data.select('core/editor');
        if (editorStore && typeof editorStore.getRenderingMode === 'function') {
            return editorStore.getRenderingMode();
        }

        return 'post-only';
    };

    isSupportedContext = () => {
        const renderingMode = this.getEditorRenderingMode();
        if (renderingMode && renderingMode !== 'post-only') {
            return false;
        }

        const supportedPostTypes = Array.isArray(i18n.supportedPostTypes) ? i18n.supportedPostTypes : [];
        const currentPostType = this.getCurrentPostType();

        return supportedPostTypes.includes(currentPostType);
    };

    updateEditorContext = () => {
        const contextSupported = this.isSupportedContext();

        if (!this.isMounted) {
            return contextSupported;
        }

        this.setState((prevState) => {
            if (contextSupported) {
                return prevState.isSupportedContext ? null : { isSupportedContext: true };
            }

            const failedRequirementsAlreadyReset =
                prevState.failedRequirements.block.length === 0 &&
                prevState.failedRequirements.warning.length === 0;

            if (!prevState.isSupportedContext && prevState.requirements.length === 0 && !prevState.showRequiredLegend && failedRequirementsAlreadyReset) {
                return null;
            }

            return {
                isSupportedContext: false,
                showRequiredLegend: false,
                requirements: [],
                failedRequirements: {
                    block: [],
                    warning: [],
                },
            };
        });

        return contextSupported;
    };

    /**
     * Build the initial requirement list from the data localized by PHP.
     * The server provides the initial (last saved) status for each requirement.
     */
    buildInitialRequirements = () => {
        const source = (typeof ppChecklists !== 'undefined' && ppChecklists.requirements)
            ? ppChecklists.requirements
            : {};

        return Object.entries(source).map(([key, req]) => {
            const id = req.id || key;
            return { ...req, id, status: !!req.status };
        });
    };

    /**
     * Recompute the status of every requirement against the current editor
     * state, then refresh the panel and the failed-requirements consumers.
     */
    recompute = () => {
        if (!this.isMounted || !this.state.isSupportedContext) {
            return;
        }

        const current = this.state.requirements.length
            ? this.state.requirements
            : this.buildInitialRequirements();

        const updated = current.map((req) => {
            const result = evaluateRequirement(req);
            return {
                ...req,
                status: !!result.status,
                label: result.label !== undefined ? result.label : req.label,
            };
        });

        const sorted = this.sortRequirements(updated);
        const showRequiredLegend = sorted.some((req) => req.rule === 'block');

        const prevHash = this.getRequirementsHash(this.state.requirements);
        const nextHash = this.getRequirementsHash(sorted);

        if (prevHash !== nextHash || this.state.showRequiredLegend !== showRequiredLegend) {
            this.setState({ requirements: sorted, showRequiredLegend }, () => this.computeFailedRequirements());
        } else {
            this.computeFailedRequirements();
        }
    };

    /**
     * Build the { block, warning } list of failed requirements from the current
     * state and broadcast it. The pre-publish warning panel and the publish
     * lock in savePost both rely on this data.
     */
    computeFailedRequirements = () => {
        const failed = { block: [], warning: [] };

        this.state.requirements.forEach((req) => {
            if (!req.status) {
                const label = toPlainText(req.label);
                if (req.rule === 'block') {
                    failed.block.push(label);
                } else if (req.rule === 'warning') {
                    failed.warning.push(label);
                }
            }
        });

        const prev = this.state.failedRequirements;
        const changed = prev.block.join('|') !== failed.block.join('|')
            || prev.warning.join('|') !== failed.warning.join('|');

        if (changed && this.isMounted) {
            this.setState({ failedRequirements: failed });

            // Notify external consumers (pre-publish warning panel, third parties).
            hooks.doAction('pp-checklists.update-failed-requirements', failed);
        }

        // Run these every recompute (not only on change): post status and
        // requirement completeness can both move the lock / icon state.
        this.updatePublishButtonLock(failed);
        this.updateWarningIcon();
    };

    /**
     * When the "Disable publish button" setting is on, proactively lock saving
     * while required (block) tasks are incomplete - mirroring the classic
     * meta-box.js behaviour. Publishing is already blocked by the savePost
     * override; this also disables the button up-front. See #1212.
     */
    updatePublishButtonLock = (failed) => {
        if (typeof ppChecklists === 'undefined' || !ppChecklists.disable_publish_button) {
            return;
        }

        const dispatch = wp.data.dispatch('core/editor');
        if (!dispatch || typeof dispatch.lockPostSaving !== 'function') {
            return;
        }

        const currentPost = wp.data.select('core/editor').getCurrentPost();
        const status = currentPost && currentPost.status ? currentPost.status : '';
        const isPublished = status === 'publish';
        const isPending = status === 'pending';

        // Pro can opt out of locking already-published posts via this flag.
        // When it is not set (default / free) the lock applies regardless.
        const applyToPublished = !ppChecklists.disable_published_block_feature;
        const shouldConsider = (!isPublished && !isPending) || applyToPublished;

        if (shouldConsider && failed.block.length > 0) {
            dispatch.lockPostSaving('ppcPublishButton');
        } else {
            dispatch.unlockPostSaving('ppcPublishButton');
        }
    };

    /**
     * Toggle the body class used by the Pro status-filter warning icon when any
     * requirement is incomplete. Mirrors the classic meta-box.js behaviour.
     * See #1212.
     */
    updateWarningIcon = () => {
        if (typeof ppChecklists === 'undefined'
            || !ppChecklists.show_warning_icon_submit
            || !ppChecklists.status_filter_enabled
            || ppChecklists.status_filter_enabled === 'off'
        ) {
            return;
        }

        if (typeof document === 'undefined' || !document.body) {
            return;
        }

        const anyIncomplete = this.state.requirements.some((req) => !req.status);
        document.body.classList.toggle('ppch-show-publishing-warning-icon', anyIncomplete);
    };

    /**
     * Toggle a custom (manually checked) requirement and persist it as post
     * meta so it is saved through the REST API - no classic meta box needed.
     */
    toggleCustomItem = (req) => {
        if (!req.is_custom) {
            return;
        }

        const newStatus = !req.status;
        const updated = this.state.requirements.map((item) =>
            item.id === req.id ? { ...item, status: newStatus } : item
        );

        this.setState({ requirements: updated }, () => this.computeFailedRequirements());
        this.persistCustomItem(req.id, newStatus);
    };

    persistCustomItem = (id, status) => {
        const metaKey = 'pp_checklist_custom_item_' + id;
        wp.data.dispatch('core/editor').editPost({ meta: { [metaKey]: status ? 'yes' : 'no' } });
    };

    /**
     * Handle the "Check Now" button for button-based requirements (e.g. OpenAI).
     * Mirrors the AJAX call the classic engine used to make.
     */
    checkRequirementButton = (req, event) => {
        event.preventDefault();
        event.stopPropagation();

        if (typeof window.jQuery === 'undefined' || typeof ppChecklists === 'undefined') {
            return;
        }

        const $ = window.jQuery;
        const button = $(event.currentTarget);
        const wrap = button.closest('.requirement-button-task-wrap');
        wrap.find('.request-response').html('');
        button.prop('disabled', true);
        wrap.find('.spinner').addClass('is-active');

        const data = {
            action: 'pp_checklists_' + req.source + '_requirement',
            requirement: ppChecklists.requirements[req.id],
            content: helpers.getContent(),
            nonce: ppChecklists.nonce,
        };

        $.post(ajaxurl, data, (response) => {
            const status = response.yes_no === 'yes';
            const updated = this.state.requirements.map((item) =>
                item.id === req.id ? { ...item, status } : item
            );
            this.setState({ requirements: updated }, () => this.computeFailedRequirements());
            this.persistCustomItem(req.id, status);

            const content = (response.content || '').replace(/\n/g, '<br>');
            wrap.find('.request-response').html(
                '<div class="ppch-message notice is-dismissible updated published"><p>' + content + '</p></div>'
            );
        }).fail((jqXHR, textStatus, errorThrown) => {
            wrap.find('.request-response').html(
                '<div class="ppch-message notice is-dismissible error"><p>' + errorThrown + ' ' + textStatus + '</p></div>'
            );
        }).always(() => {
            button.prop('disabled', false);
            wrap.find('.spinner').removeClass('is-active');
        });
    };

    getRequirementsHash = (requirements) => {
        return JSON.stringify(
            (requirements || []).map((req) => ({
                id: req.id || '',
                label: req.label || '',
                rule: req.rule || '',
                status: !!req.status,
                type: req.type || '',
                source: req.source || '',
                extra: req.extra || '',
                is_custom: !!req.is_custom,
                require_button: !!req.require_button,
            }))
        );
    };

    normalizeLabelForSort = (label) => {
        return toPlainText(label).toLowerCase();
    };

    isRequirementCompliant = (req) => {
        const status = req.status;
        if (typeof status === 'boolean') return status;
        if (typeof status === 'number') return status === 1;
        if (typeof status === 'string') {
            const normalizedStatus = status.trim().toLowerCase();
            return normalizedStatus === 'yes' || normalizedStatus === 'true' || normalizedStatus === '1';
        }
        return false;
    };

    getRequirementGroup = (req) => {
        const sortMode = i18n.checklistItemsSortOrder || 'default';
        const rule = req.rule || '';
        const compliant = this.isRequirementCompliant(req);

        if (sortMode === 'required_recommended') {
            if (rule === 'block') return compliant ? 1 : 0;
            if (rule === 'warning') return compliant ? 3 : 2;
            return compliant ? 5 : 4;
        }

        if (!compliant) {
            if (rule === 'block') return 0;
            if (rule === 'warning') return 1;
            return 2;
        }

        if (rule === 'block') return 3;
        if (rule === 'warning') return 4;
        return 5;
    };

    sortRequirements = (requirements) => {
        const sortMode = i18n.checklistItemsSortOrder || 'default';
        if (sortMode === 'default') return requirements;

        const decorated = requirements.map((req, index) => ({
            req,
            index,
            label: this.normalizeLabelForSort(req.label),
            group: this.getRequirementGroup(req),
        }));

        decorated.sort((a, b) => {
            if (sortMode === 'required_recommended' && a.group !== b.group) {
                return a.group - b.group;
            }

            const labelCompare = a.label.localeCompare(b.label, undefined, { numeric: true });
            if (labelCompare !== 0) return labelCompare;

            return a.index - b.index;
        });

        return decorated.map((item) => item.req);
    };

    /**
     * Get the icon class based on status
     *
     * @param {string} rule - 'block' (Required) or 'warning' (Recommended) - not used anymore
     * @param {boolean} status - true (complete) or false (incomplete)
     * @returns {string} - Dashicon class name
     */
    getIconClass = (rule, status) => {
        const customIcons = i18n.customIcons || {};

        // Use the same icons for both Required and Recommended
        return status ? (customIcons.complete || 'dashicons-yes') : (customIcons.incomplete || 'dashicons-no');
    };

    render() {
        const { isSupportedContext, showRequiredLegend, requirements } = this.state;
        const showRuleHeadings = i18n.showRuleHeadings === '1';
        let lastHeadingRule = '';

        if (!isSupportedContext) {
            return null;
        }

        return requirements.length > 0 ? (
            <Fragment>
                <PluginSidebarMoreMenuItem
                    target="checklists-sidebar"
                    icon={<CheckListIcon />}
                >
                    {i18n.checklistLabel}
                </PluginSidebarMoreMenuItem>
                <PluginSidebar
                    name="checklists-sidebar"
                    title={__("Checklists", "publishpress-checklists")}
                >
                    <div id="pp-checklists-sidebar-content" className="components-panel__body is-opened">
                        {i18n.isElementorEnabled == "1" ? (
                            <p><em>{i18n.elementorNotice}</em></p>
                        ) : (
                            <Fragment>
                                {requirements.length === 0 ? (
                                    <p>
                                        <em>
                                            {i18n.noTaskLabel}
                                        </em>
                                    </p>
                                ) : (
                                    <ul id="pp-checklists-sidebar-req-box">
                                        {requirements.flatMap((req, key) => {
                                            const nodes = [];
                                            const shouldPrintHeading = showRuleHeadings
                                                && (req.rule === 'block' || req.rule === 'warning')
                                                && lastHeadingRule !== req.rule;

                                            if (shouldPrintHeading) {
                                                nodes.push(
                                                    <li key={`pp-checklists-heading-${req.rule}-${key}`} className="pp-checklists-group-heading">
                                                        {req.rule === 'block'
                                                            ? (i18n.requiredHeading || __("Required", "publishpress-checklists"))
                                                            : (i18n.recommendedHeading || __("Recommended", "publishpress-checklists"))}
                                                    </li>
                                                );
                                                lastHeadingRule = req.rule;
                                            }

                                            nodes.push(
                                                <li
                                                    key={`pp-checklists-req-panel-${key}`}
                                                    className={`pp-checklists-req panel-req pp-checklists-${req.rule} status-${req.status ? 'yes' : 'no'} ${req.is_custom ? 'pp-checklists-custom-item' : ''
                                                        }`}
                                                    data-id={req.id}
                                                    data-type={req.type}
                                                    data-extra={req.extra || ''}
                                                    data-source={req.source || ''}
                                                    onClick={() => {
                                                        if (req.is_custom) {
                                                            this.toggleCustomItem(req);
                                                        }
                                                    }}
                                                >
                                                    <div className={`status-icon dashicons ${this.getIconClass(req.rule, req.status)}`}></div>
                                                    <div className="status-label">
                                                        <span className="req-label" dangerouslySetInnerHTML={{ __html: req.label }} />
                                                        {req.rule === 'block' ? (
                                                            <span className="required">*</span>
                                                        ) : null}
                                                        {req.require_button ? (
                                                            <div className="requirement-button-task-wrap">
                                                                <button
                                                                    type="button"
                                                                    className="button button-secondary pp-checklists-check-item"
                                                                    onClick={(event) => this.checkRequirementButton(req, event)}
                                                                >
                                                                    {__("Check Now", "publishpress-checklists")}
                                                                    <span className="spinner"></span>
                                                                </button>
                                                                <div className="request-response"></div>
                                                            </div>
                                                        ) : null}
                                                    </div>
                                                </li>
                                            );

                                            return nodes;
                                        })}
                                    </ul>
                                )}
                            </Fragment>
                        )}
                        {showRequiredLegend ? (
                            <em>
                                (*) {i18n.required}
                            </em>
                        ) : null}
                    </div>
                </PluginSidebar>
            </Fragment>
        ) : null;
    }
}

const ChecklistsTitle = () => (
    <div className="pp-checklists-toolbar-icon" aria-hidden="true">
        <span className="dashicons dashicons-yes"></span>
    </div>
);

registerPlugin("publishpress-checklists-panel", {
    render: PPChecklistsPanel,
    icon: <ChecklistsTitle />,
});
