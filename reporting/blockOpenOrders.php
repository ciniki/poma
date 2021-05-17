<?php
//
// Description
// -----------
// Return the report of upcoming certificate expirations
//
// Arguments
// ---------
// ciniki:
// tnid:         
// args:                The options for the query.
//
// Additional Arguments
// --------------------
// days:                The number of days forward to look for certificate expirations.
// 
// Returns
// -------
//
function ciniki_poma_reporting_blockOpenOrders(&$ciniki, $tnid, $args) {
    //
    // Get the tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteIDs');

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'mysql');

    //
    // Load maps
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'maps');
    $rc = ciniki_poma_maps($ciniki);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $maps = $rc['maps'];

    //
    // Store the report block chunks
    //
    $chunks = array();

    //
    // Get the date_id for today
    //
    $dt = new DateTime('now', new DateTimezone($intl_timezone));
    $strsql = "SELECT orders.id, "
        . "orders.order_number, "
        . "orders.date_id, "
        . "orders.customer_id, "
        . "DATE_FORMAT(orders.order_date, '%b %d, %Y') AS order_date, "
        . "orders.billing_name, "
        . "orders.balance_amount, "
        . "orders.status AS status_text, "
        . "orders.payment_status AS payment_status_text "
        . "FROM ciniki_poma_orders AS orders "
        . "WHERE orders.order_date < '" . ciniki_core_dbQuote($ciniki, $dt->format('Y-m-d')) . "'"
        . "AND orders.status < 70 "
        . "AND orders.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "ORDER BY orders.order_date DESC, order_number "
        . "LIMIT 500 "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'orders', 'fname'=>'id', 
            'fields'=>array('id', 'date_id', 'customer_id', 'order_number', 'order_date', 'billing_name', 'balance_amount', 
                'status_text', 'payment_status_text'),
            'maps'=>array(
                'status_text'=>$maps['order']['status'],
                'payment_status_text'=>$maps['order']['payment_status'],
                ),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.244', 'msg'=>'Unable to get orders', 'err'=>$rc['err']));
    }
    $orders = isset($rc['orders']) ? $rc['orders'] : array();
    if( count($orders) == 0 ) {
        return array('stat'=>'ok');     // Return no chunks, creates empty report and no email
    }
    
    //
    // Create the report blocks
    //
    foreach($orders as $oid => $order) {
        $orders[$oid]['balance_amount_display'] = '$' . number_format($order['balance_amount'], 2);
    }
    $chunk = array(
        'type'=>'table',
        'columns'=>array(
            array('label'=>'#', 'pdfwidth'=>'10%', 'field'=>'order_number'),
            array('label'=>'Date', 'pdfwidth'=>'20%', 'field'=>'order_date'),
            array('label'=>'Name', 'pdfwidth'=>'40%', 'field'=>'billing_name'),
            array('label'=>'Amount', 'pdfwidth'=>'15%', 'field'=>'balance_amount_display'),
            array('label'=>'Status', 'pdfwidth'=>'15%', 'field'=>'payment_status_text'),
            ),
        'data'=>$orders,
        'editApp'=>array('app'=>'ciniki.foodmarket.main', 'args'=>array('date_id'=>'d.date_id', 'order_id'=>'d.id', 'customer_id'=>'d.customer_id')),
        'textlist'=>'',
        );

    $chunks[] = $chunk;
    
    return array('stat'=>'ok', 'chunks'=>$chunks);
}
?>
