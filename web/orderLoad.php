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
// tnid:     The ID of the tenant to get poma web options for.
//
// args:            The possible arguments for posts
//
//
// Returns
// -------
//
function ciniki_poma_web_orderLoad(&$ciniki, $settings, $tnid, $args) {

    //
    // Check to make sure the module is enabled
    //
    if( !isset($ciniki['tenant']['modules']['ciniki.poma']) ) {
        return array('stat'=>'ok');
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
        . "AND ciniki_poma_orders.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
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
                . "AND ciniki_poma_order_dates.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
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
                . "AND ciniki_poma_order_dates.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.118', 'msg'=>"Oops, we seem to have trouble loading your order. Please try again or contact us for help."));
            }
            if( !isset($rc['date']) ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.123', 'msg'=>"Oops, we seem to have trouble loading your order. Please try again or contact us for help."));
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
        . "ciniki_poma_order_items.uuid, "
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
        . "ciniki_poma_order_items.cdeposit_description, "
        . "ciniki_poma_order_items.cdeposit_amount, "
        . "ciniki_poma_order_items.deposited_amount, "
        . "ciniki_poma_order_items.subtotal_amount, "
        . "ciniki_poma_order_items.discount_amount, "
        . "ciniki_poma_order_items.total_amount, "
        . "ciniki_poma_order_items.taxtype_id, "
        . "IFNULL(taxtypes.name, '') AS taxtype_name "
        . "FROM ciniki_poma_order_items "
        . "LEFT JOIN ciniki_tax_types AS taxtypes ON ("
            . "ciniki_poma_order_items.taxtype_id = taxtypes.id "
            . "AND taxtypes.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE ciniki_poma_order_items.order_id = '" . ciniki_core_dbQuote($ciniki, $order['id']) . "' "
        . "AND ciniki_poma_order_items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
//        . "AND ciniki_poma_order_items.parent_id = 0 "   // Don't load child items, they are only used for product baskets in foodmarket
        . "ORDER BY parent_id, line_number, description "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('id', 'parent_id', 'line_number', 'object', 'object_id', 'code', 'description', 
                'flags', 'itype', 'weight_units', 'weight_quantity', 'unit_quantity', 'unit_suffix', 'cdeposit_description', 'cdeposit_amount', 'deposited_amount',
                'unit_amount', 'unit_discount_amount', 'unit_discount_percentage', 'subtotal_amount', 'discount_amount', 'total_amount', 
                'taxtype_id', 'taxtype_name')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['items']) ) {
        $order['items'] = $rc['items'];
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'orderItemFormat');
        foreach($order['items'] as $iid => $item) {
/*            //
            // Check if limited quantity and lookup 
            //
            if( isset($item['flags']) && ($item['flags']&0x0800) == 0x0800 && $item['object'] != '') {
                list($pkg, $mod, $obj) = explode('.', $item['object']);
                $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'itemLookup');
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.185', 'msg'=>'Unable to find item.'));
                }
                $fn = $rc['function_call'];
                $rc = $fn($ciniki, $tnid, array('object'=>$item['object'], 'object_id'=>$item['object_id']));
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
                if( !isset($rc['item']) ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.195', 'msg'=>'Unable to find order item.'));
                }
                $o_item = $rc['item'];
                $item['inventory'] = $rc['item']['inventory'];
                $item['num_ordered'] = $rc['item']['num_ordered'];
                $item['num_available'] = $rc['item']['num_available'];
            } */

            $rc = ciniki_poma_web_orderItemFormat($ciniki, $settings, $tnid, $item);
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            $order['items'][$iid] = $rc['item'];

            if( ($order['items'][$iid]['flags']&0x02) == 0x02 && $order['order_date_status'] == 30 ) {
                $order['items'][$iid]['substitutions'] = 'yes';
            }
            if( $order['order_date_status'] < 50 ) {
                $order['items'][$iid]['modifications'] = 'yes';
            } else {
                $order['items'][$iid]['modifications'] = 'no';
            }
            if( ($order['items'][$iid]['flags']&0x20) == 0x20 ) {
                $order['items'][$iid]['modifications'] = 'no';
            }
            if( isset($item['parent_id']) && $item['parent_id'] > 0 && isset($order['items'][$item['parent_id']]) ) {
                if( !isset($order['items'][$item['parent_id']]['subitems']) ) {
                    $order['items'][$item['parent_id']]['subitems'] = array();
                }
                $order['items'][$item['parent_id']]['subitems'][$iid] = $order['items'][$iid];
                unset($order['items'][$iid]);
            }

/*            //
            // Setup discount text (taken from private/formatItems.php)
            //
            $order['items'][$iid]['discount_text'] = '';
            if( $order['items'][$iid]['discount_amount'] > 0 ) {
                if( $order['items'][$iid]['unit_discount_amount'] > 0 ) {
                    if( $order['items'][$iid]['quantity'] != 1 ) {
                        $order['items'][$iid]['discount_text'] .= '-$' . number_format($order['items'][$iid]['unit_discount_amount'], 2) . 'x' . $order['items'][$iid]['quantity'];
                    } else {
                        if( $order['items'][$iid]['unit_discount_percentage'] > 0 ) {
                            $order['items'][$iid]['discount_text'] .= '-$' . number_format($order['items'][$iid]['unit_discount_amount'], 2);
                        }
                    }
                }
                if( $order['items'][$iid]['unit_discount_percentage'] > 0 ) {
                    $order['items'][$iid]['discount_text'] .= ($order['items'][$iid]['discount_text'] != '' ? ', ' : '')
                        . (float)$order['items'][$iid]['unit_discount_percentage'] . '%';
                }
                $order['items'][$iid]['discount_text'] .= ' (-$' . number_format($order['items'][$iid]['discount_amount'], 2) . ')';
            }
            $order['items'][$iid]['deposit_text'] = '';
            if( ($order['items'][$iid]['flags']&0x80) == 0x80 && $order['items'][$iid]['cdeposit_amount'] > 0 ) {
                $order['items'][$iid]['deposit_text'] = $order['items'][$iid]['cdeposit_description'];
                $order['items'][$iid]['deposit_text'] .= ($order['items'][$iid]['deposit_text'] != '' ? ': ' : '')
                    . '$' . number_format(bcmul($order['items'][$iid]['quantity'], $order['items'][$iid]['cdeposit_amount'], 2), 2);
            } */
        }
    } else {
        $order['items'] = array();
    }
    uasort($order['items'], function($a, $b) {
        if( $a['line_number'] == $b['line_number'] ) {
            return 0;
        }
        return $a['line_number'] < $b['line_number'] ? -1 : 1;
        });

    // 
    // Get the taxes
    //
    $strsql = "SELECT id, " 
        . "line_number, "
        . "description, "
        . "ROUND(amount, 2) AS amount "
        . "FROM ciniki_poma_order_taxes "
        . "WHERE ciniki_poma_order_taxes.order_id = '" . ciniki_core_dbQuote($ciniki, $order['id']) . "' "
        . "AND ciniki_poma_order_taxes.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "ORDER BY line_number, date_added "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'taxes', 'fname'=>'id', 'name'=>'tax',
            'fields'=>array('id', 'line_number', 'description', 'amount')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['taxes']) ) {
        $order['taxes'] = array();
        $order['taxes_amount'] = 0;
    } else {
        $order['taxes'] = $rc['taxes'];
        $order['taxes_amount'] = 0;
        foreach($rc['taxes'] as $tid => $tax) {
            if( $tax['amount'] > 0 ) {
                $order['taxes_amount'] = bcadd($order['taxes_amount'], $tax['amount'], 2);
            } 
        }
    }

    return array('stat'=>'ok', 'order'=>$order);
}
?>
