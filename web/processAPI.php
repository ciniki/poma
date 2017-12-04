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
// tnid:     The ID of the tenant to get poma request for.
//
// args:            The possible arguments for posts
//
//
// Returns
// -------
//
function ciniki_poma_web_processAPI(&$ciniki, $settings, $tnid, $args) {

    //
    // Check to make sure the module is enabled
    //
    if( !isset($ciniki['tenant']['modules']['ciniki.poma']) ) {
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
        return ciniki_poma_favItemAdd($ciniki, $tnid, array(
            'object'=>$args['uri_split'][1],
            'object_id'=>$args['uri_split'][2],
            'customer_id'=>$ciniki['session']['customer']['id'],
            ));
    }

    //
    // favItemDelete/object/object_id
    //
    elseif( isset($args['uri_split'][2]) && $args['uri_split'][0] == 'favItemDelete' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'favItemDelete');
        return ciniki_poma_favItemDelete($ciniki, $tnid, array(
            'object'=>$args['uri_split'][1],
            'object_id'=>$args['uri_split'][2],
            'customer_id'=>$ciniki['session']['customer']['id'],
            ));
    }
    
    //
    // orderObjectUpdate/object/object_id
    //
    elseif( isset($args['uri_split'][2]) && $args['uri_split'][0] == 'orderObjectUpdate' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'apiOrderObjectUpdate');
        return ciniki_poma_web_apiOrderObjectUpdate($ciniki, $settings, $tnid, array(
            'object'=>$args['uri_split'][1],
            'object_id'=>$args['uri_split'][2],
            ));
    }
    
    //
    // repeatObjectUpdate/object/object_id
    //
    elseif( isset($args['uri_split'][2]) && $args['uri_split'][0] == 'repeatObjectUpdate' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'apiRepeatObjectUpdate');
        return ciniki_poma_web_apiRepeatObjectUpdate($ciniki, $settings, $tnid, array(
            'object'=>$args['uri_split'][1],
            'object_id'=>$args['uri_split'][2],
            ));
    }
    
    //
    // orderItemUpdate/item_id
    //
    elseif( isset($args['uri_split'][1]) && $args['uri_split'][0] == 'orderItemUpdate' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'apiOrderItemUpdate');
        return ciniki_poma_web_apiOrderItemUpdate($ciniki, $settings, $tnid, array(
            'item_id'=>$args['uri_split'][1],
            ));
    }

    //
    // orderSubstitutionAdd/item_id/object/object_id
    //
    elseif( isset($args['uri_split'][3]) && $args['uri_split'][0] == 'orderSubstitutionAdd' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'apiOrderSubstitutionAdd');
        return ciniki_poma_web_apiOrderSubstitutionAdd($ciniki, $settings, $tnid, array(
            'item_id'=>$args['uri_split'][1],
            'object'=>$args['uri_split'][2],
            'object_id'=>$args['uri_split'][3],
            ));
    }

    //
    // orderSubstitutionUpdate/item_id/subitem_id
    //
    elseif( isset($args['uri_split'][2]) && $args['uri_split'][0] == 'orderSubstitutionUpdate' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'apiOrderSubstitutionUpdate');
        return ciniki_poma_web_apiOrderSubstitutionUpdate($ciniki, $settings, $tnid, array(
            'item_id'=>$args['uri_split'][1],
            'subitem_id'=>$args['uri_split'][2],
            ));
    }

    //
    // queueObjectUpdate/object/object_id
    //
    elseif( isset($args['uri_split'][2]) && $args['uri_split'][0] == 'queueObjectUpdate' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'apiQueueObjectUpdate');
        return ciniki_poma_web_apiQueueObjectUpdate($ciniki, $settings, $tnid, array(
            'object'=>$args['uri_split'][1],
            'object_id'=>$args['uri_split'][2],
            ));
    }
    
    return array('stat'=>'ok');
}
?>
