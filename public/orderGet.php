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
//
// Returns
// -------
//
function ciniki_poma_orderGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'order_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Order Item'),
        'dates'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Dates'),
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
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.orderGet');
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
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

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
    if( $args['order_id'] == 0 ) {   
        $order = array(
            );
        $order_details = array(
            );
    }

    //
    // Get the details for an existing Order Item
    //
    else {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderLoad');
        $rc = ciniki_poma_orderLoad($ciniki, $args['tnid'], $args['order_id']);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.213', 'msg'=>'', 'err'=>$rc['err']));
        }
        $order = $rc['order'];
//        error_log(print_r($rc['order'], true));
        $order['order_details'] = array(
            array('label' => 'Customer', 'value' => $order['billing_name']),
            array('label' => 'Order #', 'value' => $order['order_number']),
            array('label' => 'Date', 'value' => $order['order_date_text']),
            );
    }

    $rsp =  array('stat'=>'ok', 'order'=>$order, 'orderdates'=>array());

    //
    // Get the list of dates available to move this item to
    //
    if( isset($args['dates']) && $args['dates'] == 'yes' ) {
        $dt = new DateTime('now', new DateTimezone($intl_timezone));
        $dt->sub(new DateInterval('P14D'));

        $strsql = "SELECT id, DATE_FORMAT(order_date, '%a %b %d, %Y') AS order_date "
            . "FROM ciniki_poma_order_dates "
            . "WHERE ciniki_poma_order_dates.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND order_date >= '" . ciniki_core_dbQuote($ciniki, $dt->format('Y-m-d')) . "' "
            . "AND id <> '" . ciniki_core_dbQuote($ciniki, $order['date_id']) . "' "
            . "ORDER BY ciniki_poma_order_dates.order_date ASC "
            . "";
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'dates', 'fname'=>'id', 'fields'=>array('id', 'order_date')),
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $rsp['dates'] = isset($rc['dates']) ? $rc['dates'] : array();
    }

    return $rsp;
}
?>
