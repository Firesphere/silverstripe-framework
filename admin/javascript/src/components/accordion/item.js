import React from 'react';
import { connect } from 'react-redux';
import SilverStripeComponent from 'silverstripe-component';

class AccordionItem extends SilverStripeComponent {
  render() {
    return (
      <a className="list-group-item">
        {this.props.children}
      </a>
    );
  }
}
export default AccordionItem;
