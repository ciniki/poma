<?php
//
// Description
// -----------
// This method will return the list of Customer Items for a tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Customer Item for.
//
// Returns
// -------
//
function ciniki_poma_customerItemList($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.customerItemList');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Get the list of items
    //
    $strsql = "SELECT ciniki_poma_customer_items.id, "
        . "ciniki_poma_customer_items.parent_id, "
        . "ciniki_poma_customer_items.customer_id, "
        . "ciniki_poma_customer_items.itype, "
        . "ciniki_poma_customer_items.object, "
        . "ciniki_poma_customer_items.object_id, "
        . "ciniki_poma_customer_items.description, "
        . "ciniki_poma_customer_items.repeat_days, "
        . "ciniki_poma_customer_items.last_order_date, "
        . "ciniki_poma_customer_items.next_order_date, "
        . "ciniki_poma_customer_items.quantity, "
        . "ciniki_poma_customer_items.single_units_text, "
        . "ciniki_poma_customer_items.plural_units_text "
        . "FROM ciniki_poma_customer_items "
        . "WHERE ciniki_poma_customer_items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('id', 'parent_id', 'customer_id', 'itype', 'object', 'object_id', 'description', 'repeat_days', 'last_order_date', 'next_order_date', 'quantity', 'single_units_text', 'plural_units_text')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['items']) ) {
        $items = $rc['items'];
        $item_ids = array();
        foreach($items as $iid => $item) {
            $item_ids[] = $item['id'];
        }
    } else {
        $items = array();
        $item_ids = array();
    }

    return array('stat'=>'ok', 'items'=>$items, 'nplist'=>$item_ids);
}
?>
