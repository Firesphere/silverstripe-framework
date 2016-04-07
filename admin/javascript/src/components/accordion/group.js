import React from 'react';
import { connect } from 'react-redux';
import SilverStripeComponent from 'silverstripe-component';

class AccordionGroup extends SilverStripeComponent {
  render() {
    return (
      <div className="accordion-group">
        <h6 className="accordion-title" role="tab" id={this.props.id}>{this.props.title}</h6>
        {this.props.children}
      </div>
    );
  }
}
export default AccordionGroup;
