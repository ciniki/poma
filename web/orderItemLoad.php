<?php
//
// Description
// -----------
// This function loads the item and subitems for an order to do substitutions
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
function ciniki_poma_web_orderItemLoad($ciniki, $settings, $business_id, $args) {
    if( !isset($args['item_id']) || $args['item_id'] < 1 || $args['item_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.83', 'msg'=>'No item specified.'));
    }
    //
    // Load the detail for the item in the order
    //
    $strsql = "SELECT ciniki_poma_order_items.id, "
        . "ciniki_poma_order_items.uuid, "
        . "ciniki_poma_order_items.order_id, "
        . "ciniki_poma_order_items.object, "
        . "ciniki_poma_order_items.object_id, "
        . "ciniki_poma_order_items.flags, "
        . "ciniki_poma_order_items.itype, "
        . "ciniki_poma_order_items.weight_quantity, "
        . "ciniki_poma_order_items.unit_quantity, "
        . "ciniki_poma_order_items.total_amount "
        . "FROM ciniki_poma_order_items "
        . "WHERE ciniki_poma_order_items.id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
        . "AND ciniki_poma_order_items.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['item']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.76', 'msg'=>'Invalid order item.'));
    }
    $item = $rc['item'];
    $item['subitems'] = array();

    //
    // Check the order belongs to the logged in customer
    //
    $strsql = "SELECT ciniki_poma_orders.id, "
        . "ciniki_poma_orders.date_id, "
        . "ciniki_poma_orders.status, "
        . "ciniki_poma_orders.customer_id, "
        . "ciniki_poma_order_dates.status AS date_status "
        . "FROM ciniki_poma_orders, ciniki_poma_order_dates "
        . "WHERE ciniki_poma_orders.id = '" . ciniki_core_dbQuote($ciniki, $item['order_id']) . "' "
        . "AND ciniki_poma_orders.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "AND ciniki_poma_orders.date_id = ciniki_poma_order_dates.id "
        . "AND ciniki_poma_order_dates.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['order']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.79', 'msg'=>'Invalid order item.'));
    }
    if( $rc['order']['customer_id'] != $ciniki['session']['customer']['id'] ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.80', 'msg'=>'Permission denied.'));
    }
    if( $rc['order']['status'] > 30 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.81', 'msg'=>'Order is closed, no more changes allowed.'));
    }
    if( $rc['order']['status'] > 30 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.82', 'msg'=>'Order is closed, no more changes allowed.'));
    }
    $order = $rc['order'];
    $item['order_date_id'] = $order['date_id'];

    //
    // Load the subitems
    //
    $strsql = "SELECT ciniki_poma_order_items.id, "
        . "ciniki_poma_order_items.uuid, "
        . "ciniki_poma_order_items.description, "
        . "ciniki_poma_order_items.flags, "
        . "ciniki_poma_order_items.object, "
        . "ciniki_poma_order_items.object_id, "
        . "ciniki_poma_order_items.itype, "
        . "ciniki_poma_order_items.weight_units, "
        . "ciniki_poma_order_items.weight_quantity, "
        . "ciniki_poma_order_items.unit_quantity, "
        . "ciniki_poma_order_items.unit_suffix, "
        . "ciniki_poma_order_items.unit_amount, "
        . "ciniki_poma_order_items.total_amount "
        . "FROM ciniki_poma_order_items "
        . "WHERE ciniki_poma_order_items.parent_id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
        . "AND ciniki_poma_order_items.order_id = '" . ciniki_core_dbQuote($ciniki, $item['order_id']) . "' "
        . "AND ciniki_poma_order_items.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "ORDER BY ciniki_poma_order_items.description "
        . "";
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'subitems', 'fname'=>'id', 'fields'=>array('id', 'uuid', 'description', 'flags', 'object', 'object_id', 
            'itype', 'weight_units', 'weight_quantity', 'unit_quantity', 'unit_suffix', 'unit_amount', 'total_amount')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['subitems']) ) {
        $subitems = array();
    } else {
        $subitems = $rc['subitems'];
    }

    //
    // Get the available amount remaining
    //
    $item['curtotal'] = 0;
//    $item['limit'] = bcadd($item['total_amount'], bcmul($item['total_amount'], 0.05, 2), 2);
    $item['limit'] = bcadd($item['total_amount'], 1, 2);
    foreach($subitems as $iid => $itm) {
        if( $itm['itype'] == 10 ) {
            $item['curtotal'] = bcadd($item['curtotal'], bcmul($itm['unit_amount'], $itm['weight_quantity'], 6), 2);
            $subitems[$iid]['quantity'] = $itm['weight_quantity'];
        } else {
            $item['curtotal'] = bcadd($item['curtotal'], bcmul($itm['unit_amount'], $itm['unit_quantity'], 6), 2);
            $subitems[$iid]['quantity'] = $itm['unit_quantity'];
        }
    }
    $item['available'] = bcsub($item['limit'], $item['curtotal'], 2);

    return array('stat'=>'ok', 'item'=>$item, 'subitems'=>$subitems);
}
?>
