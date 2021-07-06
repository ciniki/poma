<?php
//
// Description
// -----------
// This method searchs for a Orders for a tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Order for.
// start_needle:       The search string to search for.
// limit:              The maximum number of entries to return.
//
// Returns
// -------
//
function ciniki_poma_orderSearch($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'start_needle'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Search String'),
        'limit'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Limit'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.orderSearch');
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
        . "AND ("
            . "billing_name LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR billing_name LIKE '% " . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR order_number LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR order_number LIKE '% " . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . ") "
        . "ORDER BY order_date "
        . "";
    if( isset($args['limit']) && is_numeric($args['limit']) && $args['limit'] > 0 ) {
        $strsql .= "LIMIT " . ciniki_core_dbQuote($ciniki, $args['limit']) . " ";
    } else {
        $strsql .= "LIMIT 25 ";
    }
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'orders', 'fname'=>'id', 
            'fields'=>array('id', 'order_number', 'customer_id', 'date_id', 'order_date', 
                'pickup_time', 'status', 'status_text', 'payment_status', 'flags', 'billing_name', 
                'subtotal_amount', 'subtotal_discount_amount', 'subtotal_discount_percentage', 'discount_amount', 
                'total_amount', 'total_savings', 'paid_amount', 'balance_amount',
                ),
            'maps'=>array('status_text'=>$maps['order']['status']),
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

    return array('stat'=>'ok', 'orders'=>$orders, 'nplist'=>$order_ids);
}
?>
