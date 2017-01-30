<?php
//
// Description
// -----------
// This method will delete an customer ledger entry.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:            The ID of the business the customer ledger entry is attached to.
// entry_id:            The ID of the customer ledger entry to be removed.
//
// Returns
// -------
//
function ciniki_poma_customerLedgerDelete(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'),
        'entry_id'=>array('required'=>'yes', 'blank'=>'yes', 'name'=>'Customer Ledger Entry'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to business_id as owner
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['business_id'], 'ciniki.poma.customerLedgerDelete');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.113', 'msg'=>'Not yet implemented'));

    //
    // Get the current settings for the customer ledger entry
    //
    $strsql = "SELECT id, uuid "
        . "FROM ciniki_poma_customer_ledgers "
        . "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
        . "AND id = '" . ciniki_core_dbQuote($ciniki, $args['entry_id']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'entry');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['entry']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.107', 'msg'=>'Customer Ledger Entry does not exist.'));
    }
    $entry = $rc['entry'];

    //
    // Check for any dependencies before deleting
    //

    //
    // Check if any modules are currently using this object
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectCheckUsed');
    $rc = ciniki_core_objectCheckUsed($ciniki, $args['business_id'], 'ciniki.poma.customerLedger', $args['entry_id']);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.108', 'msg'=>'Unable to check if the customer ledger entry is still being used.', 'err'=>$rc['err']));
    }
    if( $rc['used'] != 'no' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.109', 'msg'=>'The customer ledger entry is still in use. ' . $rc['msg']));
    }

    //
    // Start transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDelete');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.poma');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Remove the entry
    //
    $rc = ciniki_core_objectDelete($ciniki, $args['business_id'], 'ciniki.poma.customerledger',
        $args['entry_id'], $entry['uuid'], 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
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
    // Update the last_change date in the business modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
    ciniki_businesses_updateModuleChangeDate($ciniki, $args['business_id'], 'ciniki', 'poma');

    return array('stat'=>'ok');
}
?>
