<?php
//
// Description
// -----------
// This function will update a queued object id, when queued items object_id change from purchase by weight to weighted units, or units.
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure.
// tnid:     The ID of the tenant to get poma web options for.
//
// args:            The possible arguments for posts
//
//
// Returns
// -------
//
function ciniki_poma_hooks_updateObjectID(&$ciniki, $tnid, $args) {

    //
    // Check to make sure the module is enabled
    //
    if( !isset($ciniki['tenant']['modules']['ciniki.poma']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.179', 'msg'=>"I'm sorry, the page you requested does not exist."));
    }

    //
    // Check to make sure object and description have been passed
    //
    if( !isset($args['object']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.180', 'msg'=>"No object specified."));
    }
    if( !isset($args['old_object_id']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.181', 'msg'=>"No old object specified."));
    }
    if( !isset($args['new_object_id']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.182', 'msg'=>"No new object specified."));
    }

    //
    // Load tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    //
    // Get the list of items
    //
    $strsql = "SELECT id, object_id "
        . "FROM ciniki_poma_customer_items "
        . "WHERE ciniki_poma_customer_items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND ciniki_poma_customer_items.object = '" . ciniki_core_dbQuote($ciniki, $args['object']) . "' "
        . "AND ciniki_poma_customer_items.object_id = '" . ciniki_core_dbQuote($ciniki, $args['old_object_id']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['rows']) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
        foreach($rc['rows'] as $row) {
            if( $row['object_id'] != $args['new_object_id'] ) { 
                $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.poma.customeritem', $row['id'], array('object_id'=>$args['new_object_id']), 0x04);
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
            }
        }
    }

    //
    // Get the list of queued items
    //
    $strsql = "SELECT id, object_id "
        . "FROM ciniki_poma_queued_items "
        . "WHERE ciniki_poma_queued_items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND ciniki_poma_queued_items.object = '" . ciniki_core_dbQuote($ciniki, $args['object']) . "' "
        . "AND ciniki_poma_queued_items.object_id = '" . ciniki_core_dbQuote($ciniki, $args['old_object_id']) . "' "
        . "AND ciniki_poma_queued_items.status <= 40 "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['rows']) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
        foreach($rc['rows'] as $row) {
            if( $row['object_id'] != $args['new_object_id'] ) { 
                $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.poma.queueditem', $row['id'], array('object_id'=>$args['new_object_id']), 0x04);
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
            }
        }
    }

    //
    // FIXME: Update any descriptions on open orders
    //



    return array('stat'=>'ok');
}
?>
