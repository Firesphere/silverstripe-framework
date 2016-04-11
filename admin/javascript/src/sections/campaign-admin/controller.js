import React from 'react';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import * as actions from 'state/campaignAdmin/actions';
import SilverStripeComponent from 'silverstripe-component';
import FormAction from 'components/form-action/index';
import i18n from 'i18n';
import NorthHeader from 'components/north-header/index';
import FormBuilder from 'components/form-builder/index';
import ChangeSetContainer from './list';

class CampaignAdminContainer extends SilverStripeComponent {

  constructor(props) {
    super(props);

    this.addCampaign = this.addCampaign.bind(this);
    this.createFn = this.createFn.bind(this);
  }

  componentDidMount() {
    // Set selected set
    window.ss.router(`/${this.props.config.itemListViewLink}`, (ctx) => {
      this.props.actions.showSetItems(ctx.params.id);
    });

    // Go back to main list
    window.ss.router(`/${this.props.config.setListViewLink}`, () => {
      this.props.actions.showSetList();
    });
  }

  render() {
    if (this.props.setid) {
      return this.renderItemListView();
    }

    return this.renderIndexView();
  }

  /**
   * Renders the default view which displays a list of Campaigns.
   *
   * @return object
   */
  renderIndexView() {
    const schemaUrl = this.props.config.forms.editForm.schemaUrl;

    return (
      <div className="cms-middle no-preview">
        <div className="cms-campaigns collapse in" aria-expanded="true">
          <NorthHeader />
          <FormAction
            label={i18n._t('Campaigns.ADDCAMPAIGN')}
            icon={'plus-circled'}
            handleClick={this.addCampaign}
          />
          <FormBuilder schemaUrl={schemaUrl} createFn={this.createFn} />
        </div>
      </div>
    );
  }

  /**
   * Renders a list of items in a Campaign.
   *
   * @return object
   */
  renderItemListView() {
    const props = {
      setid: this.props.setid,
      itemListViewEndpoint: this.props.config.itemListViewEndpoint,
    };

    return (
      <ChangeSetContainer {...props} />
    );
  }

  /**
   * Hook to allow customisation of components being constructed by FormBuilder.
   *
   * @param object Component - Component constructor.
   * @param object props - Props passed from FormBuilder.
   *
   * @return object - Instanciated React component
   */
  createFn(Component, props) {
    const showSetItems = this.props.actions.showSetItems;
    const itemListViewLink = this.props.config.itemListViewLink;
    if (props.component === 'GridField') {
      const extendedProps = Object.assign({}, props, {
        data: Object.assign({}, props.data, {
          handleDrillDown: (event, record) => {
            // Set url and set list
            window.ss.router.show(itemListViewLink.replace(/:id/, record.ID));
            showSetItems(record.ID);
          },
        }),
      });

      return <Component key={extendedProps.name} {...extendedProps} />;
    }

    return <Component key={props.name} {...props} />;
  }

  /**
   * Gets preview URL for itemid
   * @param int id
   * @returns string
   */
  previewURLForItem(id) {
    if (!id) {
      return '';
    }

    // hard code in baseurl for any itemid preview url
    return document.getElementsByTagName('base')[0].href;
  }

  addCampaign() {
    // Add campaign
  }

}

CampaignAdminContainer.propTypes = {
  config: React.PropTypes.shape({
    forms: React.PropTypes.shape({
      editForm: React.PropTypes.shape({
        schemaUrl: React.PropTypes.string,
      }),
    }),
  }),
  sectionConfigKey: React.PropTypes.string.isRequired,
};

function mapStateToProps(state, ownProps) {
  return {
    config: state.config.sections[ownProps.sectionConfigKey],
    setid: state.campaignAdmin.setid,
  };
}

function mapDispatchToProps(dispatch) {
  return {
    actions: bindActionCreators(actions, dispatch),
  };
}

export default connect(mapStateToProps, mapDispatchToProps)(CampaignAdminContainer);
