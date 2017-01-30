<?php
//
// Description
// -----------
// This function will apply a credit to a customer account.
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
function ciniki_poma_accountApplyCredit(&$ciniki, $business_id, $args) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderUpdateStatusBalance');

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
    // Load the last entry in the customer ledger
    //
    $strsql = "SELECT id, balance "
        . "FROM ciniki_poma_customer_ledgers "
        . "WHERE customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
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
    // Apply the credit to the balance
    //
    $new_balance = bcadd($balance, $args['customer_amount'], 6);
    $credit_balance = $args['customer_amount'];

    //
    // Load any unpaid invoices
    //
    $strsql = "SELECT ciniki_poma_orders.id, "
        . "ciniki_poma_orders.total_amount, "
        . "ciniki_poma_orders.balance_amount "
        . "FROM ciniki_poma_orders "
        . "WHERE ciniki_poma_orders.customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
        . "AND ciniki_poma_orders.payment_status > 0 "
        . "AND ciniki_poma_orders.payment_status < 50 "
        . "AND ciniki_poma_orders.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'invoice');
    if( $rc['stat'] != 'ok') {
        return $rc;
    }
    if( isset($rc['rows']) ) {
        $unpaid_orders = $rc['rows'];
        //
        // Check if orders will get payment amounts
        //
        foreach($unpaid_orders as $oid => $order) {
            //
            // Skip any orders that might be screwed up with a negative balance
            //
            if( $order['balance_amount'] < 0 ) {    
                continue;
            }
            if( $order['balance_amount'] < $credit_balance ) {
                $unpaid_orders[$oid]['payment_amount'] = $order['balance_amount'];
                $credit_balance = bcsub($credit_balance, $order['balance_amount'], 6);
//                $new_balance = bcadd($new_balance, $order['balance_amount'], 6);
            } else {
                $unpaid_orders[$oid]['payment_amount'] = $credit_balance;
                $credit_balance = 0;
//                $new_balance = bcadd($new_balance, $order['balance_amount'], 6);
            }
            if( $credit_balance <= 0 ) {
                break;
            }
        }
    }

    //
    // Add the ledger entry
    //
    $args['balance'] = $new_balance;
    $rc = ciniki_core_objectAdd($ciniki, $business_id, 'ciniki.poma.customerledger', $args, 0x04);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $entry_id = $rc['id'];

    //
    // Apply credit to invoice if open unpaid invoices
    //
    if( isset($unpaid_orders) ) {
        foreach($unpaid_orders as $order) {
            if( isset($order['payment_amount']) && $order['payment_amount'] > 0 ) {
                $rc = ciniki_core_objectAdd($ciniki, $business_id, 'ciniki.poma.orderpayment', array(
                    'order_id'=>$order['id'],
                    'ledger_id'=>$entry_id,
                    'payment_type'=>$args['transaction_type'],
                    'amount'=>$order['payment_amount'],
                    ), 0x04);
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
                $rc = ciniki_poma_orderUpdateStatusBalance($ciniki, $business_id, $order['id']);
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
            }
        }
    }
    
    return array('stat'=>'ok', 'id'=>$entry_id);
}
?>
