<?php
//
// Description
// -----------
// This function will update the customer ledger, and invoice status if required
//
// Arguments
// ---------
// ciniki:
// tnid:                 The tenant ID to check the session user against.
// method:                      The requested method.
//
// Returns
// -------
// <rsp stat='ok' />
//
function ciniki_poma_accountRecords(&$ciniki, $tnid, $args) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');

    //
    // Set the default start record to at beginning of ledger, unless someething else is specified
    //
    $start_ledger_id = 0;
    $start_ledger_date = '0000-00-00';
    $prev_balance = 0;

    if( isset($args['customer_id']) && $args['customer_id'] > 0 ) {
        $customer_id = $args['customer_id'];
    } else {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.178', 'msg'=>'Must specify a customer'));
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');

    //
    // Setup the empty array for records
    //
    $records = array();

    //
    // Get all the orders
    //
    $strsql = "SELECT ciniki_poma_orders.id, "
        . "DATE_FORMAT(ciniki_poma_orders.order_date, '%Y%m%d') AS sort_id, "
        . "ciniki_poma_orders.order_date AS record_date, "
        . "ciniki_poma_orders.order_number, "
        . "ciniki_poma_orders.total_amount AS amount "
        . "FROM ciniki_poma_orders "
        . "WHERE customer_id = '" . ciniki_core_dbQuote($ciniki, $customer_id) . "' "
        . "";
    if( isset($start_ledger_date) ) {
        $strsql .= "AND order_date >= '" . ciniki_core_dbQuote($ciniki, $start_ledger_date) . "' "
        . "AND id <> '" . ciniki_core_dbQuote($ciniki, $start_ledger_id) . "' "
        . "";
    }
    $strsql .= "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "ORDER BY ciniki_poma_orders.order_date ASC "
        . "";
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'orders', 'fname'=>'id', 
            'fields'=>array('id', 'sort_id', 'record_date', 'order_number', 'amount'),
            'utctotz'=>array('record_date'=>array('timezone'=>'UTC', 'format'=>$date_format)),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['orders']) ) {
        foreach($rc['orders'] as $oid => $order) {
            $order['sort_id'] .= '000000';
            $order['amount'] = -$order['amount'];
            $order['transaction_name'] = 'Invoice #' . $order['order_number'];
            $order['balance'] = 0;
            $records[] = $order;
        }
    }

    //
    // Get all the credits and payments
    //
    $strsql = "SELECT id, "
        . "customer_id, "
        . "order_id, "
        . "transaction_type, "
        . "DATE_FORMAT(transaction_date, '%Y%m%d%H%i%s') AS sort_id, "
        . "transaction_date AS record_date, "
        . "description, "
        . "customer_amount AS amount, "
        . "balance "
        . "FROM ciniki_poma_customer_ledgers "
        . "WHERE customer_id = '" . ciniki_core_dbQuote($ciniki, $customer_id) . "' "
        . "AND transaction_type IN (10, 60) "
        . "";
    if( isset($start_ledger_date) ) {
        $strsql .= "AND transaction_date >= '" . ciniki_core_dbQuote($ciniki, $start_ledger_date) . "' "
        . "AND id <> '" . ciniki_core_dbQuote($ciniki, $start_ledger_id) . "' "
        . "";
    }
    $strsql .= "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "ORDER BY record_date ASC "
        . "";
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'transactions', 'fname'=>'id', 
            'fields'=>array('id', 'customer_id', 'order_id', 'sort_id', 'transaction_type', 'record_date', 'amount', 'old_balance'=>'balance', 'description'),
            'utctotz'=>array('record_date'=>array('timezone'=>'UTC', 'format'=>$date_format)),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['transactions']) ) {
        foreach($rc['transactions'] as $tid => $transaction) {
            if( $transaction['transaction_type'] == 10 ) {
                $transaction['transaction_name'] = 'Credit';
            } elseif( $transaction['transaction_type'] == 60 ) {
                $transaction['transaction_name'] = $transaction['description'];
            }
            $transaction['balance'] = 0;
            $records[] = $transaction;
        }
    }

    //
    // Sort the records
    //
    usort($records, function($a, $b) {
        if( $a['sort_id'] == $b['sort_id'] ) {
            return 0;
        }
        return ($a['sort_id'] < $b['sort_id']) ? -1 : 1;
    });

    //
    // Go through the records and calculate balance
    //
    $balance = 0;
    foreach($records as $rid => $record) {
        $balance = bcadd($balance, $record['amount'], 6);
        $records[$rid]['balance'] = $balance;
        $records[$rid]['balance_display'] = number_format($balance, 2);
        $records[$rid]['amount_display'] = number_format($record['amount'], 2);
    }

    return array('stat'=>'ok', 'records'=>$records);
}
?>
