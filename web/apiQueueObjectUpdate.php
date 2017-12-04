<?php
//
// Description
// -----------
// This function will update an orders item with new quantity. If required, an order will be created
// based on the current session date.
//
// Arguments
// ---------
// ciniki:
// tnid:                 The tenant ID to check the session user against.
// method:                      The requested method.
//
// Returns
// -------
// <rsp stat='ok' />
//
function ciniki_poma_web_apiQueueObjectUpdate(&$ciniki, $settings, $tnid, $args) {
    
    //
    // Check args
    //
    if( !isset($args['object']) || $args['object'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.154', 'msg'=>'No item specified.'));
    }
    if( !isset($args['object_id']) || $args['object_id'] < 1 || $args['object_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.158', 'msg'=>'No item specified.'));
    }
    if( !isset($ciniki['session']['customer']['id']) || $ciniki['session']['customer']['id'] < 1 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.159', 'msg'=>'No customer specified.'));
    }
    if( !isset($args['quantity']) ) {
        if( isset($_GET['quantity']) ) {
            $args['quantity'] = $_GET['quantity'];
        } else {
            $args['quantity'] = 1;
        }
    }
    $args['customer_id'] = $ciniki['session']['customer']['id'];

    //
    // Update the queued item
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'queueUpdateObject');
    $rc = ciniki_poma_queueUpdateObject($ciniki, $tnid, $args);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    return array('stat'=>'ok');
}
?>
