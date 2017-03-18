<?php
//
// Description
// -----------
// This function will add an object to the queue
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_poma_queueDepositAdd(&$ciniki, $business_id, $args) {

    //
    // Load business settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'intlSettings');
    $rc = ciniki_businesses_intlSettings($ciniki, $business_id);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    //
    // Look for an open order today or next 7 days
    //
    $sdt = new DateTime('now', new DateTimezone($intl_timezone));
    $edt = clone $sdt;
    $edt->add(new DateInterval('P8D'));
    $strsql = "SELECT dates.id, "
        . "DATE_FORMAT(dates.order_date, '%Y-%m-%d') AS order_date, "
        . "IFNULL(orders.id, 0) AS order_id, "
        . "IFNULL(orders.flags, 0) AS order_flags, "
        . "IFNULL(orders.status, 0) AS order_status, "
        . "IFNULL(orders.payment_status, 0) AS payment_status "
        . "FROM ciniki_poma_order_dates AS dates "
        . "LEFT JOIN ciniki_poma_orders AS orders ON ("
            . "dates.id = orders.date_id "
            . "AND orders.customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
            . "AND orders.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
            . ") "
        . "WHERE dates.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "AND dates.order_date >= '" . ciniki_core_dbQuote($ciniki, $sdt->format('Y-m-d')) . "' "
        . "AND dates.order_date < '" . ciniki_core_dbQuote($ciniki, $edt->format('Y-m-d')) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $order_id = 0;
    $date_id = 0;
    $order_flags = 0;
    if( isset($rc['rows']) ) {
        foreach($rc['rows'] as $date) {
            if( $date_id == 0 && $date['order_date'] != $sdt->format('Y-m-d') ) {
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
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.160', 'msg'=>'No order date available for deposit'));
    }

    //
    // Add new order, or get the last line number of the existing order
    //
    $max_line_number = 0;
    if( $order_id == 0 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'newOrderForDate');
        $rc = ciniki_poma_newOrderForDate($ciniki, $business_id, array(
            'checkdate'=>'no',
            'customer_id'=>$args['customer_id'],
            'date_id'=>$date_id,
            ));
        if( $rc['stat'] != 'ok' ) {
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
            . "AND business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['order']['line_number']) ) {
            $max_line_number = $rc['order']['line_number'];
        }
    }
 
    //
    // Add the deposit
    //
    if( $order_id > 0 ) {
        $args['order_id'] = $order_id;
        $args['line_number'] = $max_line_number + 1;
        $args['flags'] = 0x40;
        $rc = ciniki_core_objectAdd($ciniki, $business_id, 'ciniki.poma.orderitem', $args, 0x04);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
    } 

    //
    // Update the order totals
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderUpdateStatusBalance');
    $rc = ciniki_poma_orderUpdateStatusBalance($ciniki, $business_id, $order_id);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }

    //
    // Update the flag to mail the order to the customer
    //
    if( ($order_flags&0x10) == 0 ) {
        $rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.poma.order', $order_id, array('flags'=>$order_flags |= 0x10), 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
            return $rc;
        }
    }

    return array('stat'=>'ok');
}
?>
