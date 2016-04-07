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

  render() {
    // <Hard coding>
    const mode = 'items'; // change to sets (lists campaigns), or items (items in campaign)
    const setID = 1;
    // </Hard coding>

    switch (mode) {
      case 'sets':
        return this.renderSets();
      case 'items':
        return this.renderItems(setID);
    }
  }

  /**
   * Renders list of sets
   * @returns {XML}
   */
  renderSets() {
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
   * Renders items view for a set
   *
   * @param setID
   */
  renderItems(setID) {
    // <Hard coding>
    const itemID = 1;
    // </Hard coding>


    // Trigger different layout when preview is enabled
    const
      previewUrl = this.previewURLForItem(itemID),
      itemGroups = this.groupItemsForSet(setID),
      classNames = previewUrl ? 'cms-middle with-preview' : 'cms-middle no-preview';

    // Get items in this set
    let accordionGroups = [];
    Object.keys(itemGroups).forEach(className => {
      let group = itemGroups[className],
        accordionItems = [],
        groupCount = group.items.length,
        title = groupCount + ' ' +  (groupCount === 1 ? group.singular : group.plural),
        id = 'Set_' + setID + '_Group_' + className;

      // Create items for this group
      group.items.forEach(item => {
        accordionItems.push(
          <AccordionItem key={item.ID}>
            <ChangeSetItem item={item} />
          </AccordionItem>
        );
      });

      // Merge into group
      accordionGroups.push(<AccordionGroup key={id} id={id} title={title}>{accordionItems}</AccordionGroup>);
    });

    return (
      <div className={classNames}>
        <div className="cms-campaigns collapse in" aria-expanded="true">
          <NorthHeader />
          <Accordion>
            {accordionGroups}
          </Accordion>
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
   * @param setid
   * @return Array
   */
  groupItemsForSet(setid) {
    let groups = {},
      items = this.itemsForSet(setid);

    // group by whatever
    items.forEach(item => {
      // Create new group if needed
      let classname = item['BaseClass'];
      if (!groups[classname]) {
        groups[classname] = {
          singular: item['Singular'],
          plural: item['Plural'],
          items: []
        };
      }

      // Push items
      groups[classname]['items'].push(item);
    });

    return groups;
  }

  /**
   * List of items for a set
   *
   * @param setid
   * @return Array
   */
  itemsForSet(setid) {
    // hard coded json
    return [{
					"_links": {"self": {"href": "admin\/campaigns\/item\/1"}},
					"ID": 1,
					"Created": "2016-03-29 18:08:18",
					"LastEdited": "2016-03-29 18:20:51",
					"Title": "Home",
					"ChangeType": "none",
					"Added": "explicitly",
					"ObjectClass": "Page",
					"ObjectID": 1,
					"BaseClass": "SiteTree",
					"Singular": "Page",
					"Plural": "Pages"
				}, {
					"_links": {"self": {"href": "admin\/campaigns\/item\/2"}},
					"ID": 2,
					"Created": "2016-03-29 18:08:18",
					"LastEdited": "2016-03-29 18:20:51",
					"Title": "About Us",
					"ChangeType": "modified",
					"Added": "explicitly",
					"ObjectClass": "Page",
					"ObjectID": 2,
					"BaseClass": "SiteTree",
					"Singular": "Page",
					"Plural": "Pages"
				}, {
					"_links": {"self": {"href": "admin\/campaigns\/item\/3"}},
					"ID": 3,
					"Created": "2016-03-29 18:08:18",
					"LastEdited": "2016-03-29 18:20:51",
					"Title": "Contact Us",
					"ChangeType": "modified",
					"Added": "explicitly",
					"ObjectClass": "Page",
					"ObjectID": 3,
					"BaseClass": "SiteTree",
					"Singular": "Page",
					"Plural": "Pages"
				}, {
					"_links": {"self": {"href": "admin\/campaigns\/item\/4"}},
					"ID": 4,
					"Created": "2016-03-29 18:08:18",
					"LastEdited": "2016-03-29 18:20:51",
					"Title": "Page not found",
					"ChangeType": "modified",
					"Added": "explicitly",
					"ObjectClass": "ErrorPage",
					"ObjectID": 4,
					"BaseClass": "SiteTree",
					"Singular": "Page",
					"Plural": "Pages"
				}, {
					"_links": {"self": {"href": "admin\/campaigns\/item\/5"}},
					"ID": 5,
					"Created": "2016-03-29 18:08:18",
					"LastEdited": "2016-03-29 18:20:51",
					"Title": "Server error",
					"ChangeType": "none",
					"Added": "explicitly",
					"ObjectClass": "ErrorPage",
					"ObjectID": 5,
					"BaseClass": "SiteTree",
					"Singular": "Page",
					"Plural": "Pages"
				}, {
					"_links": {"self": {"href": "admin\/campaigns\/item\/7"}},
					"ID": 7,
					"Created": "2016-03-29 18:20:51",
					"LastEdited": "2016-03-29 18:20:51",
					"Title": "Fireworks",
					"ChangeType": "created",
					"Added": "implicitly",
					"ObjectClass": "Image",
					"ObjectID": 2,
					"BaseClass": "File",
					"Singular": "File",
					"Plural": "Files"
				}];
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
