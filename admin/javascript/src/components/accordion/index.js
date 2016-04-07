import React from 'react';
import { connect } from 'react-redux';
import SilverStripeComponent from 'silverstripe-component';

class Accordion extends SilverStripeComponent {
  render() {
    return (
      <div role="tablist" aria-multiselectable="true">{this.props.children}</div>
    );
  }
}
export default Accordion;
