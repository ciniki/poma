<?php
//
// Description
// -----------
// This function will process a web request for upcoming orders.
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
function ciniki_poma_web_processRequestUpcoming(&$ciniki, $settings, $business_id, $args) {

    
    $page = array(
        'title'=>$args['page_title'],
        'breadcrumbs'=>$args['breadcrumbs'],
        'blocks'=>array(),
        'submenu'=>array(),
        );

    $page['breadcrumbs'][] = array('name'=>'Upcoming', 'url'=>$args['base_url']);


    return array('stat'=>'ok', 'page'=>$page);
}
?>
