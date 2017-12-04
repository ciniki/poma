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
// tnid:     The ID of the tenant to get events for.
//
// args:            The possible arguments for posts
//
//
// Returns
// -------
//
function ciniki_poma_web_processRequestPast(&$ciniki, $settings, $tnid, $args) {

    
    $page = array(
        'title'=>$args['page_title'],
        'breadcrumbs'=>$args['breadcrumbs'],
        'blocks'=>array(),
        'submenu'=>array(),
        );

    $page['breadcrumbs'][] = array('name'=>'Past', 'url'=>$args['base_url'] . '/past');

    $page['blocks'][] = array('type'=>'content', 'size'=>'wide', 'content'=>'Coming soon...');

    return array('stat'=>'ok', 'page'=>$page);
}
?>
