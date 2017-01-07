<?php
//
// Description
// -----------
// This method will add a new order item for the business.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:        The ID of the business to add the Order Item to.
//
// Returns
// -------
//
function ciniki_poma_orderItemAdd(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'),
        'order_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Order'),
        'parent_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Parent'),
        'line_number'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Line'),
        'flags'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Options'),
        'object'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Object'),
        'object_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Object ID'),
        'code'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Code'),
        'description'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Description'),
        'itype'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Type'),
        'weight_units'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Weight Units'),
        'weight_quantity'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Weight Quantity'),
        'unit_quantity'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Unit Quantity'),
        'unit_suffix'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Unit Suffix'),
        'packing_order'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Packing Order'),
        'unit_amount'=>array('required'=>'yes', 'blank'=>'no', 'type'=>'currency', 'name'=>'Amount'),
        'unit_discount_amount'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'currency', 'name'=>'Discount Amount'),
        'unit_discount_percentage'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Discount Percentage'),
//        'subtotal_amount'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Subtotal Amount'),
//        'discount_amount'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Discount Amount'),
//        'total_amount'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Total Amount'),
        'taxtype_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Tax Type'),
        'notes'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Notes'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to business_id as owner
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['business_id'], 'ciniki.poma.orderItemAdd');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Start transaction
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
    // Add the order item to the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    $rc = ciniki_core_objectAdd($ciniki, $args['business_id'], 'ciniki.poma.orderitem', $args, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }
    $item_id = $rc['id'];

    //
    // Update the order
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderUpdateStatusBalance');
    $rc = ciniki_poma_orderUpdateStatusBalance($ciniki, $args['business_id'], $args['order_id']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Commit the transaction
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.poma');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the last_change date in the business modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
    ciniki_businesses_updateModuleChangeDate($ciniki, $args['business_id'], 'ciniki', 'poma');

    //
    // Update the web index if enabled
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'hookExec');
    ciniki_core_hookExec($ciniki, $args['business_id'], 'ciniki', 'web', 'indexObject', array('object'=>'ciniki.poma.orderItem', 'object_id'=>$item_id));

    return array('stat'=>'ok', 'id'=>$item_id);
}
?>
