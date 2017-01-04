<?php
//
// Description
// -----------
// This function will update any object descriptions from other modules
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure.
// business_id:     The ID of the business to get poma web options for.
//
// args:            The possible arguments for posts
//
//
// Returns
// -------
//
function ciniki_poma_hooks_updateDescriptions(&$ciniki, $business_id, $args) {

    //
    // Check to make sure the module is enabled
    //
    if( !isset($ciniki['business']['modules']['ciniki.poma']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.59', 'msg'=>"I'm sorry, the page you requested does not exist."));
    }

    //
    // Check to make sure object and description have been passed
    //
    if( !isset($args['object']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.60', 'msg'=>"No object specified."));
    }
    if( !isset($args['object_id']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.61', 'msg'=>"No object specified."));
    }
    if( !isset($args['description']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.62', 'msg'=>"No description specified."));
    }

    //
    // Load business settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'intlSettings');
    $rc = ciniki_businesses_intlSettings($ciniki, $business_id);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    //
    // Get the list of items
    //
    $strsql = "SELECT id, description "
        . "FROM ciniki_poma_customer_items "
        . "WHERE ciniki_poma_customer_items.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "AND ciniki_poma_customer_items.object = '" . ciniki_core_dbQuote($ciniki, $args['object']) . "' "
        . "AND ciniki_poma_customer_items.object_id = '" . ciniki_core_dbQuote($ciniki, $args['object_id']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['rows']) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
        foreach($rc['rows'] as $row) {
            if( $row['description'] != $args['description'] ) { 
                $rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.poma.customeritem', $row['id'], array('description'=>$args['description']), 0x04);
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
            }
        }
    }

    return array('stat'=>'ok');
}
?>
