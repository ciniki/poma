<?php
//
// Description
// -----------
// This method searchs for a Order Items for a tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Order Item for.
// start_needle:       The search string to search for.
// limit:              The maximum number of entries to return.
//
// Returns
// -------
//
function ciniki_poma_orderItemSearch($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
//        'order_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Order'),
        'start_needle'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Search String'),
        'limit'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Limit'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.orderItemSearch');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load tenant INTL settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    //
    // Prepare the search string
    //
    $uwords = explode(' ', $args['start_needle']);
    $kwords = array();
    foreach($uwords as $word) {
        if( trim($word) == '' ) {
            continue;
        }
        $kwords[] = $word;
    }
    sort($kwords);
    $keywords = implode(' ', array_unique($kwords));

    //
    // Setup the array for the items
    //
    $items = array();

    //
    // Check for modules which have searchable items
    //
    foreach($ciniki['tenant']['modules'] as $module => $m) {
        list($pkg, $mod) = explode('.', $module);
        $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'itemSearch');
        if( $rc['stat'] != 'ok' ) {
            continue;
        }
        $fn = $rc['function_call'];
        $rc = $fn($ciniki, $args['tnid'], array(
            'keywords'=>$keywords,
//            'order_id'=>$args['order_id'],
            'limit'=>$args['limit']));
        if( $rc['stat'] != 'ok' ) {
            continue;
        }
        if( isset($rc['items']) ) {
            $items = array_merge($items, $rc['items']);
        }
    }

    //
    // Check existing items in invoices, but only if owner/employee
    //
    if( count($items) == 0 ) {
/*        $strsql = "SELECT DISTINCT "    
            . "CONCAT_WS('-', ciniki_poma_order_items.description, ciniki_poma_order_items.unit_amount) AS id, "
            . "ciniki_poma_order_items.object, "
            . "ciniki_poma_order_items.object_id, "
            . "ciniki_poma_order_items.description, "
            . "ciniki_poma_order_items.itype, "
            . "ciniki_poma_order_items.weight_units, "
            . "ciniki_poma_order_items.weight_quantity, "
            . "ciniki_poma_order_items.unit_quantity, "
            . "ciniki_poma_order_items.unit_suffix, "
            . "ciniki_poma_order_items.packing_order, "
            . "ciniki_poma_order_items.unit_amount, "
            . "ciniki_poma_order_items.unit_discount_amount, "
            . "ciniki_poma_order_items.unit_discount_percentage, "
            . "ciniki_poma_order_items.taxtype_id, "
            . "ciniki_poma_order_items.notes "
            . "FROM ciniki_poma_order_items "
            . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND object = '' "
            . "AND (description LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR description LIKE ' %" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . ") "
            . "";
        if( isset($args['limit']) && $args['limit'] > 0 ) {
            $strsql .= "LIMIT " . ciniki_core_dbQuote($ciniki, $args['limit']) . " ";
        } else {
            $strsql .= "LIMIT 15 ";
        }
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryTree');
        $rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'items', 'fname'=>'id', 'name'=>'item',
                'fields'=>array('object', 'object_id', 'description', 
                    'itype', 'weight_units', 'weight_quantity', 'unit_quantity', 'unit_suffix', 'packing_order',
                    'unit_amount', 'unit_discount_amount', 'unit_discount_percentage', 'taxtype_id', 'notes')),
            ));
        if( $rc['stat'] != 'ok' ) {    
            return $rc;
        }
        if( isset($rc['items']) ) {
            $items = array_merge($rc['items'], $items);
        } */
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'formatItems');
    $rc = ciniki_poma_formatItems($ciniki, $args['tnid'], $items);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $items = $rc['items'];

    return array('stat'=>'ok', 'items'=>$items);
}
?>
