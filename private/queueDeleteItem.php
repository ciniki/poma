<?php
//
// Description
// -----------
// This function will add the order item for a queued item when it came in.
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_poma_queueDeleteItem(&$ciniki, $tnid, $item_id) {

    //
    // Load tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    //
    // Get the details of the item
    //
    $strsql = "SELECT items.id, "
        . "items.uuid, "
        . "items.customer_id, "
        . "items.status, "
        . "items.quantity, "
        . "items.object, "
        . "items.object_id, "
        . "items.description "
        . "FROM ciniki_poma_queued_items AS items "
        . "WHERE items.id = '" . ciniki_core_dbQuote($ciniki, $item_id) . "' "
        . "AND items.status < 40 "
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['item']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.231', 'msg'=>'Could not find the queue item'));
    }
    $qitem = $rc['item'];

    //
    // Get the details for the item
    //
/*    list($pkg, $mod, $obj) = explode('.', $qitem['object']);
    $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'queueItemLookup');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.232', 'msg'=>'Unable to add item to queue.'));
    }
    $fn = $rc['function_call'];
    $rc = $fn($ciniki, $tnid, array(
        'object'=>$qitem['object'],
        'object_id'=>$qitem['object_id'],
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['item']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.233', 'msg'=>'Unable to add item to queue.'));
    }
    $item = $rc['item']; */

    //
    // Start a transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'queueDepositAdd');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderUpdateStatusBalance');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.poma');
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }

    //
    // Get the deposits for this item
    //
    $strsql = "SELECT items.id, "
        . "items.uuid, "
        . "items.object, "
        . "items.object_id, "
        . "items.order_id, "
        . "items.flags, "
        . "items.unit_amount, "
        . "items.unit_quantity, "
        . "items.total_amount, "
        . "orders.flags AS order_flags, "
        . "orders.payment_status "
        . "FROM ciniki_poma_orders AS orders, ciniki_poma_order_items AS items "
        . "WHERE orders.customer_id = '" . ciniki_core_dbQuote($ciniki, $qitem['customer_id']) . "' "
        . "AND orders.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND orders.id = items.order_id "
        . "AND items.object = 'ciniki.poma.queueditem' "
        . "AND items.object_id = '" . ciniki_core_dbQuote($ciniki, $item_id) . "' "
        . "AND (items.flags&0x40) = 0x40 "
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }
    $deposited_amount = 0;
    if( isset($rc['rows']) ) {
        $deposits = $rc['rows'];
        foreach($deposits as $deposit) {
            //
            // Check if any deposits are unpaid, then remove deposit from invoice
            //
            if( $deposit['payment_status'] < 50 ) {
                $rc = ciniki_core_objectDelete($ciniki, $tnid, 'ciniki.poma.orderitem', $deposit['id'], $deposit['uuid'], 0x04);
                if( $rc['stat'] != 'ok' ) {
                    ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                    return $rc;
                }

                //
                // Update the order totals
                //
                $rc = ciniki_poma_orderUpdateStatusBalance($ciniki, $tnid, $deposit['order_id']);
                if( $rc['stat'] != 'ok' ) {
                    ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                    return $rc;
                }
            } else {
                // FIXME: Figure out how to deal with deposits
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.224', 'msg'=>'Item has deposit, cannot be removed.', 'err'=>$rc['err']));
//                $deposited_amount = bcadd($deposited_amount, $deposit['total_amount'], 6);
            }
        }
    }

    //
    // Delete the queued item
    //
    $rc = ciniki_core_objectDelete($ciniki, $tnid, 'ciniki.poma.queueditem', $item_id, null, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }

    //
    // Commit the transaction
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.poma');
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }

    return array('stat'=>'ok');
}
?>
