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
    if( !isset($args['item_id']) || $args['item_id'] < 1 || $args['item_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.54', 'msg'=>'No item specified.'));
    }
    if( !isset($ciniki['session']['customer']['id']) || $ciniki['session']['customer']['id'] < 1 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.55', 'msg'=>'No customer specified.'));
    }
    
    //
    // Load the detail for the item in the order
    //
    $strsql = "SELECT ciniki_poma_order_items.id, "
        . "ciniki_poma_order_items.uuid, "
        . "ciniki_poma_order_items.order_id, "
        . "ciniki_poma_order_items.object, "
        . "ciniki_poma_order_items.object_id, "
        . "ciniki_poma_order_items.itype, "
        . "ciniki_poma_order_items.weight_quantity, "
        . "ciniki_poma_order_items.unit_quantity "
        . "FROM ciniki_poma_order_items "
        . "WHERE ciniki_poma_order_items.id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
        . "AND ciniki_poma_order_items.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['item']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.56', 'msg'=>'Invalid order item.'));
    }
    $existing_item = $rc['item'];

    //
    // Get the details for the item
    //
    if( $existing_item['object'] != '' ) {
        list($pkg, $mod, $obj) = explode('.', $existing_item['object']);
        $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'itemLookup');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.57', 'msg'=>'Unable to find item.'));
        }
        $fn = $rc['function_call'];
        $rc = $fn($ciniki, $business_id, array('object'=>$existing_item['object'], 'object_id'=>$existing_item['object_id']));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['item']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.58', 'msg'=>'Unable to find order item.'));
        }
        $o_item = $rc['item'];
    }

    //
    // FIXME: Check inventory before allowing an increase in quantity when limited items
    //

    //
    // The list of fields the customer is allowed to change
    //
    $fields = array('unit_quantity', 'weight_quantity');

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
    // Decide how the quantity is applied
    //
    $new_item = array();
    if( isset($_GET['quantity']) ) {
        if( $_GET['quantity'] == 0 ) {
            //
            // Remove item
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
            $rc = ciniki_core_objectDelete($ciniki, $business_id, 'ciniki.poma.orderitem', $existing_item['id'], $existing_item['uuid'], 0x04);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                return $rc;
            }
        } else {
            if( $existing_item['itype'] == 10 ) {
                $new_item['weight_quantity'] = $_GET['quantity'];
            } else {
                $new_item['unit_quantity'] = $_GET['quantity'];
            }
        }
    }

    $update_args = array();
    foreach($fields as $field) {
        if( isset($new_item[$field]) && $new_item[$field] != $existing_item[$field] ) {
            $update_args[$field] = $new_item[$field];
        }
    }

    if( count($update_args) > 0 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
        $rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.poma.orderitem', $existing_item['id'], $update_args, 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
            return $rc;
        }
    } 

    //
    // Update the order totals
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderUpdateStatusBalance');
    $rc = ciniki_poma_orderUpdateStatusBalance($ciniki, $business_id, $existing_item['order_id']);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }
    if( isset($rc['order']) ) {
        $order = $rc['order'];
    }

    //
    // Commit the transaction
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.poma');
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }

    //
    // Load the order with any new calculations
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'orderLoad'); 
    $rc = ciniki_poma_web_orderLoad($ciniki, $settings, $business_id, array('order_id'=>$existing_item['order_id']));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $order = $rc['order']; 

    return array('stat'=>'ok', 'order'=>$order);
}
?>