import React from 'react';
import { connect } from 'react-redux';
import SilverStripeComponent from 'silverstripe-component';

/**
 * Describes an individual changeset
 */
class ChangeSetItem extends SilverStripeComponent {
  render() {
    let badge, links,
      item = this.props.item;

    // change badge
    switch (item.ChangeType) {
      case 'created':
        badge = <span className="label label-warning">Draft</span>;
        break;
      case 'modified':
        badge = <span className="label label-warning">Modified</span>;
        break;
      case 'deleted':
        badge = <span className="label label-error">Removed</span>;
        break;
      case 'none':
        badge = <span className="label label-success item_visible-hovered">Already published</span>;
        break;
    }

    // Linked items
    links = <span className="btnbtn-link pull-xs-right">[lk] 3 links</span>;

    return (
      <div>
        <h6 className="list-group-item-heading">{item.Title}</h6>
        {links}
        {badge}
      </div>
    );
  }
}
export default ChangeSetItem;
