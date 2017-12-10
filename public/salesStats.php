<?php
//
// Description
// -----------
// Return the sales stats
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Customer Item for.
//
// Returns
// -------
//
function ciniki_poma_salesStats($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.salesStats');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Get the list of items
    //
    $strsql = "SELECT description, COUNT(*) AS num_ordered "
        . "FROM ciniki_poma_order_items "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND DATEDIFF(UTC_TIMESTAMP(), date_added) < 366 "
        . "GROUP BY description "
        . "ORDER BY description "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'items', 'fname'=>'description', 'fields'=>array('description', 'num_ordered')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $items = isset($rc['items']) ? $rc['items'] : array();

    return array('stat'=>'ok', 'products_sales'=>$items);
}
?>
