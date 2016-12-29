<?php
//
// Description
// -----------
// This function will process api requests for web.
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure.
// business_id:     The ID of the business to get poma request for.
//
// args:            The possible arguments for posts
//
//
// Returns
// -------
//
function ciniki_poma_web_processAPI(&$ciniki, $settings, $business_id, $args) {

    //
    // Check to make sure the module is enabled
    //
    if( !isset($ciniki['business']['modules']['ciniki.poma']) ) {
        return array('stat'=>'404', 'err'=>array('code'=>'ciniki.poma.14', 'msg'=>"I'm sorry, the page you requested does not exist."));
    }

    //
    // Check to make sure customer is logged in
    //
    if( !isset($ciniki['session']['customer']['id']) || $ciniki['session']['customer']['id'] < 1 ) {
        return array('stat'=>'404', 'err'=>array('code'=>'ciniki.poma.35', 'msg'=>"I'm sorry, but you must be logged in to do that.")); 
    }

    //
    // favItemAdd/object/object_id
    //
    if( isset($args['uri_split'][2]) && $args['uri_split'][0] == 'favItemAdd' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'favItemAdd');
        $rc = ciniki_poma_favItemAdd($ciniki, $business_id, array(
            'object'=>$args['uri_split'][1],
            'object_id'=>$args['uri_split'][2],
            'customer_id'=>$ciniki['session']['customer']['id'],
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'500', 'err'=>array('code'=>'ciniki.poma.36', 'msg'=>'Unable to add item as a favourite.'));
        }
        return array('stat'=>'ok');
    }

    //
    // favItemDelete/object/object_id
    //
    elseif( isset($args['uri_split'][2]) && $args['uri_split'][0] == 'favItemDelete' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'favItemDelete');
        $rc = ciniki_poma_favItemDelete($ciniki, $business_id, array(
            'object'=>$args['uri_split'][1],
            'object_id'=>$args['uri_split'][2],
            'customer_id'=>$ciniki['session']['customer']['id'],
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'500', 'err'=>array('code'=>'ciniki.poma.37', 'msg'=>'Unable to add item as a favourite.'));
        }
        return array('stat'=>'ok');
    }
    
    //
    // orderItemUpdate/object/object_id
    //
    elseif( isset($args['uri_split'][2]) && $args['uri_split'][0] == 'orderItemUpdate' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'apiOrderItemUpdate');
        $rc = ciniki_poma_web_apiOrderItemUpdate($ciniki, $settings, $business_id, array(
            'object'=>$args['uri_split'][1],
            'object_id'=>$args['uri_split'][2],
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'500', 'err'=>array('code'=>'ciniki.poma.33', 'msg'=>'Unable to add item as a favourite.'));
        }
        return array('stat'=>'ok');
    }
    
    //
    // repeatItemUpdate/object/object_id
    //
    elseif( isset($args['uri_split'][2]) && $args['uri_split'][0] == 'repeatItemUpdate' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'apiRepeatItemUpdate');
        $rc = ciniki_poma_web_apiRepeatItemUpdate($ciniki, $settings, $business_id, array(
            'object'=>$args['uri_split'][1],
            'object_id'=>$args['uri_split'][2],
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'500', 'err'=>array('code'=>'ciniki.poma.34', 'msg'=>'Unable to add item as a favourite.'));
        }
        return array('stat'=>'ok');
    }
    
    return array('stat'=>'ok');
}
?>
