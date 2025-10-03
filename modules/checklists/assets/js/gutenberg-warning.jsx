const {Component} = React;
const {__} = wp.i18n;
const {Fragment} = wp.element;
const {PluginPrePublishPanel} = wp.editPost;
const {registerPlugin} = wp.plugins;
const {hooks} = wp;

String.prototype.stripTags = function () {
    return this.replace(/(<([^>]+)>)/ig, "");
};

class PPChecklistsWarning extends Component {
    isMounted = false;

    componentDidMount() {
        this.isMounted = true;
    }

    componentWillUnmount() {
        this.isMounted = false;
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

        hooks.addAction('pp-checklists.update-failed-requirements', 'publishpress/checklists', this.updateFailedRequirements, 10);
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
                            <span className="dashicons dashicons-no"></span><span>{item.stripTags()}</span></li>
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
                            <span className="dashicons dashicons-no"></span><span>{item.stripTags()}</span>
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
