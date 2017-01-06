<?php
//
// Description
// -----------
// This function will add another modules object/objectid as a favourite.
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
function ciniki_poma_newOrderForDate(&$ciniki, $business_id, $args) {
    
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
    // Load business settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'intlSettings');
    $rc = ciniki_businesses_intlSettings($ciniki, $business_id);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    //
    // Load the customer details
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'hooks', 'customerDetails');
    $rc = ciniki_customers_hooks_customerDetails($ciniki, $business_id, array('customer_id'=>$args['customer_id']));
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
        . "AND business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['date']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.46', 'msg'=>'No date specified.'));
    } 
    $odate = $rc['date'];

    if( $odate['status'] != 10 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.47', 'msg'=>'No more orders are being accepted for this date, please choose another other date.'));
    }
    $odt = new DateTime($odate['order_date'], new DateTimezone($intl_timezone));
    $dt = new DateTime('now', new DateTimezone($intl_timezone));
    if( $dt > $odt ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.48', 'msg'=>'No more orders are being accepted for this date, please choose another other date.'));
    }

    //
    // Get the next order number
    //
    $strsql = "SELECT MAX(order_number) AS max_order_number "
        . "FROM ciniki_poma_orders "
        . "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
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
        );
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    $rc = ciniki_core_objectAdd($ciniki, $business_id, 'ciniki.poma.order', $order, 0x07);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $order['id'] = $rc['id'];

    return array('stat'=>'ok', 'order'=>$order);
}
?>
