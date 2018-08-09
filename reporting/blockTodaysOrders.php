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
function ciniki_poma_reporting_blockTodaysOrders(&$ciniki, $tnid, $args) {
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
    ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'private', 'maps');
    $rc = ciniki_customers_maps($ciniki);
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
        . "orders.billing_name, "
        . "items.description "
        . "FROM ciniki_poma_orders AS orders "
        . "LEFT JOIN ciniki_poma_order_items AS items ON ("
            . "orders.id = items.order_id "
            . "AND items.parent_id = 0 "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE orders.order_date = '" . ciniki_core_dbQuote($ciniki, $dt->format('Y-m-d')) . "'"
        . "AND orders.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'orders', 'fname'=>'id', 
            'fields'=>array('id', 'order_number', 'billing_name', 'description'),
            'dlists'=>array('description'=>', '), 
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.206', 'msg'=>'Unable to get orders', 'err'=>$rc['err']));
    }
    $orders = isset($rc['orders']) ? $rc['orders'] : array();
    if( count($orders) == 0 ) {
        return array('stat'=>'ok');     // Return no chunks, creates empty report and no email
    }
    
    //
    // Create the report blocks
    //
    $chunk = array(
        'type'=>'table',
        'columns'=>array(
            array('label'=>'Name', 'pdfwidth'=>'30%', 'field'=>'billing_name'),
            array('label'=>'Items', 'pdfwidth'=>'70%', 'field'=>'description'),
            ),
        'data'=>$orders,
        'textlist'=>'',
        );

    $chunks[] = $chunk;
    
    return array('stat'=>'ok', 'chunks'=>$chunks);
}
?>
