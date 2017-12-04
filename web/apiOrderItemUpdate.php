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
function ciniki_poma_web_apiOrderItemUpdate(&$ciniki, $settings, $tnid, $args) {
    
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
        . "ciniki_poma_order_items.flags, "
        . "ciniki_poma_order_items.itype, "
        . "ciniki_poma_order_items.weight_quantity, "
        . "ciniki_poma_order_items.unit_quantity "
        . "FROM ciniki_poma_order_items "
        . "WHERE ciniki_poma_order_items.id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
        . "AND ciniki_poma_order_items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
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
        $rc = $fn($ciniki, $tnid, array('object'=>$existing_item['object'], 'object_id'=>$existing_item['object_id']));
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
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
            //
            // Remove any subitems
            //
            $strsql = "SELECT id, uuid "
                . "FROM ciniki_poma_order_items "
                . "WHERE parent_id = '" . ciniki_core_dbQuote($ciniki, $existing_item['id']) . "' "
                . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                return $rc;
            }
            if( isset($rc['rows']) ) {
                $subitems = $rc['rows'];
                foreach($subitems as $subitem) {
                    $rc = ciniki_core_objectDelete($ciniki, $tnid, 'ciniki.poma.orderitem', $subitem['id'], $subitem['uuid'], 0x04);
                    if( $rc['stat'] != 'ok' ) {
                        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                        return $rc;
                    }
                }
            }
            //
            // Remove item
            //
            $rc = ciniki_core_objectDelete($ciniki, $tnid, 'ciniki.poma.orderitem', $existing_item['id'], $existing_item['uuid'], 0x04);
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
        $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.poma.orderitem', $existing_item['id'], $update_args, 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
            return $rc;
        }
    } 

    //
    // Update the order totals
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderUpdateStatusBalance');
    $rc = ciniki_poma_orderUpdateStatusBalance($ciniki, $tnid, $existing_item['order_id']);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }
    if( isset($rc['order']) ) {
        $order = $rc['order'];
        //
        // Update the flag to mail the order to the customer
        //
        if( ($order['flags']&0x10) == 0 ) {
            $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.poma.order', $existing_item['order_id'], array('flags'=>$order['flags'] |= 0x10), 0x04);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                return $rc;
            }
        }
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
    $rc = ciniki_poma_web_orderLoad($ciniki, $settings, $tnid, array('order_id'=>$existing_item['order_id']));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $order = $rc['order']; 

    return array('stat'=>'ok', 'order'=>$order);
}
?>
