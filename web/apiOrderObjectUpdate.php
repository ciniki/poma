<?php
//
// Description
// -----------
// This function will update an orders item with new quantity. If required, an order will be created
// based on the current session date.
//
// Arguments
// ---------
// ciniki:
// tnid:                 The tenant ID to check the session user against.
// method:                      The requested method.
//
// Returns
// -------
// <rsp stat='ok' />
//
function ciniki_poma_web_apiOrderObjectUpdate(&$ciniki, $settings, $tnid, $args) {
    
    //
    // Check args
    //
    if( !isset($args['object']) || $args['object'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.38', 'msg'=>'No item specified.'));
    }
    if( !isset($args['object_id']) || $args['object_id'] < 1 || $args['object_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.39', 'msg'=>'No item specified.'));
    }
    if( !isset($ciniki['session']['customer']['id']) || $ciniki['session']['customer']['id'] < 1 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.40', 'msg'=>'No customer specified.'));
    }
    if( !isset($ciniki['session']['ciniki.poma']['date']['id']) || $ciniki['session']['ciniki.poma']['date']['id'] < 1 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.43', 'msg'=>'Unable to add item to order'));
    }
    $args['date_id'] = $ciniki['session']['ciniki.poma']['date']['id'];

    //
    // Check to make sure the date is still open
    //
    $strsql = "SELECT status "
        . "FROM ciniki_poma_order_dates "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['date_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.238', 'msg'=>'Unable to load order date', 'err'=>$rc['err']));
    }
    if( !isset($rc['date']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.239', 'msg'=>'Unable to find requested order date'));
    }
    if( $rc['date']['status'] < 10 || $rc['date']['status'] > 30 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.223', 'msg'=>'Order date is closed.'));
    }
    

    //
    // The list of fields the customer is allowed to change
    //
    $fields = array('unit_quantity', 'weight_quantity');

    //
    // Get the details for the item
    //
    list($pkg, $mod, $obj) = explode('.', $args['object']);
    $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'itemLookup');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.41', 'msg'=>'Unable to add favourite.'));
    }
    $fn = $rc['function_call'];
    $rc = $fn($ciniki, $tnid, $args);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['item']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.42', 'msg'=>'Unable to add favourite.'));
    }
    $item = $rc['item'];

    //
    // Decide how the quantity is applied
    //
    if( isset($_GET['quantity']) ) {
        if( $item['itype'] == 10 ) {
            $item['weight_quantity'] = $_GET['quantity'];
        } else {
            $item['unit_quantity'] = $_GET['quantity'];
        }
    }

    //
    // Start a transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.poma');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    
    //
    // Check if there is already an order
    //
    $strsql = "SELECT ciniki_poma_orders.id, "
        . "ciniki_poma_orders.flags, "
        . "ciniki_poma_orders.status, "
        . "IFNULL(MAX(line_number), 0) AS max_line_number "
        . "FROM ciniki_poma_orders "
        . "LEFT JOIN ciniki_poma_order_items ON ("
            . "ciniki_poma_orders.id = ciniki_poma_order_items.order_id "
            . "AND ciniki_poma_order_items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE ciniki_poma_orders.date_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['ciniki.poma']['date']['id']) . "' "
        . "AND ciniki_poma_orders.customer_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['customer']['id']) . "' "
        . "AND ciniki_poma_orders.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "GROUP BY ciniki_poma_orders.id "
        . "ORDER BY status ASC "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['order']) ) {
        $order = $rc['order'];
    } elseif( isset($rc['rows']) && count($rc['rows']) > 0 ) {
        $order = array_shift($rc['rows']);
    }
    if( isset($order) ) {
        //
        // Check to make sure order is still open for changes
        //
        if( $order['status'] > 10 ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.68', 'msg'=>'Your order is closed, no more changes can be made.'));
        }
    } else {
        //
        // Add new order
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'newOrderForDate');
        $rc = ciniki_poma_newOrderForDate($ciniki, $tnid, array(
            'customer_id'=>$ciniki['session']['customer']['id'],
            'date_id'=>$ciniki['session']['ciniki.poma']['date']['id'],
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $order = $rc['order'];
        $order['max_line_number'] = 0;
    }

    //
    // Check if the item already exists
    //
    $strsql = "SELECT ciniki_poma_order_items.id, "
        . "ciniki_poma_order_items.uuid, "
        . "ciniki_poma_order_items.weight_quantity, "
        . "ciniki_poma_order_items.unit_quantity "
        . "FROM ciniki_poma_order_items "
        . "WHERE ciniki_poma_order_items.order_id = '" . ciniki_core_dbQuote($ciniki, $order['id']) . "' "
        . "AND ciniki_poma_order_items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND object = '" . ciniki_core_dbQuote($ciniki, $args['object']) . "' "
        . "AND object_id = '" . ciniki_core_dbQuote($ciniki, $args['object_id']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }
    
    //
    // Update the existing item if it exists
    //
    if( isset($rc['item']) ) {
        //
        // Check for inventory on limited quantity items
        //
        if( ($item['flags']&0x0800) == 0x0800 && $args['object'] != '' && isset($item['num_available']) ) {
            if( $item['itype'] == 10 ) {
                $qty_diff = bcsub($item['weight_quantity'], $rc['item']['weight_quantity'], 6);
            } else {
                $qty_diff = bcsub($item['unit_quantity'], $rc['item']['unit_quantity'], 6);
            }
            if( $qty_diff > 0 && $qty_diff > $item['num_available'] ) {
                return array('stat'=>'noavail', 'err'=>array('code'=>'ciniki.poma.236', 'msg'=>"I'm sorry, there are no more available."));
            }
        }
        $existing_item = $rc['item'];
        $update_args = array();
        foreach($fields as $field) {
            if( isset($item[$field]) && $item[$field] != $existing_item[$field] ) {
                $update_args[$field] = $item[$field];
            }
        }
        //
        // Check if item should be removed, zero quantity
        //
        if( isset($_GET['quantity']) && $_GET['quantity'] <= 0 ) {
            //
            // Remove any subitems
            //
            $strsql = "SELECT id, uuid "
                . "FROM ciniki_poma_order_items "
                . "WHERE ciniki_poma_order_items.order_id = '" . ciniki_core_dbQuote($ciniki, $order['id']) . "' "
                . "AND ciniki_poma_order_items.parent_id = '" . ciniki_core_dbQuote($ciniki, $existing_item['id']) . "' "
                . "AND ciniki_poma_order_items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                return $rc;
            }
            if( isset($rc['rows']) && count($rc['rows']) > 0 ) {
                $subitems = $rc['rows'];
                foreach($subitems as $subitem) {
                    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
                    $rc = ciniki_core_objectDelete($ciniki, $tnid, 'ciniki.poma.orderitem', $subitem['id'], $subitem['uuid'], 0x04);
                    if( $rc['stat'] != 'ok' ) {
                        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                        return $rc;
                    }
                }
            }

            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
            $rc = ciniki_core_objectDelete($ciniki, $tnid, 'ciniki.poma.orderitem', $existing_item['id'], $existing_item['uuid'], 0x04);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                return $rc;
            }
        } elseif( count($update_args) > 0 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.poma.orderitem', $existing_item['id'], $update_args, 0x04);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                return $rc;
            }
        }
    } 
    //
    // Add the item if it doesn't already exist
    //
    else {
        //
        // Check for inventory on limited quantity items
        //
        if( ($item['flags']&0x0800) == 0x0800 && $args['object'] != '' && isset($item['num_available']) ) {
            if( $item['itype'] == 10 ) {
                $qty_diff = $item['weight_quantity'];
            } else {
                $qty_diff = $item['unit_quantity'];
            }
            if( $qty_diff > 0 && $qty_diff > $item['num_available'] ) {
                return array('stat'=>'noavail', 'err'=>array('code'=>'ciniki.poma.184', 'msg'=>"I'm sorry, there are no more available."));
            }
        }
        $item['order_id'] = $order['id'];
        if( isset($order['max_line_number']) ) {
            $item['line_number'] = $order['max_line_number'] + 1;
        } else {
            $item['line_number'] = 1;
        }
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
        $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.poma.orderitem', $item, 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
            return $rc;
        }
        $parent_id = $rc['id'];

        //
        // Check for subitems
        //
        if( isset($item['subitems']) ) {
            foreach($item['subitems'] as $subitem) {
                $subitem['order_id'] = $order['id'];
                $subitem['parent_id'] = $parent_id;
                $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.poma.orderitem', $subitem, 0x04);
                if( $rc['stat'] != 'ok' ) {
                    ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                    return $rc;
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
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }

    //
    // Update the flag to mail the order to the customer
    //
    if( isset($order['flags']) && ($order['flags']&0x10) == 0 ) {
        $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.poma.order', $order['id'], array('flags'=>$order['flags'] |= 0x10), 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
            return $rc;
        }
    }

    //
    // Commit the transaction
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.poma');
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }

    return array('stat'=>'ok');
}
?>
