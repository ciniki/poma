<?php
//
// Description
// ===========
// This method will return all the information about an order item.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the order item is attached to.
// item_id:          The ID of the order item to get the details for.
//
// Returns
// -------
//
function ciniki_poma_orderItemGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'item_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Order Item'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.orderItemGet');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');

    //
    // Load poma maps
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'maps');
    $rc = ciniki_poma_maps($ciniki);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $maps = $rc['maps'];

    //
    // Return default for new Order Item
    //
    if( $args['item_id'] == 0 ) {
        $item = array('id'=>0,
            'order_id'=>0,
            'parent_id'=>'0',
            'line_number'=>'1',
            'flags'=>'0',
            'object'=>'',
            'object_id'=>'0',
            'code'=>'',
            'description'=>'',
            'itype'=>'30',
            'weight_units'=>'20',
            'weight_quantity'=>'',
            'unit_quantity'=>'',
            'unit_suffix'=>'',
            'packing_order'=>'10',
            'unit_amount'=>'',
            'unit_discount_amount'=>'',
            'unit_discount_percentage'=>'',
            'cdeposit_description'=>'',
            'cdeposit_amount'=>'0',
            'subtotal_amount'=>'0',
            'discount_amount'=>'0',
            'total_amount'=>'0',
            'taxtype_id'=>'0',
            'notes'=>'',
        );
    }

    //
    // Get the details for an existing Order Item
    //
    else {
        $strsql = "SELECT ciniki_poma_order_items.id, "
            . "ciniki_poma_order_items.order_id, "
            . "ciniki_poma_order_items.parent_id, "
            . "ciniki_poma_order_items.line_number, "
            . "ciniki_poma_order_items.flags, "
            . "ciniki_poma_order_items.object, "
            . "ciniki_poma_order_items.object_id, "
            . "ciniki_poma_order_items.code, "
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
            . "ciniki_poma_order_items.cdeposit_description, "
            . "ciniki_poma_order_items.cdeposit_amount, "
            . "ciniki_poma_order_items.subtotal_amount, "
            . "ciniki_poma_order_items.discount_amount, "
            . "ciniki_poma_order_items.total_amount, "
            . "ciniki_poma_order_items.taxtype_id, "
            . "ciniki_poma_order_items.notes "
            . "FROM ciniki_poma_order_items "
            . "WHERE ciniki_poma_order_items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND ciniki_poma_order_items.id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'items', 'fname'=>'id', 
                'fields'=>array('order_id', 'parent_id', 'line_number', 'flags', 'object', 'object_id', 
                    'code', 'description', 'itype', 'weight_units', 'weight_quantity', 'unit_quantity', 'unit_suffix', 
                    'packing_order', 'unit_amount', 'unit_discount_amount', 'unit_discount_percentage', 'cdeposit_description', 'cdeposit_amount',
                    'subtotal_amount', 'discount_amount', 'total_amount', 'taxtype_id', 'notes'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.66', 'msg'=>'Order Item not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['items'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.67', 'msg'=>'Unable to find Order Item'));
        }
        $item = $rc['items'][0];
        if( $item['unit_amount'] != 0 ) {
            $item['unit_amount'] = '$' . number_format($item['unit_amount'], 2);
        } else {
            $item['unit_amount'] = '';
        }
        if( $item['weight_quantity'] != 0 ) {
            $item['weight_quantity'] = (float)$item['weight_quantity'];
        } else {
            $item['weight_quantity'] = '';
        }
        if( $item['unit_quantity'] != 0 ) {
            $item['unit_quantity'] = (float)$item['unit_quantity'];
        } else {
            $item['unit_quantity'] = '';
        }
        if( $item['unit_discount_amount'] != 0 ) {
            $item['unit_discount_amount'] = '$' . number_format($item['unit_discount_amount'], 2);
        } else {
            $item['unit_discount_amount'] = '';
        }
        if( $item['unit_discount_percentage'] != 0 ) {
            $item['unit_discount_percentage'] = (float)number_format($item['unit_discount_percentage'], 2);
        } else {
            $item['unit_discount_percentage'] = '';
        }
        if( $item['cdeposit_amount'] != 0 ) {
            $item['cdeposit_amount'] = '$' . number_format($item['cdeposit_amount'], 2);
        } else {
            $item['cdeposit_amount'] = '';
        }
    }

    $rsp =  array('stat'=>'ok', 'item'=>$item, 'orderdates'=>array());

    //
    // Get the list of dates available to move this item to
    //
    if( $item['order_id'] > 0 ) {
        $strsql = "SELECT date_id "
            . "FROM ciniki_poma_orders "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $item['order_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $date_id = 0;
        if( isset($rc['order']['date_id']) ) {
            $date_id = $rc['order']['date_id'];
        }

        $dt = new DateTime('now', new DateTimezone($intl_timezone));

        $strsql = "SELECT ciniki_poma_order_dates.id, "
            . "ciniki_poma_order_dates.order_date, "
            . "ciniki_poma_order_dates.display_name, "
            . "ciniki_poma_order_dates.status, "
            . "ciniki_poma_order_dates.flags "
            . "FROM ciniki_poma_order_dates "
            . "WHERE ciniki_poma_order_dates.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND order_date >= '" . ciniki_core_dbQuote($ciniki, $dt->format('Y-m-d')) . "' "
            . "AND id <> '" . ciniki_core_dbQuote($ciniki, $date_id) . "' "
            . "ORDER BY ciniki_poma_order_dates.order_date ASC "
            . "LIMIT 15"
            . "";
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'dates', 'fname'=>'id', 'fields'=>array('id', 'order_date', 'display_name', 'status', 'flags')),
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['dates']) ) {
            $rsp['orderdates'] = $rc['dates'];
            foreach($rsp['orderdates'] as $did => $date) {
                $rsp['orderdates'][$did]['name_status'] = $date['display_name'] . ' - ' . $maps['orderdate']['status'][$date['status']];
            }
        }
    }

    return $rsp;
}
?>
