import React from 'react';
import { connect } from 'react-redux';
import backend from 'lib/Backend';
import SilverStripeComponent from 'lib/SilverStripeComponent';
import FormAction from 'components/FormAction/FormAction';
import i18n from 'i18n';
import Toolbar from 'components/Toolbar/Toolbar';
import FormBuilder from 'components/FormBuilder/FormBuilder';
import CampaignAdminList from './CampaignAdminList';

class CampaignAdmin extends SilverStripeComponent {

  constructor(props) {
    super(props);

    this.addCampaign = this.addCampaign.bind(this);
    this.publishApi = backend.createEndpointFetcher({
      url: this.props.sectionConfig.publishEndpoint.url,
      method: this.props.sectionConfig.publishEndpoint.method,
      defaultData: { SecurityID: this.props.securityId },
      payloadSchema: {
        id: { urlReplacement: ':id', remove: true },
      },
    });
    this.campaignListCreateFn = this.campaignListCreateFn.bind(this);
    this.campaignAddCreateFn = this.campaignAddCreateFn.bind(this);
    this.campaignEditCreateFn = this.campaignEditCreateFn.bind(this);
  }

  render() {
    let view = null;

    switch (this.props.view) {
      case 'show':
        view = this.renderItemListView();
        break;
      case 'edit':
        view = this.renderDetailEditView();
        break;
      case 'create':
        view = this.renderCreateView();
        break;
      default:
        view = this.renderIndexView();
    }

    return view;
  }

  /**
   * Renders the default view which displays a list of Campaigns.
   *
   * @return object
   */
  renderIndexView() {
    const schemaUrl = this.props.sectionConfig.form.EditForm.schemaUrl;
    const formActionProps = {
      label: i18n._t('Campaigns.ADDCAMPAIGN'),
      icon: 'plus',
      handleClick: this.addCampaign,
    };
    const formBuilderProps = {
      createFn: this.campaignListCreateFn,
      schemaUrl,
    };

    return (
      <div className="cms-content__inner no-preview">
        <div className="cms-content__left cms-campaigns collapse in" aria-expanded="true">
          <Toolbar>
            <div className="breadcrumb breadcrumb--current-only">
              <h2 className="breadcrumb__item-title breadcrumb__item-title--last">Campaigns</h2>
            </div>
          </Toolbar>
          <div className="panel-scrollable--single-toolbar">
            <div className="toolbar--content">
              <div className="btn-toolbar">
                <FormAction {...formActionProps} />
              </div>
            </div>
            <FormBuilder {...formBuilderProps} />
          </div>
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
      campaignId: this.props.campaignId,
      itemListViewEndpoint: this.props.sectionConfig.itemListViewEndpoint,
      publishApi: this.publishApi,
      breadcrumbs: this.getBreadcrumbs(),
    };

    return (
      <CampaignAdminList {...props} />
    );
  }

  /**
   * Renders the Detail Edit Form for a Campaign.
   */
  renderDetailEditView() {
    const baseSchemaUrl = this.props.sectionConfig.form.DetailEditForm.schemaUrl;
    const formBuilderProps = {
      createFn: this.campaignEditCreateFn,
      schemaUrl: `${baseSchemaUrl}/ChangeSet/${this.props.campaignId}`,
    };

    return (
      <div className="cms-middle no-preview">
        <div className="cms-campaigns collapse in" aria-expanded="true">
          <Toolbar showBackButton>
            <div className="breadcrumb breadcrumb--current-only">
              <h2 className="text-truncate breadcrumb__item-title--last">Campaigns</h2>
            </div>
          </Toolbar>
          <FormBuilder {...formBuilderProps} />
        </div>
      </div>
    );
  }

  /**
   * Render the view for creating a new Campaign.
   */
  renderCreateView() {
    const baseSchemaUrl = this.props.sectionConfig.form.DetailEditForm.schemaUrl;
    const formBuilderProps = {
      createFn: this.campaignAddCreateFn,
      schemaUrl: `${baseSchemaUrl}/ChangeSet`,
    };

    return (
      <div className="cms-middle no-preview">
        <div className="cms-campaigns collapse in" aria-expanded="true">
          <Toolbar showBackButton>
            <div className="breadcrumb breadcrumb--current-only">
              <h2 className="text-truncate breadcrumb__item-title--last">Campaigns</h2>
            </div>
          </Toolbar>
          <div className="cms-middle__scrollable">
            <FormBuilder {...formBuilderProps} />
          </div>
        </div>
      </div>
    );
  }

