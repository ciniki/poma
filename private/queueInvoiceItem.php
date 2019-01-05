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
function ciniki_poma_queueInvoiceItem(&$ciniki, $tnid, $item_id) {

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
        . "AND items.status = 40 "
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['item']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.167', 'msg'=>'Could not find the queue item'));
    }
    $qitem = $rc['item'];

    //
    // Get the details for the item
    //
    list($pkg, $mod, $obj) = explode('.', $qitem['object']);
    $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'queueItemLookup');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.168', 'msg'=>'Unable to add item to queue.'));
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
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.169', 'msg'=>'Unable to add item to queue.'));
    }
    $item = $rc['item'];

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
                $deposited_amount = bcadd($deposited_amount, $deposit['total_amount'], 6);
            }
        }
    }

    //
    // Look for an open order today or next 7 days
    //
    $sdt = new DateTime('now', new DateTimezone($intl_timezone));
    $edt = clone $sdt;
    $edt->add(new DateInterval('P13D'));
    $strsql = "SELECT dates.id, "
        . "DATE_FORMAT(dates.order_date, '%Y-%m-%d') AS order_date, "
        . "IFNULL(orders.id, 0) AS order_id, "
        . "IFNULL(orders.flags, 0) AS order_flags, "
        . "IFNULL(orders.status, 0) AS order_status, "
        . "IFNULL(orders.payment_status, 0) AS payment_status "
        . "FROM ciniki_poma_order_dates AS dates "
        . "LEFT JOIN ciniki_poma_orders AS orders ON ("
            . "dates.id = orders.date_id "
            . "AND orders.customer_id = '" . ciniki_core_dbQuote($ciniki, $qitem['customer_id']) . "' "
            . "AND orders.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE dates.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND dates.order_date >= '" . ciniki_core_dbQuote($ciniki, $sdt->format('Y-m-d')) . "' "
        . "AND dates.order_date < '" . ciniki_core_dbQuote($ciniki, $edt->format('Y-m-d')) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }
    $order_id = 0;
    $date_id = 0;
    $order_flags = 0;
    if( isset($rc['rows']) ) {
        foreach($rc['rows'] as $date) {
            if( $date_id == 0 ) {
                $date_id = $date['id'];
            }
            if( isset($date['order_id']) && $date['order_id'] > 0 && $date['order_status'] <= 10 && $date['payment_status'] < 50 ) {
                $order_id = $date['order_id'];
                $order_flags = $date['order_flags'];
                break;
            }
        }
    }

    //
    // No order or date then cannot add deposit
    //
    if( $order_id == 0 && $date_id == 0 ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.170', 'msg'=>'No order date available for invoice'));
    }

    //
    // Add new order, or get the last line number of existing order
    //
    $max_line_number = 0;
    if( $order_id == 0 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'newOrderForDate');
        $rc = ciniki_poma_newOrderForDate($ciniki, $tnid, array(
            'checkdate'=>'no',
            'customer_id'=>$qitem['customer_id'],
            'date_id'=>$date_id,
            ));
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
            return $rc;
        }
        $order_id = $rc['order']['id'];
        $order_flags = 0;
    } else {
        //
        // Get the max line number for order
        //
        $strsql = "SELECT MAX(line_number) AS line_number "
            . "FROM ciniki_poma_order_items "
            . "WHERE order_id = '" . ciniki_core_dbQuote($ciniki, $order_id) . "' "
            . "AND parent_id = 0 "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
            return $rc;
        }
        if( isset($rc['order']['line_number']) ) {
            $max_line_number = $rc['order']['line_number'];
        }
    }
 
    //
    // Add the item to the order
    //
    if( $order_id > 0 ) {
        $item['order_id'] = $order_id;
        $item['line_number'] = $max_line_number + 1;
        $item['flags'] = 0x20;
        $item['deposited_amount'] = $deposited_amount;
        if( $item['itype'] == 10 ) {
            $item['weight_quantity'] = $qitem['quantity'];
            $item['unit_quantity'] = 0;
        } else {
            $item['weight_quantity'] = 0;
            $item['unit_quantity'] = $qitem['quantity'];
        }
        $item['object'] = 'ciniki.poma.queueditem';
        $item['object_id'] = $qitem['id'];
        $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.poma.orderitem', $item, 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
            return $rc;
        }
    } 

    //
    // Update the order totals
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderUpdateStatusBalance');
    $rc = ciniki_poma_orderUpdateStatusBalance($ciniki, $tnid, $order_id);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }

    //
    // Update the status of the queue item
    //
    $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.poma.queueditem', $item_id, array('status'=>90), 0x04);
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
