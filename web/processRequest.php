<?php
//
// Description
// -----------
// This function will process a web request for the POMA module.
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
function ciniki_poma_web_processRequest(&$ciniki, $settings, $business_id, $args) {

    //
    // Check to make sure the module is enabled
    //
    if( !isset($ciniki['business']['modules']['ciniki.poma']) ) {
        return array('stat'=>'404', 'err'=>array('code'=>'ciniki.poma.13', 'msg'=>"I'm sorry, the page you requested does not exist."));
    }

    //
    // Load any customer details, session information, settings, etc,
    //


    //
    // Decide where to direct the request
    //
    if( isset($args['module_page']) && $args['module_page'] == 'ciniki.poma.orders' 
        && isset($args['uri_split'][0]) && $args['uri_split'][0] == 'past' 
        ) {
        array_shift($args['uri_split']);
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'processRequestPast');
        $rc = ciniki_poma_web_processRequestPast($ciniki, $settings, $business_id, $args);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $page = $rc['page'];
    } 
    elseif( isset($args['module_page']) && $args['module_page'] == 'ciniki.poma.orders' 
        && isset($args['uri_split'][0]) && $args['uri_split'][0] == 'queue' 
        ) {
        array_shift($args['uri_split']);
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'processRequestQueue');
        $rc = ciniki_poma_web_processRequestQueue($ciniki, $settings, $business_id, $args);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $page = $rc['page'];
    } 
    elseif( isset($args['module_page']) && $args['module_page'] == 'ciniki.poma.orders' 
        && isset($args['uri_split'][0]) && $args['uri_split'][0] == 'standing' 
        ) {
        array_shift($args['uri_split']);
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'processRequestStanding');
        $rc = ciniki_poma_web_processRequestStanding($ciniki, $settings, $business_id, $args);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $page = $rc['page'];
    } 
    else {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'processRequestUpcoming');
        $rc = ciniki_poma_web_processRequestUpcoming($ciniki, $settings, $business_id, $args);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $page = $rc['page'];
    }

    //
    // Add the submenu
    //
    $page['submenu'] = array();
    $page['submenu']['upcoming'] = array('name'=>'Upcoming', 'url'=>$args['base_url'] . '');
    if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.poma', 0x01) ) {  
        $page['submenu']['standing'] = array('name'=>'Standing', 'url'=>$args['base_url'] . '/standing');
    }
    if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.poma', 0x02) ) {  
        $page['submenu']['queue'] = array('name'=>'Queue', 'url'=>$args['base_url'] . '/queue');
    }
    $page['submenu']['past'] = array('name'=>'Past', 'url'=>$args['base_url'] . '/past');
   

    return array('stat'=>'ok', 'page'=>$page);
}
?>