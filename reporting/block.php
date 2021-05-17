<?php
//
// Description
// -----------
// This function will return the report details for a requested report block.
//
// Arguments
// ---------
// ciniki:
// tnid:     The ID of the tenant.
// args:            The possible arguments for.
//
//
// Returns
// -------
//
function ciniki_poma_reporting_block(&$ciniki, $tnid, $args) {

    //
    // Check to make sure the module is enabled
    //
    if( !isset($ciniki['tenant']['modules']['ciniki.poma']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.189', 'msg'=>"That report is not available."));
    }

    //
    // Check to make sure the report is specified
    //
    if( !isset($args['block_ref']) || !isset($args['options']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.190', 'msg'=>"No block specified."));
    }

    //
    // The array to store the report data
    //

    //
    // Return the list of reports for the tenant
    //
    if( $args['block_ref'] == 'ciniki.poma.todaysorders' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'reporting', 'blockTodaysOrders');
        return ciniki_poma_reporting_blockTodaysOrders($ciniki, $tnid, $args['options']);
    } elseif( $args['block_ref'] == 'ciniki.poma.openorders' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'reporting', 'blockOpenOrders');
        return ciniki_poma_reporting_blockOpenOrders($ciniki, $tnid, $args['options']);
    }

    return array('stat'=>'ok');
}
?>
