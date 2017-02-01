<?php
//
// Description
// -----------
// This method returns the orders for a specific date, and the details of a specific order if specified.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:        The ID of the business to get Product for.
//
// Returns
// -------
//
function ciniki_poma_dateCheckout($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'),
        'date_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Date'),
        'order'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'New Order'),
        'order_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Order'),
        'customer_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Customer'),
        'action'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Action'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to business_id as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'foodmarket', 'private', 'checkAccess');
    $rc = ciniki_foodmarket_checkAccess($ciniki, $args['business_id'], 'ciniki.foodmarket.productList');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load poma maps
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'maps');
    $rc = ciniki_poma_maps($ciniki);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $maps = $rc['maps'];

    //
    // Load business settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'intlSettings');
    $rc = ciniki_businesses_intlSettings($ciniki, $args['business_id']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');

    $rsp = array('stat'=>'ok', 'dates'=>array(), 'open_orders'=>array(), 'closed_orders'=>array(), 
        'nplists'=>array('orderitems'=>array()),
        );

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');

    //
    // If the date wasn't set, then choose the closest date to now
    //
    if( !isset($args['date_id']) || $args['date_id'] == 0 ) {
        $strsql = "SELECT id, ABS(DATEDIFF(NOW(), order_date)) AS age "
            . "FROM ciniki_poma_order_dates "
            . "ORDER BY age ASC "
            . "LIMIT 1 "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['date']['id']) ) {
            return array('stat'=>'ok', 'dates'=>array(), 'open_orders'=>array(), 'closed_orders'=>array(), 'order'=>array());
        }
        $args['date_id'] = $rc['date']['id'];
        $rsp['date_id'] = $rc['date']['id'];
    }

    //
    // Get all the dates from latest date to earliest
    //
    $strsql = "SELECT ciniki_poma_order_dates.id, "
        . "ciniki_poma_order_dates.order_date, "
        . "ciniki_poma_order_dates.display_name, "
        . "ciniki_poma_order_dates.status, "
        . "ciniki_poma_order_dates.flags "
        . "FROM ciniki_poma_order_dates "
        . "WHERE ciniki_poma_order_dates.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
        . "GROUP BY ciniki_poma_order_dates.id "
        . "ORDER BY ciniki_poma_order_dates.order_date DESC "
        . "LIMIT 15"
        . "";
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'dates', 'fname'=>'id', 'fields'=>array('id', 'order_date', 'display_name', 'status', 'flags')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['dates']) || count($rc['dates']) < 1 ) {
        return array('stat'=>'ok', 'dates'=>array(), 'open_orders'=>array(), 'closed_orders'=>array(), 'order'=>array());
    }
    $rsp['dates'] = $rc['dates'];
    foreach($rsp['dates'] as $did => $date) {
        $rsp['dates'][$did]['name_status'] = $date['display_name'] . ' - ' . $maps['orderdate']['status'][$date['status']];
    }
    
    //
    // Check if a new order should be created
    //
    if( isset($args['order']) && $args['order'] == 'new' 
        && isset($args['customer_id']) && $args['customer_id'] > 0 
        && isset($args['date_id']) && $args['date_id'] > 0 
        ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'newOrderForDate');
        $rc = ciniki_poma_newOrderForDate($ciniki, $args['business_id'], array(
            'customer_id'=>$args['customer_id'],
            'date_id'=>$args['date_id'],
            'checkdate'=>'no',
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $args['order_id'] = $rc['order']['id'];
    }

    //
    // Find the customers order if no order specified
    //
    elseif( (!isset($args['order_id']) || $args['order_id'] == 0) && isset($args['customer_id']) && $args['customer_id'] > 0 ) {
        $strsql = "SELECT id "
            . "FROM ciniki_poma_orders "
            . "WHERE date_id = '" . ciniki_core_dbQuote($ciniki, $args['date_id']) . "' "
            . "AND customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
            . "ORDER BY status "
            . "LIMIT 1 "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['order']['id']) ) {
            $args['order_id'] = $rc['order']['id'];
        }
    }

    if( isset($args['action']) && $args['action'] == 'invoiceorder' && isset($args['order_id']) && $args['order_id'] > 0 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'invoiceOrder');
        $rc = ciniki_poma_invoiceOrder($ciniki, $args['business_id'], $args['order_id']);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
    }
    
    if( isset($args['action']) && $args['action'] == 'closeorder' && isset($args['order_id']) && $args['order_id'] > 0 ) {
        //
        // Check the current status
        //
        $strsql = "SELECT id, status "
            . "FROM ciniki_poma_orders "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['order_id']) . "' "
            . "AND business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['order']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.106', 'msg'=>'Unable to find order.'));
        }
        $order = $rc['order'];

        //
        // Update the Order status in the database
        //
        if( $order['status'] < 70 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            $rc = ciniki_core_objectUpdate($ciniki, $args['business_id'], 'ciniki.poma.order', $args['order_id'], array('status'=>70), 0x04);
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
        }
    }

    //
    // Get the order
    //
    if( isset($args['order_id']) && $args['order_id'] > 0 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderLoad');
        $rc = ciniki_poma_orderLoad($ciniki, $args['business_id'], $args['order_id']);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['order']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.28', 'msg'=>'Unable to find order'));
        }
        $rsp['order'] = $rc['order'];
