<?php
//
// Description
// -----------
// This function will process a web request for queued items.
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
function ciniki_poma_web_processRequestQueue(&$ciniki, $settings, $business_id, $args) {

    
    $page = array(
        'title'=>$args['page_title'],
        'breadcrumbs'=>$args['breadcrumbs'],
        'blocks'=>array(),
        'submenu'=>array(),
        );

    $page['breadcrumbs'][] = array('name'=>'Queue', 'url'=>$args['base_url'] . '/queue');

    $date_format = 'M j, Y';

    $api_queue_update = 'ciniki/poma/queueObjectUpdate/';

    //
    // Get the list of active queued items for the customer
    //
    $strsql = "SELECT ciniki_poma_queued_items.id, "
        . "ciniki_poma_queued_items.object, "
        . "ciniki_poma_queued_items.object_id, "
        . "ciniki_poma_queued_items.description, "
        . "ciniki_poma_queued_items.quantity "
        . "FROM ciniki_poma_queued_items "
        . "WHERE ciniki_poma_queued_items.customer_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['customer']['id']) . "' "
        . "AND ciniki_poma_queued_items.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "AND ciniki_poma_queued_items.status = 40 "
        . "ORDER BY ciniki_poma_queued_items.description "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('id', 'object', 'object_id', 'name'=>'description', 'queue_quantity'=>'quantity')),
        ));
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }
    $ordered = array();
    if( isset($rc['items']) && count($rc['items']) > 0 ) {
        $ordered = $rc['items'];
    }
        
    //
    // Get the list of active queued items for the customer
    //
    $strsql = "SELECT ciniki_poma_queued_items.id, "
        . "ciniki_poma_queued_items.object, "
        . "ciniki_poma_queued_items.object_id, "
        . "ciniki_poma_queued_items.description, "
        . "ciniki_poma_queued_items.quantity "
        . "FROM ciniki_poma_queued_items "
        . "WHERE ciniki_poma_queued_items.customer_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['customer']['id']) . "' "
        . "AND ciniki_poma_queued_items.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "AND ciniki_poma_queued_items.status = 10 "
        . "ORDER BY ciniki_poma_queued_items.description "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('id', 'object', 'object_id', 'name'=>'description', 'queue_quantity'=>'quantity')),
        ));
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }
    $active = array();
    if( isset($rc['items']) ) {
        $active = $rc['items'];
    }

    if( count($ordered) > 0 ) {
        $page['blocks'][] = array('type'=>'orderqueue', 'size'=>'wide', 'ordered'=>'yes',
            'api_queue_update'=>$api_queue_update,
            'intro'=>"Here is the list of items from your queue on order.",
//            'pretext'=>'There are ',
//            'posttext'=>' on order for you',
            'queue'=>$ordered);
    }
    if( count($active) > 0 ) {
        $page['blocks'][] = array('type'=>'orderqueue', 'size'=>'wide',
            'title'=>(count($ordered) > 0 ? 'Queued Items' : ''),
            'api_queue_update'=>$api_queue_update,
            'intro'=>"Here is the list of items you have in your queue.",
//            'type'=>'queued',
//            'pretext'=>'You have ',
//            'posttext'=>' in your queue',
            'queue'=>$active);
    }
    if( count($active) == 0 && count($ordered) == 0 ) {
        $page['blocks'][] = array('type'=>'content', 'content'=>"You don't have any items in your queue. "
            . "To add item, browse the products and click on the cart icon to add it to your queue.");
    }

    return array('stat'=>'ok', 'page'=>$page);
}
?>
