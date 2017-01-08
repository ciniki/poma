<?php
//
// Description
// -----------
// This function will process a web request for past orders.
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure.
// business_id:     The ID of the business to get events for.
//
// args:            The possible arguments for posts
//
//
// Returns
// -------
//
function ciniki_poma_web_processRequestPast(&$ciniki, $settings, $business_id, $args) {

    
    $page = array(
        'title'=>$args['page_title'],
        'breadcrumbs'=>$args['breadcrumbs'],
        'blocks'=>array(),
        'submenu'=>array(),
        );

    $page['breadcrumbs'][] = array('name'=>'Past', 'url'=>$args['base_url'] . '/past');

    return array('stat'=>'ok', 'page'=>$page);
}
?>