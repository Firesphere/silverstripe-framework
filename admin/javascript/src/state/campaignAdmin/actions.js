import ACTION_TYPES from './action-types';

/**
 * Show specified campaign set
 *
 * @param Integer setid
 * @param object config
 */
export function showSetItems(setid) {
  return {
    type: ACTION_TYPES.SET_CAMPAIGN_ACTIVE_CHANGESET,
    payload: { setid },
  };
}

export function showSetList() {
  return {
    type: ACTION_TYPES.SET_CAMPAIGN_ACTIVE_CHANGESET,
    payload: { setid: null },
  };
}
