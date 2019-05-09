<?php
//
// Description
// -----------
// This method will add a new customer ledger entry for the tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to add the Customer Ledger Entry to.
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
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'customer_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Customer'),
        'order_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Order'),
        'transaction_type'=>array('required'=>'yes', 'blank'=>'no', 'validlist'=>array('10', '60'), 'name'=>'Type'),
//        'transaction_date'=>array('required'=>'yes', 'blank'=>'no', 'type'=>'datetimetoutc', 'name'=>'Date'),
//        'transaction_date_date'=>array('required'=>'yes', 'blank'=>'no', 'type'=>'date', 'name'=>'Date'),
//        'transaction_date_time'=>array('required'=>'yes', 'blank'=>'no', 'type'=>'time', 'name'=>'Time'),
        'source'=>array('required'=>'no', 'blank'=>'yes', 'default'=>'0', 'name'=>'Source'),
        'customer_amount'=>array('required'=>'yes', 'blank'=>'no', 'type'=>'currency', 'name'=>'Customer Amount'),
        'transaction_fees'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'currency', 'name'=>'Transaction Fees'),
        'tenant_amount'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'currency', 'name'=>'Tenant Amount'),
        'notes'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Notes'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.customerLedgerAdd');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    //
    // Load maps
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'maps');
    $rc = ciniki_poma_maps($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $maps = $rc['maps'];

    //
    // Setup defaults
    //
    $dt = new DateTime('now', new DateTimezone('UTC'));
    $args['transaction_date'] = $dt->format('Y-m-d H:i:s');
    if( $args['transaction_type'] == 10 ) {
        $args['description'] = 'Credit';
    } else {
        $args['description'] = 'Payment';
        if( isset($maps['customerledger']['source'][$args['source']]) ) {
            $args['description'] .= ' - ' . $maps['customerledger']['source'][$args['source']];
        }
    }

//    $transaction_date = $args['transaction_date_date'] . ' ' . $args['transaction_date_time'];
//    $dt = new DateTime($transaction_date, new DateTimezone($intl_timezone));
//    $args['transaction_date'] = $dt->format('Y-m-d H:i:s');

    if( !isset($args['transaction_fees']) ) {
        $args['transaction_fees'] = 0;
    }
    if( !isset($args['tenant_amount']) ) {
        $args['tenant_amount'] = bcsub($args['customer_amount'], $args['transaction_fees'], 6);
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
    $rc = ciniki_poma_accountApplyCredit($ciniki, $args['tnid'], $args);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $entry_id = $rc['id'];

    //
    // Update account balances
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'accountUpdate');
    $rc = ciniki_poma_accountUpdate($ciniki, $args['tnid'], array('customer_id'=>$args['customer_id']));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Commit the transaction
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.poma');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $args['tnid'], 'ciniki', 'poma');

    return array('stat'=>'ok', 'id'=>$entry_id);
}
?>
