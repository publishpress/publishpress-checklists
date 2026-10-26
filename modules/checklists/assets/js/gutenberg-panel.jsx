const { registerPlugin } = wp.plugins;
const editorComponents = wp.editor || {};
const editPostComponents = wp.editPost || {};
const PluginSidebarMoreMenuItem = editorComponents.PluginSidebarMoreMenuItem || editPostComponents.PluginSidebarMoreMenuItem;
const PluginSidebar = editorComponents.PluginSidebar || editPostComponents.PluginSidebar;
const PluginDocumentSettingPanel = editorComponents.PluginDocumentSettingPanel || editPostComponents.PluginDocumentSettingPanel;
const { Fragment, Component } = wp.element;
const { __ } = wp.i18n;
const { hooks } = wp;

import CheckListIcon from './CheckListIcon.jsx';

const SUPPORTED_RENDERING_MODES = ['post-only', 'template-locked'];

const isSupportedEditorContext = ({ renderingMode, currentPostType, supportedPostTypes }) => {
    if (renderingMode && !SUPPORTED_RENDERING_MODES.includes(renderingMode)) {
        return false;
    }

    return Array.isArray(supportedPostTypes) && supportedPostTypes.includes(currentPostType);
};

const getIsPublishSidebarOpened = () => {
    const editorStore = wp.data.select('core/editor');
    if (editorStore && typeof editorStore.isPublishSidebarOpened === 'function') {
        return editorStore.isPublishSidebarOpened();
    }

    // The selector lived in core/edit-post before WordPress 6.6.
    const editPostStore = wp.data.select('core/edit-post');
    return editPostStore && typeof editPostStore.isPublishSidebarOpened === 'function'
        ? editPostStore.isPublishSidebarOpened()
        : false;
};

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

        if (isSupportedContext && typeof ppChecklists !== "undefined") {
            this.updateRequirements(ppChecklists.requirements);
        }

        hooks.addAction('pp-checklists.update-failed-requirements', 'publishpress/checklists', this.updateFailedRequirements.bind(this), 10);
        hooks.addAction('pp-checklists.requirements-updated', 'publishpress/checklists', this.handleRequirementStatusChange.bind(this), 10);

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

        // Add Gutenberg validation that triggers failed requirements
        let validateRequirements = () => {
            
            let uncheckedItems = {
                block: [],
                warning: []
            };

            // Check each requirement from the requirements array
            this.state.requirements.forEach(req => {
                if (!req.status) {
                    // This requirement is not met
                    if (req.rule === 'block') {
                        uncheckedItems.block.push(req.label);
                    } else if (req.rule === 'warning') {
                        uncheckedItems.warning.push(req.label);
                    }
                }
            });

            this.updateFailedRequirements(uncheckedItems);
        };

        // Also run by updateRequirements(), so savePost() doesn't check stale results.
        this.validateRequirements = validateRequirements;

        // Subscribe to changes to trigger validation
        this.contextSubscription = wp.data.subscribe(() => {
            const currentContextSupported = this.updateEditorContext();
            if (this.isMounted && currentContextSupported && this.state.requirements.length > 0) {
                validateRequirements();
            }
        });

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
                const editorStore = wp.data.select('core/editor');
                const isPublishSidebarOpened = getIsPublishSidebarOpened();
                const editedPostStatus = editorStore.getEditedPostAttribute('status');
                const currentPost = editorStore.getCurrentPost() || {};

                if (!isPublishSidebarOpened && editedPostStatus !== 'publish' && currentPost.status !== 'publish') {
                    publishing_post = false;
                } else if (isPublishSidebarOpened && editedPostStatus === 'publish') {
                    publishing_post = true;
                } else if (!isPublishSidebarOpened && editedPostStatus === 'publish') {
                    publishing_post = true;
                }
            }
            
            const hasBlockRequirements = this.state.failedRequirements.block && this.state.failedRequirements.block.length > 0;
            const hasWarningRequirements = this.state.failedRequirements.warning && this.state.failedRequirements.warning.length > 0;
            
            if (!publishing_post || !hasBlockRequirements) {
                return coreSavePost(options);
            } else {
                notices.createErrorNotice(i18n.completeRequirementMessage, {
                    id: 'publishpress-checklists-validation',
                    isDismissible: true
                });
                wp.data.dispatch('core/edit-post').openGeneralSidebar('publishpress-checklists-panel/checklists-sidebar');

                // Move focus to the checklist so keyboard users land on the tasks that block publishing
                setTimeout(() => {
                    const sidebarContent = document.getElementById('pp-checklists-sidebar-content');
                    if (sidebarContent) {
                        sidebarContent.focus();
                    }
                }, 0);
                
                /**
                 * change status to draft or old status if failed to 
                 * solve further save draft button not working. This is
                 * because at this state, the status has been updated to publish 
                 * and further click on "Save draft" from editor UI won't work
                 * as that doesn't update the status to publish
                 */
                // Use the saved status: the one read when the editor opened is
                // out of date once the post is saved (a post published since
                // would get 'auto-draft' back, and the REST API rejects it).
                const savedPost = wp.data.select('core/editor').getCurrentPost() || {};
                const restoreStatus = savedPost.status || this.oldStatus;

                if (restoreStatus) {
                    wp.data.dispatch('core/editor').editPost({status: restoreStatus, pp_checklists_post_status_edit: true});
                }
                return;
            }
        };
    }

    componentDidUpdate(_, prevState) {
        if (!this.state.isSupportedContext) {
            return;
        }

        if (typeof ppChecklists !== "undefined") {
            this.updateRequirements(ppChecklists.requirements);
        }
    }

    componentWillUnmount() {

        hooks.removeAction('pp-checklists.update-failed-requirements', 'publishpress/checklists');
        hooks.removeAction('pp-checklists.requirements-updated', 'publishpress/checklists');
        if (typeof this.contextSubscription === 'function') {
            this.contextSubscription();
        }

        this.isMounted = false;
    }

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
        return isSupportedEditorContext({
            renderingMode: this.getEditorRenderingMode(),
            currentPostType: this.getCurrentPostType(),
            supportedPostTypes: i18n.supportedPostTypes,
        });
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
     * Hook to failed requirement to update block requirements.
     * 
     * @param {Array} failedRequirements 
     */
    updateFailedRequirements(failedRequirements) {
        if (this.isMounted) {
            this.setState({ failedRequirements: failedRequirements });
        }
    };

    /**
     * Handle requirement status change
     */
    handleRequirementStatusChange = () => {
        // Read from ppChecklists.requirements, not this.state: the state holds
        // copies, while meta-box.js updates the source objects (status, live
        // count label).
        this.updateRequirements(typeof ppChecklists !== "undefined" ? ppChecklists.requirements : this.state.requirements);
    };

    /**
     * Update sidebar requirements
     * 
     * @param {Array} Requirements 
     */
    updateRequirements = (Requirements) => {
        if (this.isMounted && this.state.isSupportedContext) {
            const sourceRequirements = Requirements || {};
            const showRequiredLegend = Object.values(sourceRequirements).some((req) => req.rule === 'block');

            const updatedRequirements = Object.entries(sourceRequirements).map(([key, req]) => {
                const id = req.id || key;
                const element = document.querySelector(`#ppch_item_${id}`);

                if (element) {
                    req.status = element.value == 'yes' ? true : false;
                }
                req.id = id;

                // Store a copy so that later in-place changes to the source
                // object (e.g. the live count in req.label) show up as a hash
                // difference and re-render the sidebar.
                return { ...req };
            });

            const sortedRequirements = this.sortRequirements(updatedRequirements);
            const prevHash = this.getRequirementsHash(this.state.requirements);
            const nextHash = this.getRequirementsHash(sortedRequirements);

            if (prevHash !== nextHash || this.state.showRequiredLegend !== showRequiredLegend) {
                // Validate once the new list is in the state. The store
                // subscription can run before it (a ticked task edits the post
                // meta first), and some changes don't touch the store at all.
                this.setState({ showRequiredLegend, requirements: sortedRequirements }, () => {
                    if (typeof this.validateRequirements === 'function') {
                        this.validateRequirements();
                    }
                });
            }
        }
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
        const container = document.createElement('div');
        container.innerHTML = label || '';

        // Live counter badges change as the user types (see PP_Checklists.update_live_count
        // in meta-box.js) and must not affect alphabetical sort order.
        const liveCountBadge = container.querySelector('.pp-checklists-live-count');
        if (liveCountBadge) {
            liveCountBadge.remove();
        }

        return (container.textContent || container.innerText || '').trim().toLowerCase();
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

    /**
     * The checklist, shown in the Checklists sidebar and in the Post sidebar.
     * Only one of them is open at a time, so they share the element ids that
     * add-ons look for.
     */
    renderChecklist(inDocumentPanel = false) {
        const { showRequiredLegend, requirements } = this.state;
        const showRuleHeadings = i18n.showRuleHeadings === '1';
        let lastHeadingRule = '';

        return (
            <div id="pp-checklists-sidebar-content" className={inDocumentPanel ? 'pp-checklists-document-panel-content' : 'components-panel__body is-opened'} tabIndex={-1}>
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
                                                    toggleCustomItem(req.id);
                                                }
                                            }}
                                        >
                                            {req.is_custom || req.require_button ? (
                                                <input type="hidden" name={`_PPCH_custom_item[${req.id}]`} value={req.status ? 'yes' : 'no'} />
                                            ) : null}
                                            <div className={`status-icon dashicons ${this.getIconClass(req.rule, req.status)}`} aria-hidden="true"></div>
                                            <div
                                                className="status-label"
                                                {...(req.is_custom ? {
                                                    role: 'checkbox',
                                                    tabIndex: 0,
                                                    'aria-checked': req.status ? 'true' : 'false',
                                                    onKeyDown: (event) => {
                                                        if (event.key === ' ' || event.key === 'Enter') {
                                                            event.preventDefault();
                                                            toggleCustomItem(req.id);
                                                        }
                                                    },
                                                } : {})}
                                            >
                                                <span className="screen-reader-text">
                                                    {(req.status ? i18n.statusCompleted : i18n.statusNotCompleted) + ':'}
                                                </span>
                                                <span className="req-label" dangerouslySetInnerHTML={{ __html: req.label }} />
                                                {req.rule === 'block' ? (
                                                    <Fragment>
                                                        <span className="required" aria-hidden="true">*</span>
                                                        <span className="screen-reader-text">({i18n.requiredHeading})</span>
                                                    </Fragment>
                                                ) : null}
                                                {req.require_button ? (
                                                    <div className="requirement-button-task-wrap">
                                                        <button type="button" className="button button-secondary pp-checklists-check-item">
                                                            {__("Check Now", "publishpress-checklists")}
                                                            <span className="spinner"></span>
                                                        </button>
                                                        <div className="request-response" role="status"></div>
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
                    <em aria-hidden="true">
                        (*) {i18n.required}
                    </em>
                ) : null}
            </div>
        );
    }

    render() {
        const { isSupportedContext, requirements } = this.state;

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
                    {this.renderChecklist()}
                </PluginSidebar>
                {PluginDocumentSettingPanel ? (
                    // Where the Checklist meta box used to be. The block editor has
                    // no meta box: it turns off visual revisions and real-time
                    // collaboration (#1208).
                    <PluginDocumentSettingPanel
                        name="checklists-document-panel"
                        title={i18n.documentPanelLabel || __("Checklist", "publishpress-checklists")}
                        className="pp-checklists-document-panel"
                    >
                        {this.renderChecklist(true)}
                    </PluginDocumentSettingPanel>
                ) : null}
            </Fragment>
        ) : null;
    }
}

/**
 * Custom items are toggled through the meta box, which owns their state.
 *
 * @param {string} id Requirement id
 */
const toggleCustomItem = (id) => {
    const element = document.querySelector(`#pp-checklists-req-${id}` + ' .status-label');
    if (element) {
        element.click();
    }
};

const ChecklistsTitle = () => (
    <div className="pp-checklists-toolbar-icon" aria-hidden="true">
        <span className="dashicons dashicons-yes"></span>
    </div>
);

registerPlugin("publishpress-checklists-panel", {
    render: PPChecklistsPanel,
    icon: <ChecklistsTitle />,
});
