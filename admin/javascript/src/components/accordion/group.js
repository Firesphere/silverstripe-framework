import React from 'react';
import { connect } from 'react-redux';
import SilverStripeComponent from 'silverstripe-component';
import Collapse from 'bootstrap-collapse';

class AccordionGroup extends SilverStripeComponent {
  render() {
    let
      headerID = this.props.groupid + '_Header',
      listID = this.props.groupid + '_Items',
      href = '#' + listID;

    return (
      <div className="accordion-group">
        <h6 className="accordion-group__title" role="tab" id={headerID}>
          <a data-toggle="collapse" href={href} aria-expanded="true" aria-controls={listID}>
            {this.props.title}
          </a>
        </h6>
        <div id={listID} aria-expanded="true" className="list-group list-group-flush collapse in" role="tabpanel" aria-labelledby={headerID}>
          {this.props.children}
        </div>
      </div>
    );
  }
}
export default AccordionGroup;
