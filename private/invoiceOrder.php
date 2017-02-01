<?php
//
// Description
// -----------
// This function will set the payment status of an order, enter it in the customers ledger and apply any credit available.
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
function ciniki_poma_invoiceOrder(&$ciniki, $business_id, $order_id) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');

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
    // Load the order
    //
    $strsql = "SELECT ciniki_poma_orders.id, "
        . "ciniki_poma_orders.order_number, "
        . "ciniki_poma_orders.payment_status, "
        . "ciniki_poma_orders.customer_id, "
        . "ciniki_poma_orders.total_amount, "
        . "ciniki_poma_orders.paid_amount, "
        . "ciniki_poma_orders.balance_amount "
        . "FROM ciniki_poma_orders "
        . "WHERE ciniki_poma_orders.id = '" . ciniki_core_dbQuote($ciniki, $order_id) . "' "
        . "AND ciniki_poma_orders.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
    if( $rc['stat'] != 'ok') {
        return $rc;
    }
    if( !isset($rc['order']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.27', 'msg'=>'Unable to find order'));
    }
    $order = $rc['order'];

    //
    // Check status to make sure it hasn't already been invoiced
    //
    if( $order['payment_status'] != 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.105', 'msg'=>'This order has already been invoiced.'));
    }

    //
    // Load the last entry in the customer ledger
    //
    $strsql = "SELECT id, balance "
        . "FROM ciniki_poma_customer_ledgers "
        . "WHERE customer_id = '" . ciniki_core_dbQuote($ciniki, $order['customer_id']) . "' "
        . "AND business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "ORDER BY transaction_date DESC "
        . "LIMIT 1 "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'entry');
    if( $rc['stat'] != 'ok') {
        return $rc;
    }
    if( !isset($rc['entry']) ) {
        $balance = 0;
    } else {
        $balance = $rc['entry']['balance'];
    }

    //
    // Take the invoice amount from the balance, no payments have been made yet.
    //
    $new_balance = bcsub($balance, $order['total_amount'], 6);

    $order_balance = $order['balance_amount'];
    $paid_amount = $order['paid_amount'];

    //
    // Check if credit available
    //
    if( $balance > 0 ) {
        if( $order_balance > $balance ) {
            $credit_applied = $balance;
            $order_balance = bcsub($order_balance, $credit_applied, 6);
        } elseif( $order_balance < $balance ) {
            $credit_applied = $order_balance;
            $order_balance = 0;
        }
        if( isset($credit_applied) && $credit_applied > 0 ) {
//            $new_balance = bcadd($new_balance, $credit_applied, 6);
            $paid_amount = bcadd($paid_amount, $credit_applied, 6);
        }
    }

    //
    // Change order status
    //
    $update_args = array('payment_status'=>10);
    if( isset($credit_applied) && $credit_applied > 0 ) {
        if( $order_balance == 0 ) { 
            $update_args['payment_status'] = 50;
        } else {
            $update_args['payment_status'] = 40;
        }
    }
    if( $paid_amount != $order['paid_amount'] ) {
        $update_args['paid_amount'] = $paid_amount;
    }
    if( $order_balance != $order['balance_amount'] ) {
        $update_args['balance_amount'] = $order_balance;
    }
    $rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.poma.order', $order['id'], $update_args, 0x04);
    if( $rc['stat'] != 'ok') {
        return $rc;
    }

    //
    // Add ledger entry
    //
    $dt = new DateTime('now', new DateTimezone($intl_timezone));
    $rc = ciniki_core_objectAdd($ciniki, $business_id, 'ciniki.poma.customerledger', array(
        'customer_id'=>$order['customer_id'],
        'order_id'=>$order['id'],
        'transaction_type'=>30,
        'transaction_date'=>$dt->format('Y-m-d H:i:s'),
        'source'=>0,
        'description'=>'Invoice #'  . $order['order_number'],
        'customer_amount'=>$order['total_amount'],
        'transaction_fees'=>0,
        'business_amount'=>$order['total_amount'],
        'balance'=>$new_balance,
        'notes'=>'',
        ), 0x04);
    if( $rc['stat'] != 'ok') {
        return $rc;
    }
    $ledger_id = $rc['id'];
    
    //
    // Add the payment if credit was applied
    //
    if( isset($credit_applied) && $credit_applied > 0 ) {
        $rc = ciniki_core_objectAdd($ciniki, $business_id, 'ciniki.poma.orderpayment', array(
            'order_id'=>$order['id'],
            'ledger_id'=>$ledger_id,
            'payment_type'=>10,
            'amount'=>$credit_applied,
            ), 0x04);
        if( $rc['stat'] != 'ok') {
            return $rc;
        }
    }

    return array('stat'=>'ok');
}
?>
