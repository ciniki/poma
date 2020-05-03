<?php
//
// Description
// ===========
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_poma_orderItemUpdate(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'item_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Order Item'),
        'date_id'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Order Date'),
        'order_id'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Order'),
        'parent_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Parent'),
        'line_number'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Line'),
        'flags'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Options'),
        'object'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Object'),
        'object_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Object ID'),
        'code'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Code'),
        'description'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Description'),
        'itype'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Type'),
        'weight_units'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Weight Units'),
        'weight_quantity'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Weight Quantity'),
        'unit_quantity'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Unit Quantity'),
        'unit_suffix'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Unit Suffix'),
        'packing_order'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Packing Order'),
        'unit_amount'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Amount'),
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
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.orderItemUpdate');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Get the order item
    //
    $strsql = "SELECT items.id, items.order_id, orders.date_id, orders.customer_id "
        . "FROM ciniki_poma_order_items AS items, ciniki_poma_orders AS orders "
        . "WHERE items.id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND items.order_id = orders.id "
        . "AND orders.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['item']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.37', 'msg'=>'Unable to find item.'));
    }
    $item = $rc['item'];

    //
    // Start transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.poma');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Check if new date_id is specified
    //
    if( isset($args['date_id']) && $args['date_id'] > 0 && $args['date_id'] != $item['date_id'] ) {
        //
        // Check for an order for the customer on that date, and unpaid
        //
        $strsql = "SELECT id "
            . "FROM ciniki_poma_orders "
            . "WHERE customer_id = '" . ciniki_core_dbQuote($ciniki, $item['customer_id']) . "' "
            . "AND date_id = '" . ciniki_core_dbQuote($ciniki, $args['date_id']) . "' "
            . "AND payment_status < 50 "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
            return $rc;
        }
        if( isset($rc['rows'][0]['id']) ) {
            $args['order_id'] = $rc['rows'][0]['id'];
        } else {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'newOrderForDate');
            $rc = ciniki_poma_newOrderForDate($ciniki, $args['tnid'], array(
                'customer_id'=>$item['customer_id'],
                'date_id'=>$args['date_id'],
                'checkdate'=>'no',
                'pickup_time'=>'last',
                ));
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                return $rc;
            }
            $args['order_id'] = $rc['order']['id'];
        }

        //
        // Check for any subitems
        //
        if( isset($args['order_id']) && $args['order_id'] > 0 ) {
            $strsql = "SELECT items.id, items.order_id "
                . "FROM ciniki_poma_order_items AS items "
                . "WHERE items.parent_id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
                . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . "AND items.order_id = '" . ciniki_core_dbQuote($ciniki, $item['order_id']) . "' "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                return $rc;
            }
            if( isset($rc['rows']) && count($rc['rows']) > 0 ) {
                $subitems = $rc['rows'];
                foreach($subitems as $subitem) {
                    $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.poma.orderitem', $subitem['id'], array('order_id'=>$args['order_id']), 0x04);
                    if( $rc['stat'] != 'ok' ) {
                        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                        return $rc;
                    }
                }
            }
        }
    }

    //
    // Update the Order Item in the database
    //
    $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.poma.orderitem', $args['item_id'], $args, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }

    //
    // Update the order
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderUpdateStatusBalance');
    $rc = ciniki_poma_orderUpdateStatusBalance($ciniki, $args['tnid'], $item['order_id']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the order the item was moved to
    //
    if( isset($args['order_id']) && $args['order_id'] > 0 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderUpdateStatusBalance');
        $rc = ciniki_poma_orderUpdateStatusBalance($ciniki, $args['tnid'], $args['order_id']);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
    }

    //
    // Commit the transaction
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.poma');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $args['tnid'], 'ciniki', 'poma');

    //
    // Update the web index if enabled
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'hookExec');
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'web', 'indexObject', array('object'=>'ciniki.poma.orderItem', 'object_id'=>$args['item_id']));

    return array('stat'=>'ok');
}
?>
