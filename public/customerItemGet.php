<?php
//
// Description
// ===========
// This method will return all the information about an customer item.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the customer item is attached to.
// item_id:          The ID of the customer item to get the details for.
//
// Returns
// -------
//
function ciniki_poma_customerItemGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'item_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Customer Item'),
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
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.customerItemGet');
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
    // Return default for new Customer Item
    //
    if( $args['item_id'] == 0 ) {
        $item = array('id'=>0,
            'parent_id'=>'0',
            'customer_id'=>'',
            'itype'=>'',
            'object'=>'',
            'object_id'=>'',
            'description'=>'',
            'repeat_days'=>'7',
            'last_order_date'=>'',
            'next_order_date'=>'',
            'quantity'=>'',
            'single_units_text'=>'',
            'plural_units_text'=>'',
        );
    }

    //
    // Get the details for an existing Customer Item
    //
    else {
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
            . "AND ciniki_poma_customer_items.id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'items', 'fname'=>'id', 
                'fields'=>array('parent_id', 'customer_id', 'itype', 'object', 'object_id', 'description', 'repeat_days', 'last_order_date', 'next_order_date', 'quantity', 'single_units_text', 'plural_units_text'),
                'utctotz'=>array('last_order_date'=>array('timezone'=>'UTC', 'format'=>$date_format),
                    'next_order_date'=>array('timezone'=>'UTC', 'format'=>$date_format)),                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.132', 'msg'=>'Customer Item not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['items'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.133', 'msg'=>'Unable to find Customer Item'));
        }
        $item = $rc['items'][0];
        $item['quantity'] = (float)$item['quantity'];
    }

    return array('stat'=>'ok', 'item'=>$item);
}
?>
