const {Component} = React;
const {__} = wp.i18n;
const {Fragment} = wp.element;
const editorComponents = wp.editor || {};
const editPostComponents = wp.editPost || {};
const PluginPrePublishPanel = editorComponents.PluginPrePublishPanel || editPostComponents.PluginPrePublishPanel;
const {registerPlugin} = wp.plugins;
const {hooks} = wp;

String.prototype.stripTags = function () {
    return this.replace(/(<([^>]+)>)/ig, "");
};

class PPChecklistsWarning extends Component {
    isMounted = false;

    componentDidMount() {
        this.isMounted = true;

        // Use our own namespace: the sidebar panel removes the
        // 'publishpress/checklists' handlers of this hook when it unmounts,
        // which also dropped this one, so the pre-publish panel never showed.
        hooks.addAction('pp-checklists.update-failed-requirements', 'publishpress/checklists-warning', this.updateFailedRequirements, 10);
    }

    componentWillUnmount() {
        this.isMounted = false;
        hooks.removeAction('pp-checklists.update-failed-requirements', 'publishpress/checklists-warning');
    }

    constructor() {
        super();

        this.state = {
            requirements: {
                block: [],
                warning: []
            }
        };

        this.updateFailedRequirements = this.updateFailedRequirements.bind(this);
    };

    updateFailedRequirements(failedRequirements) {
        if (this.isMounted) {
            this.setState({requirements: failedRequirements});
        }
    };

    render() {
        if (typeof this.state.requirements.block === "undefined" ||
            (this.state.requirements.block.length === 0 && this.state.requirements.warning.length === 0)) {
            return (null);
        }

        let messageBlock = (null);
        if (this.state.requirements.block.length > 0) {
            messageBlock = (<div>
                <p>{ppChecklists.msg_missed_required_publishing}</p>
                <ul>
                    {this.state.requirements.block.map(
                        (item, i) => <li key={i}>
                            <span className="dashicons dashicons-no" aria-hidden="true"></span><span>{item.stripTags()}</span></li>
                    )}
                </ul>
            </div>);
        }

        let messageWarning = (null);
        if (this.state.requirements.warning.length > 0) {
            let message = this.state.requirements.block.length > 0 ?
                ppChecklists.msg_missed_important_publishing : ppChecklists.msg_missed_optional_publishing;

            messageWarning = (<div>
                <p>{message}</p>
                <ul>
                    {this.state.requirements.warning.map(
                        (item, i) => <li key={i}>
                            <span className="dashicons dashicons-no" aria-hidden="true"></span><span>{item.stripTags()}</span>
                        </li>
                    )}
                </ul>
            </div>);
        }

        return (<Fragment>
            <PluginPrePublishPanel
                name="publishpress-checklists-pre-publishing-panel"
                title={ppChecklists.label_checklist}
                initialOpen="true"
            >
                <div className="pp-checklists-failed-requirements-warning">
                    {messageBlock}
                    {messageWarning}
                </div>
            </PluginPrePublishPanel>
        </Fragment>);
    }
}

registerPlugin('pp-checklists-warning', {
    icon: 'warning',
    render: () => (<PPChecklistsWarning/>)
});
