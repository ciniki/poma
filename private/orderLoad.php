<?php
//
// Description
// -----------
// This function loads the order for a customer.
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure.
// business_id:     The ID of the business to get poma web options for.
//
// args:            The possible arguments for posts
//
//
// Returns
// -------
//
function ciniki_poma_orderLoad(&$ciniki, $business_id, $order_id) {

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
    // Load maps
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'maps');
    $rc = ciniki_poma_maps($ciniki, $business_id);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $maps = $rc['maps'];

    //
    // Get the order information
    //
    $strsql = "SELECT ciniki_poma_orders.id, "
        . "ciniki_poma_orders.order_number, "
        . "ciniki_poma_orders.customer_id, "
        . "ciniki_poma_orders.status, "
        . "ciniki_poma_orders.payment_status, "
        . "ciniki_poma_orders.order_date, "
        . "ciniki_poma_orders.billing_name, "
        . "ciniki_poma_orders.subtotal_amount, "
        . "ciniki_poma_orders.subtotal_discount_amount, "
        . "ciniki_poma_orders.subtotal_discount_percentage, "
        . "ciniki_poma_orders.discount_amount, "
        . "ciniki_poma_orders.total_amount, "
        . "ciniki_poma_orders.total_savings, "
        . "ciniki_poma_orders.paid_amount, "
        . "ciniki_poma_orders.balance_amount, "
        . "ciniki_poma_orders.customer_notes, "
        . "ciniki_poma_orders.order_notes "
        . "FROM ciniki_poma_orders "
        . "WHERE ciniki_poma_orders.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "AND ciniki_poma_orders.id = '" . ciniki_core_dbQuote($ciniki, $order_id) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    } 
    if( !isset($rc['order']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.53', 'msg'=>"Invalid order."));
    } else {
        $order = $rc['order'];
        if( isset($maps['order']['payment_status'][$order['payment_status']]) ) {
            $order['payment_status_text'] = $maps['order']['payment_status'][$order['payment_status']];
        }

        //
        // FIXME: Add query to get taxes
        //
    }
    $dt = new DateTime($order['order_date'] . ' 12:00:00', new DateTimezone($intl_timezone));
    $order['order_date_text'] = $dt->format('M j, Y');
    
    //
    // Get any order items for the date
    //
    $strsql = "SELECT "
        . "ciniki_poma_order_items.id, "
        . "ciniki_poma_order_items.uuid, "
        . "ciniki_poma_order_items.line_number, "
        . "ciniki_poma_order_items.flags, "
        . "ciniki_poma_order_items.object, "
        . "ciniki_poma_order_items.object_id, "
        . "ciniki_poma_order_items.code, "
        . "ciniki_poma_order_items.description, "
        . "ciniki_poma_order_items.itype, "
        . "ciniki_poma_order_items.weight_units, "
        . "ciniki_poma_order_items.weight_quantity, "
        . "ciniki_poma_order_items.unit_quantity, "
        . "ciniki_poma_order_items.unit_suffix, "
        . "ciniki_poma_order_items.unit_amount, "
        . "ciniki_poma_order_items.unit_discount_amount, "
        . "ciniki_poma_order_items.unit_discount_percentage, "
        . "ciniki_poma_order_items.cdeposit_description, "
        . "ciniki_poma_order_items.cdeposit_amount, "
        . "ciniki_poma_order_items.subtotal_amount, "
        . "ciniki_poma_order_items.discount_amount, "
        . "ciniki_poma_order_items.total_amount, "
        . "ciniki_poma_order_items.taxtype_id, "
        . "ciniki_poma_order_items.notes "
        . "FROM ciniki_poma_order_items "
        . "WHERE ciniki_poma_order_items.order_id = '" . ciniki_core_dbQuote($ciniki, $order['id']) . "' "
        . "AND ciniki_poma_order_items.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "AND ciniki_poma_order_items.parent_id = 0 "   // Don't load child items, they are only used for product baskets in foodmarket
        . "ORDER BY line_number "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('id', 'line_number', 'flags', 'object', 'object_id', 'code', 'description', 
                'itype', 'weight_units', 'weight_quantity', 'unit_quantity', 'unit_suffix',
                'unit_amount', 'unit_discount_amount', 'unit_discount_percentage', 'cdeposit_description', 'cdeposit_amount',
                'subtotal_amount', 'discount_amount', 'total_amount', 'taxtype_id', 'notes')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['items']) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'formatItems');
        $rc = ciniki_poma_formatItems($ciniki, $business_id, $rc['items']);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $order['items'] = $rc['items'];
    } else {
        $order['items'] = array();
    }

    //
    // Get any order subitems for the order
    //
    $strsql = "SELECT "
        . "ciniki_poma_order_items.id, "
        . "ciniki_poma_order_items.uuid, "
        . "ciniki_poma_order_items.parent_id, "
        . "ciniki_poma_order_items.line_number, "
        . "ciniki_poma_order_items.flags, "
        . "ciniki_poma_order_items.object, "
        . "ciniki_poma_order_items.object_id, "
        . "ciniki_poma_order_items.code, "
        . "ciniki_poma_order_items.description, "
        . "ciniki_poma_order_items.itype, "
        . "ciniki_poma_order_items.weight_units, "
        . "ciniki_poma_order_items.weight_quantity, "
        . "ciniki_poma_order_items.unit_quantity, "
        . "ciniki_poma_order_items.unit_suffix, "
        . "ciniki_poma_order_items.unit_amount, "
        . "ciniki_poma_order_items.unit_discount_amount, "
        . "ciniki_poma_order_items.unit_discount_percentage, "
        . "ciniki_poma_order_items.subtotal_amount, "
        . "ciniki_poma_order_items.discount_amount, "
        . "ciniki_poma_order_items.total_amount, "
        . "ciniki_poma_order_items.taxtype_id, "
        . "ciniki_poma_order_items.notes "
        . "FROM ciniki_poma_order_items "
        . "WHERE ciniki_poma_order_items.order_id = '" . ciniki_core_dbQuote($ciniki, $order['id']) . "' "
        . "AND ciniki_poma_order_items.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "AND ciniki_poma_order_items.parent_id > 0 "   // Don't load child items, they are only used for product baskets in foodmarket
        . "ORDER BY line_number "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'parents', 'fname'=>'parent_id', 'fields'=>array('id'=>'parent_id')),
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('id', 'line_number', 'flags', 'object', 'object_id', 'code', 'description', 
                'itype', 'weight_units', 'weight_quantity', 'unit_quantity', 'unit_suffix',
                'unit_amount', 'unit_discount_amount', 'unit_discount_percentage', 'subtotal_amount', 'discount_amount', 'total_amount', 'taxtype_id', 'notes')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['parents']) ) {
        foreach($order['items'] as $iid => $item) {
            if( isset($rc['parents'][$item['id']]['items']) ) {
                $order['items'][$iid]['subitems'] = $rc['parents'][$item['id']]['items'];
            }
        }
    }

    //
    // FIXME: Load the taxes
    //

    //
    // Setup the order tallies
    //
    $order['tallies'] = array();
    $order['tallies'][] = array('label'=>'Sub Total', 'value'=>'$' . number_format($order['subtotal_amount'], 2));
    if( isset($order['taxes']) && count($order['taxes']) > 0 ) {
        foreach($order['taxes'] as $tax) {
            $order['tallies'][] = array('label'=>$tax['description'], 'value'=>'$' . number_format($tax['amount'], 2));
        }
    }
    $order['tallies'][] = array('label'=>'Total', 'value'=>'$' . number_format($order['total_amount'], 2));
    if( $order['total_savings'] > 0 ) {
        $order['tallies'][] = array('label'=>'Savings', 'value'=>'$' . number_format($order['total_savings'], 2));
    }

    //
    // Load any payments for this order
    //
    $strsql = "SELECT ciniki_poma_order_payments.id, "
        . "ciniki_poma_order_payments.payment_type, "
        . "ciniki_poma_order_payments.amount, "
        . "IFNULL(ciniki_poma_customer_ledgers.description, '') AS description "
        . "FROM ciniki_poma_order_payments "
        . "LEFT JOIN ciniki_poma_customer_ledgers ON ("
            . "ciniki_poma_order_payments.ledger_id = ciniki_poma_customer_ledgers.id "
            . "AND ciniki_poma_customer_ledgers.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
            . ") "
        . "WHERE ciniki_poma_order_payments.order_id = '" . ciniki_core_dbQuote($ciniki, $order['id']) . "' "
        . "AND ciniki_poma_order_payments.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "";
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'payments', 'fname'=>'id', 'fields'=>array('id', 'payment_type', 'amount', 'description')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $order['payments'] = array();
    if( isset($rc['payments']) ) {
        foreach($rc['payments'] as $payment) {
            $order['payments'][] = array('label'=>$payment['description'], 'value'=>'-$' . number_format($payment['amount'], 2));
        }
    }
    if( $order['total_amount'] != $order['balance_amount'] || $order['payment_status'] > 0 ) {
        $order['payments'][] = array('label'=>'Balance', 
            'status'=>($order['balance_amount'] > 0 ? ($order['balance_amount'] == $order['total_amount'] ? 'red' : 'orange') : 'green'),
            'value'=>'$' . number_format($order['balance_amount'], 2));
    }

    return array('stat'=>'ok', 'order'=>$order);
}
?>
