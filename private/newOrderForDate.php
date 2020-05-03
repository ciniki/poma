<?php
//
// Description
// -----------
// This function will add a new order for a date
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
function ciniki_poma_newOrderForDate(&$ciniki, $tnid, $args) {
    
    //
    // Check args
    //
    if( !isset($args['customer_id']) || $args['customer_id'] < 1 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.44', 'msg'=>'No customer specified.'));
    }
    if( !isset($args['date_id']) || $args['date_id'] < 1 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.45', 'msg'=>'No date specified.'));
    }

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
    // Load the customer details
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'hooks', 'customerDetails');
    $rc = ciniki_customers_hooks_customerDetails($ciniki, $tnid, array('customer_id'=>$args['customer_id']));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['customer']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.36', 'msg'=>'No customer found.'));
    }
    $customer = $rc['customer'];

    //
    // Get the current status of the order date_id
    //
    $strsql = "SELECT ciniki_poma_order_dates.id, "
        . "ciniki_poma_order_dates.order_date, "
        . "ciniki_poma_order_dates.status "
        . "FROM ciniki_poma_order_dates "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['date_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['date']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.46', 'msg'=>'No date specified.'));
    } 
    $odate = $rc['date'];

    //
    // Check the status of the date, unless override is specified
    //
    if( !isset($args['checkdate']) || $args['checkdate'] != 'no' ) {
        if( $odate['status'] > 30 ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.47', 'msg'=>'No more orders are being accepted for this date, please choose another other date.'));
        }
        $odt = new DateTime($odate['order_date'], new DateTimezone($intl_timezone));
        $dt = new DateTime('now', new DateTimezone($intl_timezone));
        if( $dt > $odt ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.48', 'msg'=>'No more orders are being accepted for this date, please choose another other date.'));
        }
    }

    //
    // Get the next order number
    //
    $strsql = "SELECT MAX(CAST(order_number AS UNSIGNED)) AS max_order_number "
        . "FROM ciniki_poma_orders "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'max');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['max']['max_order_number']) ) {   
        $order_number = $rc['max']['max_order_number'] + 1;
    } else {    
        $order_number = 1;
    }

    $pickup_time = '';
    if( isset($args['pickup_time']) && $args['pickup_time'] == 'last' ) {
        //
        // Load all pickup times taken for this date
        //
        $strsql = "SELECT id, pickup_time " 
            . "FROM ciniki_poma_orders "
            . "WHERE date_id = '" . ciniki_core_dbQuote($ciniki, $args['date_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
        $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'times', 'fname'=>'pickup_time', 'fields'=>array('pickup_time')),
            array('container'=>'orders', 'fname'=>'id', 'fields'=>array('id')),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.235', 'msg'=>'Unable to load pickup times', 'err'=>$rc['err']));
        }
        $picked_times = isset($rc['times']) ? $rc['times'] : array();

        //
        // Get the last weeks order pickup time
        //
        $prev_odt = new DateTime($odate['order_date'], new DateTimezone($intl_timezone));
        $prev_odt->sub(new DateInterval('P8D'));
        $strsql = "SELECT order_date, pickup_time "
            . "FROM ciniki_poma_orders "
            . "WHERE customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
            . "AND order_date < '" . ciniki_core_dbQuote($ciniki, $odate['order_date']) . "' "
            . "AND order_date >= '" . ciniki_core_dbQuote($ciniki, $prev_odt->format('Y-m-d')) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "ORDER BY order_date DESC "
            . "LIMIT 1 "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.220', 'msg'=>'Unable to load order', 'err'=>$rc['err']));
        }
        if( isset($rc['order']) ) {
            $last_order = $rc['order'];
            
            if( $last_order['pickup_time'] != '' && !isset($picked_times[$last_order['pickup_time']]) ) {
                $pickup_time = $last_order['pickup_time'];
            }
        }
    }

    //
    // Add the order
    //
    $order = array(
        'order_number'=>$order_number,
        'customer_id'=>$args['customer_id'],
        'billing_name'=>$customer['display_name'],
        'date_id'=>$args['date_id'],
        'order_date'=>$odate['order_date'],
        'status'=>10,
        'payment_status'=>0,
        'flags'=>0,
        'items'=>array(),
        'pickup_time'=>$pickup_time,
        );
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.poma.order', $order, 0x07);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $order['id'] = $rc['id'];

    return array('stat'=>'ok', 'order'=>$order);
}
?>
