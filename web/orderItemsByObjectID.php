<?php
//
// Description
// -----------
// This function returns the list of items on the current order for a customer
// This function returns the list of items that a customer has favourited, queued or put on repeat order.
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure.
// tnid:     The ID of the tenant to get poma web options for.
//
// args:            The possible arguments for posts
//
//
// Returns
// -------
//
function ciniki_poma_web_orderItemsByObjectID(&$ciniki, $tnid, $args) {

    //
    // Check to make sure the module is enabled
    //
    if( !isset($ciniki['tenant']['modules']['ciniki.poma']) ) {
        return array('stat'=>'ok', 'items'=>array());
    }

    //
    // Check to make sure customer has been passed
    //
    if( !isset($args['customer_id']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.20', 'msg'=>"Customer not logged in."));
    }

    //
    // Check for the current date_id
    //
    if( !isset($ciniki['session']['ciniki.poma']['date']['id']) ) {
        return array('stat'=>'ok', 'items'=>array());
    }

    //
    // Get any order items for the date
    //
    $strsql = "SELECT "
        . "ciniki_poma_order_items.id, "
        . "ciniki_poma_order_items.object, "
        . "ciniki_poma_order_items.object_id, "
        . "ciniki_poma_order_items.itype, "
        . "ciniki_poma_order_items.weight_units, "
        . "ciniki_poma_order_items.weight_quantity, "
        . "ciniki_poma_order_items.unit_quantity, "
        . "ciniki_poma_order_items.unit_suffix "
        . "FROM ciniki_poma_orders, ciniki_poma_order_items "
        . "WHERE ciniki_poma_orders.date_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['ciniki.poma']['date']['id']) . "' "
        . "AND ciniki_poma_orders.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND ciniki_poma_orders.customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
        . "AND ciniki_poma_orders.id = ciniki_poma_order_items.order_id "
        . "AND ciniki_poma_order_items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND ciniki_poma_order_items.parent_id = 0 "   // Don't load child items, they are only used for product baskets in foodmarket
        . "";
    if( isset($args['object']) ) {
        $strsql .= "AND ciniki_poma_order_items.object = '" . ciniki_core_dbQuote($ciniki, $args['object']) . "' ";
    } else {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.21', 'msg'=>"No object specified."));
    }
    if( isset($args['object_ids']) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteIDs');
        $strsql .= "AND ciniki_poma_order_items.object_id IN (" . ciniki_core_dbQuoteIDs($ciniki, $args['object_ids']) . ") ";
    } else {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.22', 'msg'=>"No objects specified."));
    }
    $strsql .= "ORDER BY ciniki_poma_order_items.object_id ";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'items', 'fname'=>'object_id', 
            'fields'=>array('id', 'object', 'object_id', 'itype', 'weight_units', 'weight_quantity', 'unit_quantity', 'unit_suffix')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['items']) ) {
        $items = $rc['items'];
        foreach($items as $iid => $item) {
            if( $item['itype'] == 10 ) {
                $items[$iid]['quantity'] = $item['weight_quantity'];
            } else {
                $items[$iid]['quantity'] = $item['unit_quantity'];
            }
        }
    } else {
        $items = array();
    }

    return array('stat'=>'ok', 'items'=>$items);
}
?>
