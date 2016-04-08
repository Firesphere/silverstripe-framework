import deepFreeze from 'deep-freeze';
import ACTION_TYPES from './action-types';

function campaignAdminReducer(state = { setid: null }, action) {
  switch (action.type) {

    case ACTION_TYPES.SET_CAMPAIGN_ACTIVE_CHANGESET:
      return deepFreeze(Object.assign({}, state, { setid: action.payload.setid }));

    default:
      return state;

  }
}

export default campaignAdminReducer;
