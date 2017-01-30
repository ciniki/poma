<?php
//
// Description
// -----------
// This method will add a new customer ledger entry for the business.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:        The ID of the business to add the Customer Ledger Entry to.
//
// Returns
// -------
//
function ciniki_poma_customerLedgerAdd(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'),
        'customer_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Customer'),
        'order_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Order'),
        'transaction_type'=>array('required'=>'yes', 'blank'=>'no', 'validlist'=>array('10', '60'), 'name'=>'Type'),
//        'transaction_date'=>array('required'=>'yes', 'blank'=>'no', 'type'=>'datetimetoutc', 'name'=>'Date'),
        'transaction_date_date'=>array('required'=>'yes', 'blank'=>'no', 'type'=>'date', 'name'=>'Date'),
        'transaction_date_time'=>array('required'=>'yes', 'blank'=>'no', 'type'=>'time', 'name'=>'Time'),
        'source'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Source'),
        'customer_amount'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Customer Amount'),
        'transaction_fees'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Transaction Fees'),
        'business_amount'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Business Amount'),
        'notes'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Notes'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to business_id as owner
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['business_id'], 'ciniki.poma.customerLedgerAdd');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    if( $args['transaction_type'] == 10 ) {
        $args['description'] = 'Credit';
    } else {
        $args['description'] = 'Payment';
    }

    //
    // Load business settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'intlSettings');
    $rc = ciniki_businesses_intlSettings($ciniki, $args['business_id']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    $transaction_date = $args['transaction_date_date'] . ' ' . $args['transaction_date_time'];
    $dt = new DateTime($transaction_date, new DateTimezone($intl_timezone));
    $args['transaction_date'] = $dt->format('Y-m-d H:i:s');

    if( !isset($args['transaction_fees']) ) {
        $args['transaction_fees'] = 0;
    }
    if( !isset($args['business_amount']) ) {
        $args['business_amount'] = bcsub($args['customer_amount'], $args['transaction_fees'], 6);
    }

    //
    // Start transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.poma');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'accountApplyCredit');
    $rc = ciniki_poma_accountApplyCredit($ciniki, $args['business_id'], $args);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $entry_id = $rc['id'];

    //
    // Commit the transaction
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.poma');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the last_change date in the business modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
    ciniki_businesses_updateModuleChangeDate($ciniki, $args['business_id'], 'ciniki', 'poma');

    return array('stat'=>'ok', 'id'=>$entry_id);
}
?>
