<?php
//
// Description
// -----------
// This function will process a web request for standing order items.
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
function ciniki_poma_web_processRequestStanding(&$ciniki, $settings, $tnid, $args) {

    
    $page = array(
        'title'=>$args['page_title'],
        'breadcrumbs'=>$args['breadcrumbs'],
        'blocks'=>array(),
        'submenu'=>array(),
        );

    $page['breadcrumbs'][] = array('name'=>'Standing', 'url'=>$args['base_url'] . '/standing');

    $date_format = 'M j, Y';

    $api_repeat_update = 'ciniki/poma/repeatObjectUpdate/';

    //
    // Get the list of standing order items for the customer
    //
    $strsql = "SELECT ciniki_poma_customer_items.id, "
        . "ciniki_poma_customer_items.object, "
        . "ciniki_poma_customer_items.object_id, "
        . "ciniki_poma_customer_items.description, "
        . "ciniki_poma_customer_items.quantity, "
        . "ciniki_poma_customer_items.repeat_days, "
        . "ciniki_poma_customer_items.last_order_date, "
        . "ciniki_poma_customer_items.next_order_date AS repeat_next_date, "
        . "IFNULL(COUNT(ciniki_poma_order_items.object_id), 0) AS num_orders "
        . "FROM ciniki_poma_customer_items "
        . "LEFT JOIN ciniki_poma_order_items ON ("
            . "ciniki_poma_customer_items.object = ciniki_poma_order_items.object "
            . "AND ciniki_poma_customer_items.object_id = ciniki_poma_order_items.object_id "
            . "AND ciniki_poma_order_items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE ciniki_poma_customer_items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND ciniki_poma_customer_items.customer_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['customer']['id']) . "' "
        . "AND ciniki_poma_customer_items.itype = 40 "
        . "GROUP BY ciniki_poma_customer_items.id "
        . "ORDER BY ciniki_poma_customer_items.description "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('id', 'object', 'object_id', 'name'=>'description', 'repeat_quantity'=>'quantity', 'last_order_date', 'repeat_next_date', 'repeat_days'),
            'utctotz'=>array(
                'last_order_date'=>array('format'=>$date_format, 'timezone'=>'UTC'),
                'repeat_next_date'=>array('format'=>$date_format, 'timezone'=>'UTC'),
            )),
        ));
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }
    if( !isset($rc['items']) ) {
        $page['blocks'][] = array('type'=>'content', 'content'=>"You don't have any items in your standing order. "
            . "To add item, browse the products and click on the cart icon to add it to your standing order.");
    } else {
        $page['blocks'][] = array('type'=>'content', 'content'=>"The following items are on your standing order.");
        $page['blocks'][] = array('type'=>'orderrepeats', 
            'api_repeat_update'=>$api_repeat_update,
            'repeats'=>$rc['items']);
    }

    return array('stat'=>'ok', 'page'=>$page);
}
?>
