<?php
//
// Description
// -----------
// This function will update an orders item with new quantity. If required, an order will be created
// based on the current session date.
//
// Arguments
// ---------
// ciniki:
// tnid:                 The tenant ID to check the session user against.
// method:                      The requested method.
//
// Returns
// -------
// <rsp stat='ok' />
//
function ciniki_poma_web_apiRepeatObjectUpdate(&$ciniki, $settings, $tnid, $args) {
    
    //
    // Start a transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.poma');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the item
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'repeatItemUpdate');
    $item_args = array(
        'object'=>$args['object'],
        'object_id'=>$args['object_id'],
        'customer_id'=>$ciniki['session']['customer']['id'],
        );
    if( isset($_GET['quantity']) && $_GET['quantity'] != '' ) {
        $item_args['quantity'] = $_GET['quantity'];
    }
    if( isset($_GET['repeat_days']) && $_GET['repeat_days'] != '' ) {
        $item_args['repeat_days'] = $_GET['repeat_days'];
    }
    if( isset($_GET['skip']) && $_GET['skip'] == 'yes' ) {
        $item_args['skip'] = 'yes';
    }
    $rc = ciniki_poma_repeatItemUpdate($ciniki, $tnid, $item_args);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $item = $rc['item'];

    //
    // Commit the transaction
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.poma');
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }

    return array('stat'=>'ok', 'item'=>$item);
}
?>
