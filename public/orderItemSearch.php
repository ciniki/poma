<?php
//
// Description
// -----------
// This method searchs for a Order Items for a business.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:        The ID of the business to get Order Item for.
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
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'),
//        'order_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Order'),
        'start_needle'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Search String'),
        'limit'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Limit'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to business_id as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['business_id'], 'ciniki.poma.orderItemSearch');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load business INTL settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'intlSettings');
    $rc = ciniki_businesses_intlSettings($ciniki, $args['business_id']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

    //
    // Setup the array for the items
    //
    $items = array();

    //
    // Check for modules which have searchable items
    //
    foreach($ciniki['business']['modules'] as $module => $m) {
        list($pkg, $mod) = explode('.', $module);
        $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'itemSearch');
        if( $rc['stat'] != 'ok' ) {
            continue;
        }
        $fn = $rc['function_call'];
        $rc = $fn($ciniki, $args['business_id'], array(
            'start_needle'=>$args['start_needle'], 
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
            . "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
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
    $rc = ciniki_poma_formatItems($ciniki, $args['business_id'], $items);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $items = $rc['items'];

    return array('stat'=>'ok', 'items'=>$items);
}
?>