  /**
   * Hook to allow customisation of components being constructed
   * by the Campaign DetailEdit FormBuilder.
   *
   * @param {Object} Component - Component constructor.
   * @param {Object} props - Props passed from FormBuilder.
   *
   * @return {Object} - Instanciated React component
   */
  campaignEditCreateFn(Component, props) {
    const indexRoute = this.props.sectionConfig.route;

    // Route to the Campaigns index view when 'Cancel' is clicked.
    if (props.name === 'action_cancel') {
      const extendedProps = Object.assign({}, props, {
        handleClick: (event) => {
          event.preventDefault();
          window.ss.router.show(indexRoute);
        },
      });

      return <Component key={props.name} {...extendedProps} />;
    }

    return <Component key={props.name} {...props} />;
  }

  /**
   * Hook to allow customisation of components being constructed
   * by the Campaign creation FormBuilder.
   *
   * @param {Object} Component - Component constructor.
   * @param {Object} props - Props passed from FormBuilder.
   *
   * @return {Object} - Instanciated React component
   */
  campaignAddCreateFn(Component, props) {
    const indexRoute = this.props.sectionConfig.route;

    // Route to the Campaigns index view when 'Cancel' is clicked.
    if (props.name === 'action_cancel') {
      const extendedProps = Object.assign({}, props, {
        handleClick: (event) => {
          event.preventDefault();
          window.ss.router.show(indexRoute);
        },
      });

      return <Component key={props.name} {...extendedProps} />;
    }

    return <Component key={props.name} {...props} />;
  }

  /**
   * Hook to allow customisation of components being constructed
   * by the Campaign list FormBuilder.
   *
   * @param object Component - Component constructor.
   * @param object props - Props passed from FormBuilder.
   *
   * @return object - Instanciated React component
   */
  campaignListCreateFn(Component, props) {
    const campaignViewRoute = this.props.sectionConfig.campaignViewRoute;
    const typeUrlParam = 'set';

    if (props.component === 'GridField') {
      const extendedProps = Object.assign({}, props, {
        data: Object.assign({}, props.data, {
          handleDrillDown: (event, record) => {
            // Set url and set list
            const path = campaignViewRoute
              .replace(/:type\?/, typeUrlParam)
              .replace(/:id\?/, record.ID)
              .replace(/:view\?/, 'show');

            window.ss.router.show(path);
          },
          handleEditRecord: (event, id) => {
            const path = campaignViewRoute
              .replace(/:type\?/, typeUrlParam)
              .replace(/:id\?/, id)
              .replace(/:view\?/, 'edit');

            window.ss.router.show(path);
          },
        }),
      });

      return <Component key={extendedProps.name} {...extendedProps} />;
    }

    return <Component key={props.name} {...props} />;
  }

  /**
   * @todo Use dynamic breadcrumbs
   */
  getBreadcrumbs() {
    return [
      {
        text: 'Campaigns',
        href: 'admin/campaigns',
      },
      {
        text: 'March release',
        href: 'admin/campaigns/show/1',
      },
    ];
  }

  addCampaign() {
    const path = this.props.sectionConfig.campaignViewRoute
      .replace(/:type\?/, 'set')
      .replace(/:id\?/, 0)
      .replace(/:view\?/, 'create');

    window.ss.router.show(path);
  }

}

CampaignAdmin.propTypes = {
  campaignId: React.PropTypes.string,
  sectionConfig: React.PropTypes.object.isRequired,
  securityId: React.PropTypes.string.isRequired,
  view: React.PropTypes.string,
};

function mapStateToProps(state) {
  return {
    config: state.config,
    campaignId: state.campaign.campaignId,
    view: state.campaign.view,
  };
}

export default connect(mapStateToProps)(CampaignAdmin);
