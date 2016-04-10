import React from 'react';
import SilverStripeComponent from 'silverstripe-component';

class GridFieldRowComponent extends SilverStripeComponent {

  constructor(props) {
    super(props);
    this.handleDrillDown = this.handleDrillDown.bind(this);
  }

  render() {
    const props = {
      className: 'grid-field-row-component [ list-group-item ]',
      onClick: this.handleDrillDown,
    };

    return <li {...props}>{this.props.children}</li>;
  }

  handleDrillDown(event) {
    if (typeof this.props.handleDrillDown === 'undefined') {
      return;
    }

    this.props.handleDrillDown(event);
  }

}

GridFieldRowComponent.propTypes = {
  handleDrillDown: React.PropTypes.func,
};

export default GridFieldRowComponent;
