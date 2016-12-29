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
// business_id:                 The business ID to check the session user against.
// method:                      The requested method.
//
// Returns
// -------
// <rsp stat='ok' />
//
function ciniki_poma_web_apiOrderItemUpdate(&$ciniki, $settings, $business_id, $args) {
    
    //
    // Check args
    //
    if( !isset($args['object']) || $args['object'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.38', 'msg'=>'No item specified.'));
    }
    if( !isset($args['object_id']) || $args['object_id'] < 1 || $object_id == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.39', 'msg'=>'No item specified.'));
    }
    if( !isset($ciniki['session']['customer']['id']) || $ciniki['session']['customer']['id'] < 1 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.40', 'msg'=>'No customer specified.'));
    }

    //
    // Get the details for the item
    //
    list($pkg, $mod, $obj) = explode($args['object']);
    $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'itemLookup');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.41', 'msg'=>'Unable to add favourite.'));
    }
    $fn = $rc['function_call'];
    $rc = $fn($ciniki, $business_id, $args);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['item']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.42', 'msg'=>'Unable to add favourite.'));
    }
    $item = $rc['item'];

    //
    // Check if there is already an order
    //


    //
    // Check if the item already exists
    //


    //
    // The list of fields the customer is allowed to change
    //
    $fields = array('unit_quantity', 'weight_quantity');

    return array('stat'=>'ok');
}
?>
