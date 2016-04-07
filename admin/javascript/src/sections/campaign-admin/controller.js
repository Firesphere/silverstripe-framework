import React from 'react';
import { connect } from 'react-redux';
import SilverStripeComponent from 'silverstripe-component';
import FormAction from 'components/form-action/index';
import i18n from 'i18n';
import NorthHeader from 'components/north-header/index';
import FormBuilder from 'components/form-builder/index';
import CampaignPreview from './preview';
import Accordion from 'components/accordion/index';
import AccordionGroup from 'components/accordion/group';
import AccordionItem from 'components/accordion/item';
import ChangeSetItem from './item';

class CampaignAdminContainer extends SilverStripeComponent {

  constructor(props) {
    super(props);

    this.addCampaign = this.addCampaign.bind(this);
  }

  componentDidMount() {
    window.ss.router(`/${this.props.config.itemListViewLink}`, () => {
      this.forceUpdate();
    });
  }

  render() {
    const isListRoute = window
      .ss
      .router
      .routeAppliesToCurrentLocation(`/${this.props.config.itemListViewLink}`);

    if (isListRoute) {
    const setID = 1;
      return this.renderItemListView(setID);
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
          <FormBuilder schemaUrl={schemaUrl} createFn={this.createFn}/>
        </div>
      </div>
    );
  }

  /**
   * Renders a list of items in a Campaign.
   *
   * @param string setID - ID of the Campaign to display items for.
   *
   * @return object
   */
  renderItemListView(setID) {
    const itemID = 1;

    // Trigger different layout when preview is enabled
    const previewUrl = this.previewURLForItem(itemID);
    const itemGroups = this.groupItemsForSet(setID);
    const classNames = previewUrl ? 'cms-middle with-preview' : 'cms-middle no-preview';

    // Get items in this set
    let accordionGroups = [];

    Object.keys(itemGroups).forEach(className => {
      const group = itemGroups[className];
      const groupCount = group.items.length;

      let accordionItems = [];
      let title = `${groupCount} ${groupCount === 1 ? group.singular : group.plural}`;
      let groupid = `Set_${setID}_Group_${className}`;

      // Create items for this group
      group.items.forEach(item => {
        // Add extra css class for published items
        let itemClassName = '';

        if(item.ChangeType === 'none') {
          itemClassName = 'list-group-item--published';
        }

        accordionItems.push(
          <AccordionItem key={item.ID} className={itemClassName}>
            <ChangeSetItem item={item} />
          </AccordionItem>
        );
      });

      // Merge into group
      accordionGroups.push(
        <AccordionGroup key={groupid} groupid={groupid} title={title}>
          {accordionItems}
        </AccordionGroup>
      );
    });

    return (
      <div className={classNames}>
        <div className="cms-campaigns collapse in" aria-expanded="true">
          <NorthHeader />
          <div className="col-md-12 campaign-items">
            <Accordion>
              {accordionGroups}
            </Accordion>
          </div>
        </div>
        { previewUrl && <CampaignPreview previewUrl={previewUrl}/> }
      </div>
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
    return <Component {...props} />;
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

  /**
   * Group items for changeset display
   *
   * @param string setid
   *
   * @return array
   */
  groupItemsForSet(setid) {
    const groups = {};
    const items = this.itemsForSet(setid);

    // group by whatever
    items.forEach(item => {
      // Create new group if needed
      const classname = item.BaseClass;

      if (!groups[classname]) {
        groups[classname] = {
          singular: item.Singular,
          plural: item.Plural,
          items: [],
        };
      }

      // Push items
      groups[classname].items.push(item);
    });

    return groups;
  }

  /**
   * List of items for a set
   *
   * @return array
   */
  itemsForSet() {
    // hard coded json
    return [
      {
        "_links": {
          "self": {
            "href": "admin\/campaigns\/item\/1"
          }
          },
        "Plural": "Pages",
        "Thumbnail": null
        },
      {
        "_links": {
          "self": {
            "href": "admin\/campaigns\/item\/2"
          }
          },
        "Plural": "Pages",
        "Thumbnail": null
      },
      {
        "_links": {
          "self": {
            "href": "admin\/campaigns\/item\/3"
          }
        },
        "Plural": "Pages",
        "Thumbnail": null
      },
      {
        "_links": {
          "self": {
            "href": "admin\/campaigns\/item\/4"
          }
        },
        "Plural": "Pages",
        "Thumbnail": null
      },
      {
        "_links": {
          "self": {
            "href": "admin\/campaigns\/item\/5"
          }
        },
        "Plural": "Pages",
        "Thumbnail": null
      },
      {
        "_links": {
          "self": {
            "href": "admin\/campaigns\/item\/7"
          }
          },
        "Plural": "Files",
        "Thumbnail": "\/assets\/Uploads\/7e2bc8d2e1\/Fireworks__PadWzY0LDY0LCJGRkZGRkYiXQ.jpg"
      }
    ];
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
  };
}

export default connect(mapStateToProps)(CampaignAdminContainer);
