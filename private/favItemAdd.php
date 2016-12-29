<?php
//
// Description
// -----------
// This function will add another modules object/objectid as a favourite.
//
// Arguments
// ---------
// ciniki:
// business_id:                 The business ID to check the session user against.
// method:                      The requested method.
//
// Returns
// -------
// <rsp stat='ok' />
//
function ciniki_poma_favItemAdd(&$ciniki, $business_id, $args) {
    
    //
    // Check args
    //
    if( !isset($args['object']) || $args['object'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.23', 'msg'=>'No item specified.'));
    }
    if( !isset($args['object_id']) || $args['object_id'] < 1 || $args['object_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.24', 'msg'=>'No item specified.'));
    }
    if( !isset($args['customer_id']) || $args['customer_id'] < 1 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.25', 'msg'=>'No customer specified.'));
    }

    //
    // Check if item already exists as a fav
    //
    $strsql = "SELECT id, status "
        . "FROM ciniki_poma_customer_items "
        . "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "AND customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
        . "AND itype = 20 "
        . "AND object = '" . ciniki_core_dbQuote($ciniki, $args['object']) . "' "
        . "AND object_id = '" . ciniki_core_dbQuote($ciniki, $args['object_id']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.26', 'msg'=>'Unable to add favourite.', 'err'=>$rc['err']));
    }
    if( isset($rc['item']) || (isset($rc['rows']) && count($rc['rows']) > 1) ) {
        // Already added
        return array('stat'=>'ok');
    }

    //
    // Add the favourite
    //
    $args['itype'] = 20;
    $args['quantity'] = 1;
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    $rc = ciniki_core_objectAdd($ciniki, $business_id, 'ciniki.poma.customeritem', $args, 0x07);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    return array('stat'=>'ok');
}
?>
