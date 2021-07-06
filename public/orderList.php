<?php
//
// Description
// -----------
// This method will return the list of Orders for a tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Order for.
//
// Returns
// -------
//
function ciniki_poma_orderList($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'year'=>array('required'=>'yes', 'blank'=>'yes', 'name'=>'Year'),
        'month'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Month'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.orderList');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load the date format strings for the user
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');
        
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
    // Get the list of years
    //
    $strsql = "SELECT DISTINCT YEAR(order_date) AS year "
        . "FROM ciniki_poma_orders "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "ORDER BY year "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQueryList');
    $rc = ciniki_core_dbQueryList($ciniki, $strsql, 'ciniki.poma', 'years', 'year');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.245', 'msg'=>'Unable to load the years.', 'err'=>$rc['err']));
    }
    $years = isset($rc['years']) ? $rc['years'] : array();

    if( !isset($args['year']) || $args['year'] == '' || !in_array($args['year'], $years) ) {
        $args['year'] = end($years);
    }

    //
    // Get the list of orders
    //
    $strsql = "SELECT orders.id, "
        . "orders.order_number, "
        . "orders.customer_id, "
        . "orders.date_id, "
        . "orders.order_date, "
        . "orders.pickup_time, "
        . "orders.status, "
        . "orders.status AS status_text, "
        . "orders.payment_status, "
        . "orders.flags, "
        . "orders.billing_name, "
        . "orders.subtotal_amount, "
        . "orders.subtotal_discount_amount, "
        . "orders.subtotal_discount_percentage, "
        . "orders.discount_amount, "
        . "orders.total_amount, "
        . "orders.total_savings, "
        . "orders.paid_amount, "
        . "orders.balance_amount "
        . "FROM ciniki_poma_orders AS orders "
        . "WHERE orders.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND YEAR(orders.order_date) = '" . ciniki_core_dbQuote($ciniki, $args['year']) . "' "
        . "";
    if( isset($args['month']) && $args['month'] != '' && $args['month'] > 0 ) {
        $strsql .= "AND MONTH(orders.order_date) = '" . ciniki_core_dbQuote($ciniki, $args['month']) . "' ";
    }
    $strsql .= "ORDER BY order_date "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'orders', 'fname'=>'id', 
            'fields'=>array('id', 'order_number', 'customer_id', 'date_id', 'order_date', 
                'pickup_time', 'status', 'status_text', 'payment_status', 'flags', 'billing_name', 
                'subtotal_amount', 'subtotal_discount_amount', 'subtotal_discount_percentage', 'discount_amount', 
                'total_amount', 'total_savings', 'paid_amount', 'balance_amount',
                ),
            'maps'=>array('status_text'=>$maps['order']['status']),
            'naprices'=>array('subtotal_amount', 'total_amount', 'paid_amount', 'balance_amount'),
            'dtformat'=>array('order_date'=>$date_format),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['orders']) ) {
        $orders = $rc['orders'];
        $order_ids = array();
        foreach($orders as $iid => $order) {
            $order_ids[] = $order['id'];
        }
    } else {
        $orders = array();
        $order_ids = array();
    }

    return array('stat'=>'ok', 'orders'=>$orders, 'year'=>$args['year'], 'years'=>$years, 'nplist'=>$order_ids);
}
?>
