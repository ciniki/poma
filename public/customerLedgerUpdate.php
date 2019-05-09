<?php
//
// Description
// ===========
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_poma_customerLedgerUpdate(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'entry_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Customer Ledger Entry'),
        'customer_id'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Customer'),
        'order_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Order'),
        'transaction_type'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Type'),
        'transaction_date_date'=>array('required'=>'no', 'blank'=>'no', 'type'=>'date', 'name'=>'Date'),
        'transaction_date_time'=>array('required'=>'no', 'blank'=>'no', 'type'=>'time', 'name'=>'Date'),
        'source'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Source'),
        'description'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Description'),
        'customer_amount'=>array('required'=>'no', 'blank'=>'no', 'type'=>'currency', 'name'=>'Customer Amount'),
        'transaction_fees'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'currency', 'name'=>'Transaction Fees'),
        'tenant_amount'=>array('required'=>'no', 'blank'=>'no', 'type'=>'currency', 'name'=>'Tenant Amount'),
        'balance'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Balance'),
        'notes'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Notes'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.customerLedgerUpdate');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.112', 'msg'=>'Not yet implemented'));

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

    //
    // Update the Customer Ledger Entry in the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.poma.customerledger', $args['entry_id'], $args, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }

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

    return array('stat'=>'ok');
}
?>
