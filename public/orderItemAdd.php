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
//        'line_number'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Line'),
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
        'unit_amount'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Amount'),
        'unit_discount_amount'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'currency', 'name'=>'Discount Amount'),
        'unit_discount_percentage'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Discount Percentage'),
        'cdeposit_description'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Container Deposit Description'),
        'cdeposit_amount'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Container Deposit Amount'),
        'taxtype_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Tax Type'),
        'notes'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Notes'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    $fields = array('unit_amount', 'unit_discount_amount', 'cdeposit_amount');
    foreach($fields as $field) {
        if( isset($args[$field]) ) {
            $args[$field] = preg_replace("/[\$,]/", '', $args[$field]);
        }
    }

    //
    // Check access to business_id as owner
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['business_id'], 'ciniki.poma.orderItemAdd');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load the order
    //
    $strsql = "SELECT ciniki_poma_orders.id, "
        . "ciniki_poma_orders.uuid, "
        . "ciniki_poma_orders.status, "
        . "IFNULL(MAX(line_number), 0) AS max_line_number "
        . "FROM ciniki_poma_orders "
        . "LEFT JOIN ciniki_poma_order_items ON ("
            . "ciniki_poma_orders.id = ciniki_poma_order_items.order_id "
            . "AND ciniki_poma_order_items.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
            . ") "
        . "WHERE ciniki_poma_orders.id = '" . ciniki_core_dbQuote($ciniki, $args['order_id']) . "' "
        . "AND ciniki_poma_orders.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
        . "GROUP BY ciniki_poma_orders.id "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['order']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.78', 'msg'=>'Unable to find order'));
    }
    $order = $rc['order'];
    $args['date_id'] = $order['date_id'];
    $args['line_number'] = $order['max_line_number'] + 1;

    //
    // Check for object and lookup
    //
    if( isset($args['object']) && $args['object'] != '' && isset($args['object_id']) && $args['object_id'] != '' ) {
        //
        // Get the details for the item
        //
        list($pkg, $mod, $obj) = explode('.', $args['object']);
        $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'itemLookup');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.91', 'msg'=>'Unable to add favourite.'));
        }
        $fn = $rc['function_call'];
        $rc = $fn($ciniki, $args['business_id'], $args);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['item']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.92', 'msg'=>'Unable to add favourite.'));
        }
        $item = $rc['item'];
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
    // Check if there are subitems
    //
    if( isset($item['subitems']) ) {
        foreach($item['subitems'] as $subitem) {
            $subitem['order_id'] = $args['order_id'];
            $subitem['parent_id'] = $item_id;
            $rc = ciniki_core_objectAdd($ciniki, $args['business_id'], 'ciniki.poma.orderitem', $subitem, 0x04);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                return $rc;
            }
        }
    }

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
