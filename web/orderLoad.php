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
function ciniki_poma_web_orderLoad(&$ciniki, $settings, $business_id, $args) {

    //
    // Check to make sure the module is enabled
    //
    if( !isset($ciniki['business']['modules']['ciniki.poma']) ) {
        return array('stat'=>'ok');
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
    // Get the order information
    //
    $strsql = "SELECT ciniki_poma_orders.id, "
        . "ciniki_poma_orders.status, "
        . "ciniki_poma_orders.payment_status, "
        . "ciniki_poma_orders.date_id, "
        . "ciniki_poma_orders.order_date, "
        . "ciniki_poma_orders.subtotal_amount, "
        . "ciniki_poma_orders.total_amount, "
        . "ciniki_poma_orders.customer_notes, "
        . "ciniki_poma_orders.order_notes "
        . "FROM ciniki_poma_orders "
        . "WHERE ciniki_poma_orders.customer_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['customer']['id']) . "' "
        . "AND ciniki_poma_orders.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "";
    if( isset($args['date_id']) && $args['date_id'] > 0 ) {
        $strsql .= "AND ciniki_poma_orders.date_id = '" . ciniki_core_dbQuote($ciniki, $args['date_id']) . "' ";
    } elseif( isset($args['order_id']) && $args['order_id'] > 0 ) {
        $strsql .= "AND ciniki_poma_orders.id = '" . ciniki_core_dbQuote($ciniki, $args['order_id']) . "' ";
    } else {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.49', 'msg'=>"No order specified."));
    }
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    } 
    if( !isset($rc['order']) ) {
        //
        // If a date was specified, setup a default order
        //
        if( isset($args['date_id']) && $args['date_id'] > 0 ) {
            $strsql = "SELECT ciniki_poma_order_dates.id, "
                . "ciniki_poma_order_dates.status, "
                . "ciniki_poma_order_dates.order_date, "
                . "ciniki_poma_order_dates.display_name "
                . "FROM ciniki_poma_order_dates "
                . "WHERE ciniki_poma_order_dates.id = '" . ciniki_core_dbQuote($ciniki, $args['date_id']) . "' "
                . "AND ciniki_poma_order_dates.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.51', 'msg'=>"Oops, we seem to have trouble loading your order. Please try again or contact us for help."));
            }
            if( !isset($rc['date']) ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.52', 'msg'=>"Oops, we seem to have trouble loading your order. Please try again or contact us for help."));
            }
            $odate = $rc['date'];
            $order = array(
                'id'=>0,
                'order_number'=>'New Order',
                'order_date'=>$odate['order_date'],
                'order_date_status'=>$odate['status'],
                'status'=>10,
                'payment_status'=>0,
                'flags'=>0,
                'subtotal_amount'=>0,
                'subtotal_discount_amount'=>0,
                'subtotal_discount_percentage'=>0,
                'total_amount'=>0,
                'total_savings'=>0,
                'paid_amount'=>0,
                'balance_amount'=>0,
                'customer_notes'=>'',
                'order_notes'=>'',
                'items'=>array(),
                );
        } else {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.50', 'msg'=>"Oops, we seem to have trouble loading your order. Please try again or contact us for help."));
        }
    } else {
        $order = $rc['order'];
        //
        // Get the order date
        //
        if( $order['date_id'] > 0 ) {
            $strsql = "SELECT ciniki_poma_order_dates.id, "
                . "ciniki_poma_order_dates.status, "
                . "ciniki_poma_order_dates.order_date, "
                . "ciniki_poma_order_dates.display_name "
                . "FROM ciniki_poma_order_dates "
                . "WHERE ciniki_poma_order_dates.id = '" . ciniki_core_dbQuote($ciniki, $order['date_id']) . "' "
                . "AND ciniki_poma_order_dates.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.51', 'msg'=>"Oops, we seem to have trouble loading your order. Please try again or contact us for help."));
            }
            if( !isset($rc['date']) ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.52', 'msg'=>"Oops, we seem to have trouble loading your order. Please try again or contact us for help."));
            }
            $odate = $rc['date'];
            $order['order_date_status'] = $odate['status'];
        }

        //
        // FIXME: Add query to get taxes
        //
    }
    $dt = new DateTime($order['order_date'] . ' 12:00:00', new DateTimezone($intl_timezone));
    $order['order_date_text'] = $dt->format('M j, Y');
    $order['total_text'] = '$' . number_format($order['total_amount'], 2, '.', ',');
    
    //
    // Check if order can be edited by customer
    //
    if( $order['status'] == 10 ) {
        $order['editable'] = 'yes';
    }

    //
    // Get any order items for the date
    //
    $strsql = "SELECT "
        . "ciniki_poma_order_items.id, "
        . "ciniki_poma_order_items.parent_id, "
        . "ciniki_poma_order_items.line_number, "
        . "ciniki_poma_order_items.object, "
        . "ciniki_poma_order_items.object_id, "
        . "ciniki_poma_order_items.code, "
        . "ciniki_poma_order_items.description, "
        . "ciniki_poma_order_items.flags, "
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
        . "ciniki_poma_order_items.taxtype_id "
        . "FROM ciniki_poma_order_items "
        . "WHERE ciniki_poma_order_items.order_id = '" . ciniki_core_dbQuote($ciniki, $order['id']) . "' "
        . "AND ciniki_poma_order_items.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
//        . "AND ciniki_poma_order_items.parent_id = 0 "   // Don't load child items, they are only used for product baskets in foodmarket
        . "ORDER BY line_number, parent_id, description "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('id', 'parent_id', 'line_number', 'object', 'object_id', 'code', 'description', 
                'flags', 'itype', 'weight_units', 'weight_quantity', 'unit_quantity', 'unit_suffix',
                'unit_amount', 'unit_discount_amount', 'unit_discount_percentage', 'subtotal_amount', 'discount_amount', 'total_amount', 'taxtype_id')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['items']) ) {
        $order['items'] = $rc['items'];
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'orderItemFormat');
        foreach($order['items'] as $iid => $item) {
            $rc = ciniki_poma_web_orderItemFormat($ciniki, $settings, $business_id, $item);
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            $order['items'][$iid] = $rc['item'];

            if( ($order['items'][$iid]['flags']&0x02) && $order['order_date_status'] == 30 ) {
                $order['items'][$iid]['substitutions'] = 'yes';
            }
            if( isset($item['parent_id']) && $item['parent_id'] > 0 && isset($order['items'][$item['parent_id']]) ) {
                if( !isset($order['items'][$item['parent_id']]['subitems']) ) {
                    $order['items'][$item['parent_id']]['subitems'] = array();
                }
                $order['items'][$item['parent_id']]['subitems'][$iid] = $order['items'][$iid];
                unset($order['items'][$iid]);
            }
        }
    } else {
        $order['items'] = array();
    }

    return array('stat'=>'ok', 'order'=>$order);
}
?>
