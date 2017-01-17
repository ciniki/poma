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
// business_id:                 The business ID to check the session user against.
// method:                      The requested method.
//
// Returns
// -------
// <rsp stat='ok' />
//
function ciniki_poma_web_apiOrderSubstitutionUpdate(&$ciniki, $settings, $business_id, $args) {
    
    //
    // Check args
    //
    if( !isset($args['item_id']) || $args['item_id'] < 1 || $args['item_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.73', 'msg'=>'No item specified.'));
    }
    if( !isset($args['subitem_id']) || $args['subitem_id'] < 1 || $args['subitem_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.74', 'msg'=>'No item specified.'));
    }
    if( !isset($ciniki['session']['customer']['id']) || $ciniki['session']['customer']['id'] < 1 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.75', 'msg'=>'No customer specified.'));
    }


    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'orderItemLoad');

    $rc = ciniki_poma_web_orderItemLoad($ciniki, $settings, $business_id, $args);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $existing_item = (isset($rc['item']) ? $rc['item'] : array());
    $subitems = (isset($rc['subitems']) ? $rc['subitems'] : array());

    //
    // Make the changes
    //
    if( isset($subitems[$args['subitem_id']]) ) {
        $subitem = $subitems[$args['subitem_id']];
        if( isset($_GET['quantity']) && is_numeric($_GET['quantity']) && $_GET['quantity'] != $subitem['quantity'] ) {
            //
            // Check out much will be added, and if there is space
            //
            $new_quantity = $subitem['quantity'];
            $quantity_diff = bcsub($_GET['quantity'], $subitem['quantity'], 2);
            if( $quantity_diff < 0 ) {
                $new_quantity = $_GET['quantity'];
            } else {
                $amount_diff = bcmul($subitem['unit_amount'], $quantity_diff, 2);
                if( $amount_diff < $existing_item['available'] ) {
                    $new_quantity = $_GET['quantity'];
                } else {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.77', 'msg'=>"No room left, you'll need to remove something"));
                }
            }
            if( $new_quantity < 0 ) {
                $new_quantity = 0;
            }
            //
            // Check if order needs updating
            //
            if( $new_quantity != $subitem['quantity'] ) {
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
    
                if( $subitems[$args['subitem_id']]['itype'] == 10 ) {
                    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
                    $rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.poma.orderitem', $subitem['id'], array('weight_quantity'=>$new_quantity), 0x04);
                    if( $rc['stat'] != 'ok' ) {
                        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                        return $rc;
                    }
                } else {
                    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
                    $rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.poma.orderitem', $subitem['id'], array('unit_quantity'=>$new_quantity), 0x04);
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
            } else {
                // No changes made
                return array('stat'=>'ok');
            }
        }
    }

    //
    // Load the subitems
    //
    $strsql = "SELECT ciniki_poma_order_items.id, "
        . "ciniki_poma_order_items.uuid, "
        . "ciniki_poma_order_items.description, "
        . "ciniki_poma_order_items.object, "
        . "ciniki_poma_order_items.object_id, "
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
        . "AND ciniki_poma_order_items.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "ORDER BY ciniki_poma_order_items.description "
        . "";
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'subitems', 'fname'=>'id', 'fields'=>array('id', 'uuid', 'description', 'object', 'object_id', 
            'itype', 'weight_units', 'weight_quantity', 'unit_quantity', 'unit_suffix', 'unit_amount', 'total_amount')),
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
        $rc = ciniki_poma_web_orderItemFormat($ciniki, $settings, $business_id, $itm);
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
        $rc = $fn($ciniki, $business_id, $args);
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
