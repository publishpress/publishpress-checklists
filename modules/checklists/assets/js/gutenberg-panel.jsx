const { registerPlugin } = wp.plugins;
const { PluginSidebarMoreMenuItem, PluginSidebar } = wp.editPost;
const { Fragment, Component } = wp.element;
const { __ } = wp.i18n;
const { hooks } = wp;

import CheckListIcon from './CheckListIcon.jsx';

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
                if (!wp.data.select('core/edit-post').isPublishSidebarOpened() && wp.data.select('core/editor').getEditedPostAttribute('status') !== 'publish' && wp.data.select('core/editor').getCurrentPost()['status'] !== 'publish') {
                    publishing_post = false;
                } else if (wp.data.select('core/edit-post').isPublishSidebarOpened() && wp.data.select('core/editor').getEditedPostAttribute('status') == 'publish') {
                    publishing_post = true;
                } else if (!wp.data.select('core/edit-post').isPublishSidebarOpened() && wp.data.select('core/editor').getEditedPostAttribute('status') == 'publish') {
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
        this.updateRequirements(this.state.requirements);
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

                return req;
            });

            const sortedRequirements = this.sortRequirements(updatedRequirements);
            const prevHash = this.getRequirementsHash(this.state.requirements);
            const nextHash = this.getRequirementsHash(sortedRequirements);

            if (prevHash !== nextHash || this.state.showRequiredLegend !== showRequiredLegend) {
                this.setState({ showRequiredLegend, requirements: sortedRequirements });
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
                                                            const element = document.querySelector(`#pp-checklists-req-${req.id}` + ' .status-label');
                                                            if (element) {
                                                                element.click();
                                                            }
                                                        }
                                                    }}
                                                >
                                                    {req.is_custom || req.require_button ? (
                                                        <input type="hidden" name={`_PPCH_custom_item[${req.id}]`} value={req.status ? 'yes' : 'no'} />
                                                    ) : null}
                                                    <div className={`status-icon dashicons ${this.getIconClass(req.rule, req.status)}`}></div>
                                                    <div className="status-label">
                                                        <span className="req-label" dangerouslySetInnerHTML={{ __html: req.label }} />
                                                        {req.rule === 'block' ? (
                                                            <span className="required">*</span>
                                                        ) : null}
                                                        {req.require_button ? (
                                                            <div className="requirement-button-task-wrap">
                                                                <button type="button" className="button button-secondary pp-checklists-check-item">
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
