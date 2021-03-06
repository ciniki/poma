<?php
//
// Description
// -----------
// This function will lock a dates for a tenant that autolock has been specified.
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure.
// tnid:     The ID of the tenant to get poma web options for.
//
// args:            The possible arguments for posts
//
//
// Returns
// -------
//
function ciniki_poma_dateLock(&$ciniki, $tnid, $date_id) {

    //
    // Load the date
    //
    $strsql = "SELECT id, status, order_date, autolock_dt, flags "
        . "FROM ciniki_poma_order_dates "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $date_id) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND status < 50 "
        . "AND autolock_dt <= UTC_TIMESTAMP() "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['date']) ) {
        return array('stat'=>'ok');
    }
    $date = $rc['date'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQueryList');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderRepeatItemsAdd');

    //
    // Get the list of repeat items for this date
    //
    $strsql = "SELECT DISTINCT customer_id "
        . "FROM ciniki_poma_customer_items "
        . "WHERE next_order_date <= '" . ciniki_core_dbQuote($ciniki, $date['order_date']) . "' "
        . "AND itype = 40 "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbQueryList($ciniki, $strsql, 'ciniki.poma', 'customers', 'customer_id');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['customers']) ) {
        $repeat_customers = $rc['customers'];
        foreach($repeat_customers as $customer_id) {
            //
            // Apply the standing order items
            //
            $rc = ciniki_poma_orderRepeatItemsAdd($ciniki, $tnid, array(
                'date'=>$date,
                'customer_id'=>$customer_id,
                ));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
        }
    }

    //
    // Get the list of orders for this date
    //
    $strsql = "SELECT id, customer_id "
        . "FROM ciniki_poma_orders "
        . "WHERE date_id = '" . ciniki_core_dbQuote($ciniki, $date_id) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND status < 30 "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['rows']) && count($rc['rows']) > 0 ) {
        $orders = $rc['rows'];

        foreach($orders as $order) {
            //
            // Close the order
            //
            $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.poma.orderitem', $order['id'], array('status'=>30), 0x04);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.124', 'msg'=>'Unable to lock order', 'err'=>$rc['err']));
            }
        }
    }

    //
    // Lock the date
    //
    $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.poma.orderdate', $date['id'], array('status'=>50), 0x04);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.103', 'msg'=>'Unable to lock date', 'err'=>$rc['err']));
    }

    return array('stat'=>'ok');
}
?>
