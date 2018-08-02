<?php
//
// Description
// -----------
// This function will return the list of available blocks to the ciniki.reporting module.
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
function ciniki_poma_reporting_blocks(&$ciniki, $tnid, $args) {

    //
    // Check to make sure the module is enabled
    //
    if( !isset($ciniki['tenant']['modules']['ciniki.poma']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.193', 'msg'=>"I'm sorry, the block you requested does not exist."));
    }

    $blocks = array();

    //
    // Return the list of blocks for the tenant
    //
    $blocks['ciniki.poma.todaysorders'] = array(
        'name'=>'Todays Orders',
        'module' => 'Orders',
        'options'=>array(
            ),
        );

    return array('stat'=>'ok', 'blocks'=>$blocks);
}
?>
