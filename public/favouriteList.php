<?php
//
// Description
// -----------
// This method will return a list of favourites for business.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:        The ID of the business to get Order Date for.
//
// Returns
// -------
//
function ciniki_poma_favouriteList($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'),
        'customer_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Customer ID'),
        'customers'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Customers'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to business_id as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['business_id'], 'ciniki.poma.favouriteList');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load business settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'intlSettings');
    $rc = ciniki_businesses_intlSettings($ciniki, $args['business_id']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'timeFormat');
    $time_format = ciniki_users_timeFormat($ciniki, 'php');

    $dt = new DateTime('now', new DateTimeZone('UTC'));

    //
    // Load maps
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'maps');
    $rc = ciniki_poma_maps($ciniki, $args['business_id']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $maps = $rc['maps'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rsp = array('stat'=>'ok');

    //
    // Get the list of favourites for a customer and the number of times they've ordered them.
    //
    if( isset($args['customer_id']) && $args['customer_id'] > 0 ) {
        $strsql = "SELECT ciniki_poma_customer_items.id, "
            . "ciniki_poma_customer_items.description, "
            . "IFNULL(COUNT(ciniki_poma_order_items.object_id), 0) AS num_orders "
            . "FROM ciniki_poma_customer_items "
            . "LEFT JOIN ciniki_poma_order_items ON ("
                . "ciniki_poma_customer_items.object = ciniki_poma_order_items.object "
                . "AND ciniki_poma_customer_items.object_id = ciniki_poma_order_items.object_id "
                . "AND ciniki_poma_order_items.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
                . ") "
            . "WHERE ciniki_poma_customer_items.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
            . "AND ciniki_poma_customer_items.customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
            . "AND ciniki_poma_customer_items.itype = 20 "
            . "GROUP BY ciniki_poma_customer_items.id "
            . "ORDER BY ciniki_poma_customer_items.description "
            . "";
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'items', 'fname'=>'id', 'fields'=>array('id', 'description', 'num_orders')),
            ));
        if( $rc['stat'] != 'ok' ) { 
            return $rc;
        }
        if( !isset($rc['items']) ) {
            $rsp['customer_favourites'] = array();
        } else {
            $rsp['customer_favourites'] = $rc['items'];
        }
    } else {
        $strsql = "SELECT CONCAT_WS(ciniki_poma_customer_items.object, ciniki_poma_customer_items.object_id, '-') AS oid, "
            . "ciniki_poma_customer_items.description, "
            . "COUNT(ciniki_poma_customer_items.customer_id) AS num_customers "
            . "FROM ciniki_poma_customer_items "
            . "WHERE ciniki_poma_customer_items.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
            . "AND ciniki_poma_customer_items.itype = 20 "
            . "GROUP BY oid "
            . "ORDER BY ciniki_poma_customer_items.description "
            . "";
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'items', 'fname'=>'oid', 'fields'=>array('id'=>'oid', 'description', 'num_customers')),
            ));
        if( $rc['stat'] != 'ok' ) { 
            return $rc;
        }
        if( !isset($rc['items']) ) {
            $rsp['favourite_items'] = array();
        } else {
            $rsp['favourite_items'] = $rc['items'];
        }
    }

    //
    // Get the list of customers with favourites
    //
    if( isset($args['customers']) && $args['customers'] == 'yes' ) {
        $strsql = "SELECT ciniki_poma_customer_items.customer_id, "
            . "ciniki_customers.display_name, "
            . "COUNT(ciniki_poma_customer_items.id) AS num_items "
            . "FROM ciniki_poma_customer_items "
            . "LEFT JOIN ciniki_customers ON ("
                . "ciniki_poma_customer_items.customer_id = ciniki_customers.id "
                . "AND ciniki_customers.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
                . ") "
            . "WHERE ciniki_poma_customer_items.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
            . "AND ciniki_poma_customer_items.itype = 20 "
            . "GROUP BY customer_id "
            . "ORDER BY ciniki_customers.display_name "
            . "";
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'customers', 'fname'=>'customer_id', 'fields'=>array('id'=>'customer_id', 'display_name', 'num_items')),
            ));
        if( $rc['stat'] != 'ok' ) { 
            return $rc;
        }
        if( !isset($rc['customers']) ) {
            $rsp['customers'] = array();
        } else {
            $rsp['customers'] = $rc['customers'];
        }
    }

    return $rsp;
}
?>
