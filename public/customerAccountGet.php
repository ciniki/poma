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
// tnid:        The ID of the tenant to get Product for.
//
// Returns
// -------
//
function ciniki_poma_customerAccountGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'customer_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Customer'),
        'order_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Order'),
        'sections'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'list', 'name'=>'Return Orders'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.customerAccountGet');
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
    // Load tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');

    $rsp = array('stat'=>'ok', 'customer_details'=>array(), 'orders'=>array());

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'hooks', 'customerDetails');

    //
    // Get the customer details
    //
    if( isset($args['sections']) && in_array('details', $args['sections']) ) {
        $rc = ciniki_customers_hooks_customerDetails($ciniki, $args['tnid'], array('customer_id'=>$args['customer_id']));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['details']) ) {
            $rsp['customer_details'] = $rc['details'];
        }
        if( isset($rc['customer']['member_status_text']) && $rc['customer']['member_status_text'] != '' ) {
            if( isset($rc['customer']['member_lastpaid']) && $rc['customer']['member_lastpaid'] != '' ) {
                $rsp['customer_details'][] = array('detail'=>array(
                    'label'=>'Membership',
                    'value'=>$rc['customer']['member_status_text'] . ' <span class="subdue">[' . $rc['customer']['member_lastpaid'] . ']</span>',
                    ));
            } else {
                $rsp['customer_details'][] = array('detail'=>array(
                    'label'=>'Membership',
                    'value'=>$rc['customer']['member_status_text'],
                    ));
            }
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
            . "WHERE customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
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
        $rsp['recent_ledger'] = array();
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
//                array_unshift($rsp['recent_ledger'], $entry);
            }
            if( isset($balance) ) {
                $rsp['customer_details'][] = array('detail'=>array(
                    'label'=>'Account',
                    'value'=>($balance < 0 ? '-' : '') . '$' . number_format(abs($balance), 2),
                ));
    //            if( $balance < 0 && $balance != $rsp['order']['balance_amount'] ) {
    //                $rsp['order']['default_payment_amount'] = abs($balance);
    //            }
    //            if( $balance < 0 ) {
    //                $rsp['checkout_account'] = array(
    //                    array('label'=>'Account Balance Owing', 'status'=>'red', 'value'=>'$' . number_format(abs($balance), 2)),
    //                    );
    //            }
            }
        }
    }

    //
    // Get the orders
    //
    if( isset($args['sections']) && in_array('orders', $args['sections']) ) {
        $strsql = "SELECT orders.id, "
            . "orders.customer_id, "
            . "orders.order_number, "
            . "orders.order_date, "
            . "orders.status, "
            . "orders.status AS status_text, "
            . "orders.payment_status, "
            . "orders.payment_status AS payment_status_text, "
            . "orders.billing_name, "
            . "orders.total_amount "
            . "FROM ciniki_poma_orders AS orders "
            . "WHERE orders.customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
            . "AND orders.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "ORDER BY orders.order_date DESC "
            . "";
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'orders', 'fname'=>'id', 'fields'=>array('id', 'customer_id', 'order_number', 'order_date', 
                    'status', 'status_text', 'payment_status', 'payment_status_text', 'billing_name', 'total_amount'),
                'utctotz'=>array('order_date'=>array('timezone'=>'UTC', 'format'=>$date_format)),
                'maps'=>array('status_text'=>$maps['order']['status'],
                    'payment_status_text'=>$maps['order']['payment_status']),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['orders']) ) {
            $rsp['orders'] = $rc['orders'];
            foreach($rsp['orders'] as $oid => $order) {
                $rsp['orders'][$oid]['total_amount_display'] = '$' . number_format($order['total_amount'], 2);
            }
        }
    }

    //
    // Get the records for the account
    //
    if( isset($args['sections']) && in_array('records', $args['sections']) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'accountRecords');
        $rc = ciniki_poma_accountRecords($ciniki, $args['tnid'], array('customer_id'=>$args['customer_id']));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $rsp['records'] = isset($rc['records']) ? $rc['records'] : array();
    }

    //
    // Get the order
    //
    $rsp['order_messages'] = array();
    if( isset($args['order_id']) && $args['order_id'] > 0 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderLoad');
        $rc = ciniki_poma_orderLoad($ciniki, $args['tnid'], $args['order_id']);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['order']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.28', 'msg'=>'Unable to find order'));
        }
        $rsp['order'] = $rc['order'];
//        $rsp['order']['default_payment_amount'] = $rc['order']['balance_amount'];

        //
        // Build the nplists
        //
//        foreach($rsp['order']['items'] as $item) {
//            $rsp['nplists']['orderitems'][] = $item['id'];
//        }
        //
        // Check if there are any messages for this order
        //
        if( isset($ciniki['tenant']['modules']['ciniki.mail']) ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'mail', 'hooks', 'objectMessages');
            $rc = ciniki_mail_hooks_objectMessages($ciniki, $args['tnid'], array('object'=>'ciniki.poma.order', 'object_id'=>$args['order_id']));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            $rsp['order_messages'] = isset($rc['messages']) ? $rc['messages'] : array();
        } 
    }

    return $rsp;
}
?>
