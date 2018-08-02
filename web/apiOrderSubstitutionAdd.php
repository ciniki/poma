<?php
//
// Description
// -----------
// This function add a substitution item as a subitem to and existing order item.
// This was developed from foodmarket product baskets.
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
function ciniki_poma_web_apiOrderSubstitutionAdd(&$ciniki, $settings, $tnid, $args) {
    
    //
    // Check args
    //
    if( !isset($args['item_id']) || $args['item_id'] < 1 || $args['item_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.84', 'msg'=>'No item specified.'));
    }
    if( !isset($args['object']) || $args['object'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.85', 'msg'=>'No item specified.'));
    }
    if( !isset($args['object_id']) || $args['object_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.86', 'msg'=>'No item specified.'));
    }
    if( !isset($ciniki['session']['customer']['id']) || $ciniki['session']['customer']['id'] < 1 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.87', 'msg'=>'No customer specified.'));
    }


    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'orderItemLoad');

    //
    // Load the item and the list of subitems
    //
    $rc = ciniki_poma_web_orderItemLoad($ciniki, $settings, $tnid, $args);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $existing_item = (isset($rc['item']) ? $rc['item'] : array());
    $subitems = (isset($rc['subitems']) ? $rc['subitems'] : array());

    //
    // Get the requested item
    //
    list($pkg, $mod, $obj) = explode('.', $args['object']);
    $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'itemLookup');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.196', 'msg'=>'Unable to add item.'));
    }
    $fn = $rc['function_call'];
    $rc = $fn($ciniki, $tnid, $args);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['item']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.197', 'msg'=>'Unable to add item.'));
    }
    $newitem = $rc['item'];

    //
    // Add the item if available, set quantity to 1 by default
    //
    if( $newitem['unit_amount'] > $existing_item['available'] ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.90', 'msg'=>"No room left, you'll need to remove something."));
    }

    //
    // Check for inventory on limited quantity items
    //
    if( ($newitem['flags']&0x0800) == 0x0800 && $newitem['object'] != '' && isset($newitem['num_available']) ) {
        if( 1 > $newitem['num_available'] ) {
            return array('stat'=>'noavail', 'err'=>array('code'=>'ciniki.poma.187', 'msg'=>"I'm sorry, there are no more available."));
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

    $newitem['parent_id'] = $args['item_id'];
    $newitem['order_id'] = $existing_item['order_id'];
    if( $newitem['itype'] == 10 ) {
        $newitem['weight_quantity'] = 1;
    } else {
        $newitem['unit_quantity'] = 1;
    }
    $newitem['flags'] |= 0x04;
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.poma.orderitem', $newitem, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }

    //
    // Update the order totals
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderUpdateStatusBalance');
    $rc = ciniki_poma_orderUpdateStatusBalance($ciniki, $tnid, $newitem['order_id']);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }
    if( isset($rc['order']) ) {
        $order = $rc['order'];
        //
        // Update the flag to mail the order to the customer
        //
        if( ($order['flags']&0x10) == 0 ) {
            $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.poma.order', $newitem['order_id'], array('flags'=>$order['flags'] |= 0x10), 0x04);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                return $rc;
            }
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

    //
    // Load the subitems
    //
    $strsql = "SELECT ciniki_poma_order_items.id, "
        . "ciniki_poma_order_items.uuid, "
        . "ciniki_poma_order_items.description, "
        . "ciniki_poma_order_items.object, "
        . "ciniki_poma_order_items.object_id, "
        . "ciniki_poma_order_items.flags, "
        . "ciniki_poma_order_items.itype, "
        . "ciniki_poma_order_items.weight_units, "
        . "ciniki_poma_order_items.weight_quantity, "
        . "ciniki_poma_order_items.unit_quantity, "
        . "ciniki_poma_order_items.unit_suffix, "
        . "ciniki_poma_order_items.unit_amount, "
        . "ciniki_poma_order_items.total_amount "
        . "FROM ciniki_poma_order_items "
        . "WHERE ciniki_poma_order_items.parent_id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
        . "AND ciniki_poma_order_items.order_id = '" . ciniki_core_dbQuote($ciniki, $existing_item['order_id']) . "' "
        . "AND ciniki_poma_order_items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "ORDER BY ciniki_poma_order_items.description "
        . "";
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'subitems', 'fname'=>'id', 'fields'=>array('id', 'uuid', 'description', 'object', 'object_id', 
            'flags', 'itype', 'weight_units', 'weight_quantity', 'unit_quantity', 'unit_suffix', 'unit_amount', 'total_amount')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['subitems']) ) {
        $subitems = array();
    } else {
        $subitems = $rc['subitems'];
    }

    //
    // Get the available amount remaining
    //
    $existing_item['curtotal'] = 0;
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'orderItemFormat');
    foreach($subitems as $iid => $itm) {
        $rc = ciniki_poma_web_orderItemFormat($ciniki, $settings, $tnid, $itm);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $subitems[$iid] = $rc['item'];
        $existing_item['subitems'][] = $rc['item'];
        $existing_item['curtotal'] = bcadd($existing_item['curtotal'], bcmul($subitems[$iid]['unit_amount'], $subitems[$iid]['quantity'], 6), 2);
    }
    $existing_item['available'] = bcsub($existing_item['limit'], $existing_item['curtotal'], 2);

    //
    // Get the list of available items from modules
    //
    list($pkg, $mod, $obj) = explode('.', $existing_item['object']);
    $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'itemSubstitutions');
    $existing_item['subs'] = array();
    if( $rc['stat'] == 'ok' ) {
        $fn = $rc['function_call'];
        $args = array(
            'date_id'=>$existing_item['order_date_id'],
            'object'=>$existing_item['object'],
            'object_id'=>$existing_item['object_id'],
            );
        $rc = $fn($ciniki, $tnid, $args);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['substitutions']) ) {
            foreach($rc['substitutions'] as $sid => $sub) {
                $found = 0;
                foreach($existing_item['subitems'] as $itm) {
                    if( $sub['object'] == $itm['object'] && $sub['object_id'] == $itm['object_id'] ) {
                        $found = 1;
                    }
                }
                if( $found == 0 ) {
                    $existing_item['subs'][] = $sub;
                }
            }
        }
    }

    $item = array(
        'available'=>$existing_item['available'],
        'curtotal'=>$existing_item['curtotal'],
        'limit'=>$existing_item['limit'],
        'subitems'=>$existing_item['subitems'],
        'subs'=>$existing_item['subs'],
        );

    return array('stat'=>'ok', 'item'=>$item);
}
?>
