import React from 'react';
import { connect } from 'react-redux';
import SilverStripeComponent from 'silverstripe-component';

class CampaignPreview extends SilverStripeComponent {

  render() {
    return (
      <div className="pages-preview">
        <iframe src={this.props.previewUrl}></iframe>
      </div>
    );
  }
}

export default CampaignPreview;
