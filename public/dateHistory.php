<?php
//
// Description
// -----------
// This method will return the list of actions that were applied to an element of an order date.
// This method is typically used by the UI to display a list of changes that have occured
// on an element through time. This information can be used to revert elements to a previous value.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant to get the details for.
// date_id:          The ID of the order date to get the history for.
// field:                   The field to get the history for.
//
// Returns
// -------
//
function ciniki_poma_dateHistory($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'date_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Order Date'),
        'field'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'field'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.dateHistory');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    if( $args['field'] == 'order_date' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbGetModuleHistoryReformat');
        return ciniki_core_dbGetModuleHistoryReformat($ciniki, 'ciniki.poma', 'ciniki_poma_history', $args['tnid'], 'ciniki_poma_order_dates', $args['date_id'], $args['field'], 'date');
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbGetModuleHistory');
    return ciniki_core_dbGetModuleHistory($ciniki, 'ciniki.poma', 'ciniki_poma_history', $args['tnid'], 'ciniki_poma_order_dates', $args['date_id'], $args['field']);
}
?>