//        $rsp['checkout_orderitems'] = $rc['order']['items'];
//        $rsp['checkout_tallies'] = $rc['order']['tallies'];
//        $rsp['checkout_orderpayments'] = $rc['order']['payments'];

        //
        // Build the nplists
        //
        foreach($rsp['order']['items'] as $item) {
            $rsp['nplists']['orderitems'][] = $item['id'];
        }
        //
        // Check if there are any messages for this order
        //
        error_log('check mail');
        if( isset($ciniki['business']['modules']['ciniki.mail']) ) {
            error_log('oeasdf: ' . $args['order_id']);
            ciniki_core_loadMethod($ciniki, 'ciniki', 'mail', 'hooks', 'objectMessages');
            $rc = ciniki_mail_hooks_objectMessages($ciniki, $args['business_id'], array('object'=>'ciniki.poma.order', 'object_id'=>$args['order_id']));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            if( isset($rc['messages']) ) {
                $rsp['order']['messages'] = $rc['messages'];
            } else {
                $rsp['order']['messages'] = array();
            }
        } 
    }

    if( isset($rsp['order']['customer_id']) && $rsp['order']['customer_id'] > 0 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'hooks', 'customerDetails');
        $rc = ciniki_customers_hooks_customerDetails($ciniki, $args['business_id'], array('customer_id'=>$rsp['order']['customer_id']));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['details']) ) {
            $rsp['customer_details'] = $rc['details'];
        }

        //
        // Get the current account balance
        //
        $strsql = "SELECT ciniki_poma_customer_ledgers.id, "
            . "ciniki_poma_customer_ledgers.description, "
            . "ciniki_poma_customer_ledgers.transaction_date, "
            . "ciniki_poma_customer_ledgers.transaction_type, "
            . "ciniki_poma_customer_ledgers.customer_amount, "
            . "ciniki_poma_customer_ledgers.balance "
            . "FROM ciniki_poma_customer_ledgers "
            . "WHERE customer_id = '" . ciniki_core_dbQuote($ciniki, $rsp['order']['customer_id']) . "' "
            . "AND business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
            . "ORDER BY transaction_date DESC "
            . "LIMIT 15 "
            . "";
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'entries', 'fname'=>'id', 
                'fields'=>array('id', 'description', 'transaction_date', 'transaction_type', 'customer_amount', 'balance'),
                'utctotz'=>array('transaction_date'=>array('timezone'=>$intl_timezone, 'format'=>$date_format)),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $rsp['checkout_recentledger'] = array();
        if( isset($rc['entries']) ) {
            foreach($rc['entries'] as $entry) {
                if( !isset($balance) ) {
                    $balance = $entry['balance'];
                }
                if( $entry['transaction_type'] == 10 ) {
                    $entry['amount'] = '$' . number_format($entry['customer_amount'], 2);
                } elseif( $entry['transaction_type'] == 30 ) {
                    $entry['amount'] = '-$' . number_format($entry['customer_amount'], 2);
                } elseif( $entry['transaction_type'] == 60 ) {
                    $entry['amount'] = '$' . number_format($entry['customer_amount'], 2);
                }
                $entry['balance_text'] = ($entry['balance'] < 0 ? '-':'') . '$' . number_format(abs($entry['balance']), 2);
                array_unshift($rsp['checkout_recentledger'], $entry);
            }
            if( isset($balance) ) {
                $rsp['customer_details'][] = array('detail'=>array(
                    'label'=>'Balance',
                    'value'=>($balance < 0 ? '-' : '') . '$' . number_format(abs($balance), 2),
                ));
            }
        }
    }

    //
    // Get the list of open & closed orders
    //
    $strsql = "SELECT ciniki_poma_orders.id, "
        . "IF(ciniki_poma_orders.status < 70, 'open', 'closed') AS state, "
        . "ciniki_poma_orders.status, "
        . "ciniki_poma_orders.payment_status, "
        . "ciniki_poma_orders.billing_name "
        . "FROM ciniki_poma_orders "
        . "WHERE date_id = '" . ciniki_core_dbQuote($ciniki, $args['date_id']) . "' "
        . "AND business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
        . "ORDER BY state, billing_name "
        . "";
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'states', 'fname'=>'state', 'fields'=>array('state')),
        array('container'=>'orders', 'fname'=>'id', 'fields'=>array('id', 'state', 'status', 'payment_status', 'billing_name'),
            'maps'=>array('payment_status'=>$maps['order']['payment_status']),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['states']) ) {
        foreach($rc['states'] as $state) {
            if( $state['state'] == 'open' ) {
                $rsp['open_orders'] = $state['orders'];
            } else {
                $rsp['closed_orders'] = $state['orders'];
            }
        }
    }

    return $rsp;
}
?>
