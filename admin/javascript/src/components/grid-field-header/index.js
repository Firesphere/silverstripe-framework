import React from 'react';
import SilverStripeComponent from '../../SilverStripeComponent';
import GridFieldRowComponent from '../grid-field-row';

class GridFieldHeaderComponent extends SilverStripeComponent {

    render() {
        return (
            <GridFieldRowComponent>{this.props.children}</GridFieldRowComponent>
        );
    }

}

export default GridFieldHeaderComponent;
