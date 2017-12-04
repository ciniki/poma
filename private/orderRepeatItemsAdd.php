<?php
//
// Description
// -----------
// This function will lock any dates for a tenant that autolock has been specified.
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
function ciniki_poma_orderRepeatItemsAdd(&$ciniki, $tnid, $args) {

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
    // Check for any repeat items for the customer that should be added to this order for the date
    //
    $strsql = "SELECT ciniki_poma_customer_items.id, "
        . "ciniki_poma_customer_items.object, "
        . "ciniki_poma_customer_items.object_id, "
        . "ciniki_poma_customer_items.description, "
        . "ciniki_poma_customer_items.repeat_days, "
        . "ciniki_poma_customer_items.quantity "
        . "FROM ciniki_poma_customer_items "
        . "WHERE ciniki_poma_customer_items.customer_id = '". ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
        . "AND ciniki_poma_customer_items.itype = 40 "
        . "AND next_order_date <= '" . ciniki_core_dbQuote($ciniki, $order_date) . "' "
        . "AND ciniki_poma_customer_items.tnid = '". ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'items', 'fname'=>'id', 'fields'=>array('id', 'object', 'object_id', 'description', 'repeat_days', 'quantity')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['items']) ) {
        $items = $rc['items'];
    } else {
        //
        // Nothing to process, return
        //
        return array('stat'=>'ok');
    }

    //
    // Get the items from the order to make sure it's not already on the order
    //
    $strsql = "SELECT ciniki_poma_order_items.id, "
        . "ciniki_poma_order_items.object, "
        . "ciniki_poma_order_items.object_id, "
        . "ciniki_poma_order_items.itype, "
        . "ciniki_poma_order_items.weight_quantity, "
        . "ciniki_poma_order_items.unit_quantity "
        . "FROM ciniki_poma_order_items "
        . "WHERE ciniki_poma_order_items.order_id = '" . ciniki_core_dbQuote($ciniki, $order['id']) . "' "
        . "AND ciniki_poma_order_items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'objects', 'fname'=>'object', 'fields'=>array('object')),
        array('container'=>'ids', 'fname'=>'object_id', 'fields'=>array('id', 'object', 'object_id', 'itype', 'weight_quantity', 'unit_quantity')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['objects']) ) {
        $objects = $rc['objects'];
    } else {
        $objects = array();
    }
    
    //
    // Set the line number to next available
    //
    $line_number = $order['max_line_number'] + 1;

    $order_updated = 'no';
    $added_items = array();
    foreach($items as $item) {  
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

            //
            // Update the last order date, and next order date
            //
            $ndt = clone($odt);
            $ndt->add(new DateInterval('P' . $item['repeat_days'] . 'D'));
            $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.poma.customeritem', $item['id'], array(
                'last_order_date'=>$order_date, 
                'next_order_date'=>$ndt->format('Y-m-d'),
                ), 0x04);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.116', 'msg'=>'Unable to update last order date', 'err'=>$rc['err']));
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

    //
    // Check if order should be emailed
    //
    if( $order_updated == 'yes' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'emailRepeatsAdded');
        $rc = ciniki_poma_emailRepeatsAdded($ciniki, $tnid, $order['id'], $added_items);
        if( $rc['stat'] != 'ok' && $rc['stat'] != 'warn' ) {
            return $rc;
        }
    }

    return array('stat'=>'ok');
}
?>
