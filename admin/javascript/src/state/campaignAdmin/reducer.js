import deepFreeze from 'deep-freeze';
import ACTION_TYPES from './action-types';

const initialState = {
  setid: null,
  view: null
};

function campaignAdminReducer(state = initialState, action) {
  switch (action.type) {

    case ACTION_TYPES.SET_CAMPAIGN_ACTIVE_CHANGESET:
      return deepFreeze(Object.assign({}, state, {
        setid: action.payload.setid,
        view: action.payload.view,
      }));

    default:
      return state;

  }
}

export default campaignAdminReducer;
