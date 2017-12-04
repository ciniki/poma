<?php
//
// Description
// -----------
// This function will apply repeats for an order date.
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
function ciniki_poma_dateRepeatsAdd(&$ciniki, $tnid, $date_id) {

    //
    // The repeat items will also be added by the dateLock function from cron if missed here
    //

    //
    // Load the date
    //
    $strsql = "SELECT id, status, order_date, autolock_dt, flags "
        . "FROM ciniki_poma_order_dates "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $date_id) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND status < 20 "
        . "AND repeats_dt <= UTC_TIMESTAMP() "
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
    $repeat_customers = $rc['customers'];
    foreach($repeat_customers as $customer_id) {
        //
        // Apply the standing order items
        //
        error_log("Applying repeats for customer: $date_id <- $customer_id\n");
        $rc = ciniki_poma_orderRepeatItemsAdd($ciniki, $tnid, array(
            'date'=>$date,
            'date_id'=>$date_id,
            'customer_id'=>$customer_id,
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
    }

    //
    // Lock the date
    //
    $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.poma.orderdate', $date['id'], array('status'=>20), 0x04);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    return array('stat'=>'ok');
}
?>
