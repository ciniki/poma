<?php
//
// Description
// -----------
// This function will apply repeats for an order date.
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
function ciniki_poma_dateOpen(&$ciniki, $tnid, $date_id) {

    //
    // The repeat items will also be added by the dateLock function from cron if missed here
    //

    //
    // Load the date
    //
    $strsql = "SELECT id, status, order_date, autolock_dt, flags "
        . "FROM ciniki_poma_order_dates "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $date_id) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND status = 5 "
        . "AND open_dt <= UTC_TIMESTAMP() "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['date']) ) {
        return array('stat'=>'ok');
    }
    $date = $rc['date'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQueryList');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');

    //
    // Open the date
    //
    $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.poma.orderdate', $date['id'], array('status'=>10), 0x04);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    return array('stat'=>'ok');
}
?>
