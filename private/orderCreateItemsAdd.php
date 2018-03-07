<?php
//
// Description
// -----------
// This function will create the order and add the item for a date. This is used
// when setting up a member for a season in foodmarket.
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure.
// tnid:            The ID of the tenant to get poma web options for.
//
// args:            The possible arguments for posts
//
//
// Returns
// -------
//
function ciniki_poma_orderCreateItemsAdd(&$ciniki, $tnid, $args) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');

    //
    // Get the timezone
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];


    if( isset($args['date']['id']) ) {
        $date_id = $args['date']['id'];
    }
    if( isset($args['date']['order_date']) ) {
        $order_date = $args['date']['order_date'];
    } else {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.102', 'msg'=>'No date specified.'));
    }

    $odt = new DateTime($order_date, new DateTimezone($intl_timezone));

    //
    // Check the order exists, or create one
    //
    $strsql = "SELECT ciniki_poma_orders.id, "
        . "ciniki_poma_orders.order_date, "
        . "MAX(ciniki_poma_order_items.line_number) AS max_line_number "
        . "FROM ciniki_poma_orders "
        . "LEFT JOIN ciniki_poma_order_items ON ("
            . "ciniki_poma_orders.id = ciniki_poma_order_items.order_id "
            . "AND ciniki_poma_order_items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE ciniki_poma_orders.customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
        . "AND ciniki_poma_orders.date_id = '" . ciniki_core_dbQuote($ciniki, $date_id) . "' "
        . "AND ciniki_poma_orders.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "GROUP BY ciniki_poma_orders.id "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['order']) ) {
        $order = $rc['order'];
    } elseif( isset($rc['rows'][0]) ) {
        $order = $rc['rows'][0];
    } else {
        //
        // Create a new order
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'newOrderForDate');
        $rc = ciniki_poma_newOrderForDate($ciniki, $tnid, array(
            'customer_id'=>$args['customer_id'],
            'date_id'=>$date_id,
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $order = $rc['order'];
        $order['max_line_number'] = 0;
    }

    //
    // Set the line number to next available
    //
    $line_number = $order['max_line_number'] + 1;

    $order_updated = 'no';
    $added_items = array();
    foreach($args['items'] as $item) {  
        //
        // Check the item is not already part of the order
        //
        if( !isset($objects[$item['object']]['ids'][$item['object_id']]) ) {
            //
            // Lookup the item
            //
            list($pkg, $mod, $obj) = explode('.', $item['object']);
            $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'itemLookup');
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.99', 'msg'=>'Unable to find the item.'));
            }
            $fn = $rc['function_call'];
            $rc = $fn($ciniki, $tnid, array('object'=>$item['object'], 'object_id'=>$item['object_id'], 'date_id'=>$date_id));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            if( !isset($rc['item']) ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.100', 'msg'=>'Unable to find the item.'));
            }
            $object_item = $rc['item'];
            $object_item['line_number'] = $line_number++;
            $object_item['order_id'] = $order['id'];

            if( $object_item['itype'] == 10 ) {
                $object_item['weight_quantity'] = $item['quantity'];
            } else {
                $object_item['unit_quantity'] = $item['quantity'];
            }

            //
            // Add the item to the order
            //
            $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.poma.orderitem', $object_item, 0x04);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.114', 'msg'=>'Unable to add repeat item', 'err'=>$rc['err']));
            }
            $parent_id = $rc['id'];
            $order_updated = 'yes';
            $object_item['id'] = $rc['id'];
            $object_item['quantity'] = $item['quantity'];
            $added_items[] = $object_item;

            //
            // Check for subitems
            //
            if( isset($object_item['subitems']) ) {
                foreach($object_item['subitems'] as $subitem) {
                    $subitem['order_id'] = $order['id'];
                    $subitem['parent_id'] = $parent_id;
                    $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.poma.orderitem', $subitem, 0x04);
                    if( $rc['stat'] != 'ok' ) {
                        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.115', 'msg'=>'Unable to add repeat subitem', 'err'=>$rc['err']));
                    }
                }
            }
        }
    }

    //
    // Update the order totals
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderUpdateStatusBalance');
    $rc = ciniki_poma_orderUpdateStatusBalance($ciniki, $tnid, $order['id']);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.117', 'msg'=>'Unable to update update order status', 'err'=>$rc['err']));
    }

    return array('stat'=>'ok');
}
?>
