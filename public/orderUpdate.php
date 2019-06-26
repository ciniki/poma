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
function ciniki_poma_orderUpdate(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'order_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Order'),
        'status'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Status'),
        'date_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Status'),
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
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.orderUpdate');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Get the order
    //
    $strsql = "SELECT id, date_id, status "
        . "FROM ciniki_poma_orders "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['order_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['order']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.125', 'msg'=>'Unable to find order.'));
    }
    $order = $rc['order'];

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
    // Get the date 
    //
    if( isset($args['date_id']) && $args['date_id'] != $order['date_id'] ) {
        $strsql = "SELECT order_date "
            . "FROM ciniki_poma_order_dates "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['date_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.211', 'msg'=>'Unable to load date', 'err'=>$rc['err']));
        }
        if( !isset($rc['date']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.212', 'msg'=>'Unable to find requested date'));
        }
        $args['order_date'] = $rc['date']['order_date'];
        $update_order = 'yes';
    }

    //
    // Check if status changed
    //
    if( isset($args['status']) && $args['status'] != $order['status'] ) {
        $update_order = 'yes';
    } 

    //
    // Update the Order Item in the database
    //
    if( isset($update_order) && $update_order == 'yes' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
        $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.poma.order', $args['order_id'], $args, 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
            return $rc;
        }
    }

    //
    // Update the order
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderUpdateStatusBalance');
    $rc = ciniki_poma_orderUpdateStatusBalance($ciniki, $args['tnid'], $args['order_id']);
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

    //
    // Update the web index if enabled
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'hookExec');
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'web', 'indexObject', array('object'=>'ciniki.poma.orderItem', 'object_id'=>$args['item_id']));

    return array('stat'=>'ok');
}
?>
