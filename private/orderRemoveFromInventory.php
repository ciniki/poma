<?php
//
// Description
// -----------
// This function will callout to hooks in other modules to remove the products from inventory.
// Any order items that have the inventoried (0x01) flag set.
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
function ciniki_poma_orderRemoveFromInventory(&$ciniki, $tnid, $order_id) {

    //
    // Get the order items that are inventoried
    //
    $strsql = "SELECT "
        . "items.id, "
        . "items.uuid, "
        . "items.flags, "
        . "items.object, "
        . "items.object_id, "
        . "items.code, "
        . "items.description, "
        . "items.itype, "
        . "items.weight_units, "
        . "items.weight_quantity, "
        . "items.unit_quantity "
        . "FROM ciniki_poma_order_items AS items "
        . "WHERE items.order_id = '" . ciniki_core_dbQuote($ciniki, $order_id) . "' "
        . "AND (items.flags&0x01) = 0x01 " // Inventoried items
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $items = isset($rc['rows']) ? $rc['rows'] : array();
   
    //
    // No inventoried items, return
    //
    if( count($items) == 0 ) {
        return array('stat'=>'ok');
    }

    foreach($items as $item) {
        //
        // Skip items that are not inventoried
        //
        if( ($item['flags']&0x01) != 0x01 ) {
            continue;
        }
        $qty = $item['itype'] == 10 ? $item['weight_quantity'] : $item['unit_quantity'];
        //
        // Update the inventory
        //
        list($pkg, $mod, $obj) = explode('.', $item['object']);
        $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'itemInventoryRemove');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.99', 'msg'=>'Unable to find the item.'));
        }
        $fn = $rc['function_call'];
        $rc = $fn($ciniki, $tnid, array('object'=>$item['object'], 'object_id'=>$item['object_id'], 'quantity'=>$qty));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
    }

    return array('stat'=>'ok');
}
?>
