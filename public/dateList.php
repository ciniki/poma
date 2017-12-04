<?php
//
// Description
// -----------
// This method will return the list of Order Dates for a tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Order Date for.
//
// Returns
// -------
//
function ciniki_poma_dateList($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'upcoming'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Upcoming'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.dateList');
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
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'timeFormat');
    $time_format = ciniki_users_timeFormat($ciniki, 'php');

    $dt = new DateTime('now', new DateTimeZone('UTC'));

    //
    // Load maps
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'maps');
    $rc = ciniki_poma_maps($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $maps = $rc['maps'];

    //
    // Get the list of dates
    //
    if( isset($args['upcoming']) && $args['upcoming'] == 'yes' ) {
        $strsql = "SELECT ciniki_poma_order_dates.id, "
            . "ciniki_poma_order_dates.order_date, "
            . "ciniki_poma_order_dates.display_name, "
            . "ciniki_poma_order_dates.status, "
            . "ciniki_poma_order_dates.flags, "
            . "ciniki_poma_order_dates.autolock_dt AS autolock_date, "
            . "ciniki_poma_order_dates.autolock_dt AS autolock_time, "
            . "COUNT(ciniki_poma_orders.date_id) AS num_orders "
            . "FROM ciniki_poma_order_dates "
            . "LEFT JOIN ciniki_poma_orders ON ("
                . "ciniki_poma_order_dates.id = ciniki_poma_orders.date_id "
                . "AND ciniki_poma_orders.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE ciniki_poma_order_dates.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND ciniki_poma_order_dates.order_date >= '" . ciniki_core_dbQuote($ciniki, $dt->format('Y-m-d')) . "' "
            . "GROUP BY ciniki_poma_order_dates.id "
            . "ORDER BY ciniki_poma_order_dates.order_date ASC "
            . "";
    } else {
        $strsql = "SELECT ciniki_poma_order_dates.id, "
            . "ciniki_poma_order_dates.order_date, "
            . "ciniki_poma_order_dates.display_name, "
            . "ciniki_poma_order_dates.status, "
            . "ciniki_poma_order_dates.flags, "
            . "ciniki_poma_order_dates.autolock_dt AS autolock_date, "
            . "ciniki_poma_order_dates.autolock_dt AS autolock_time, "
            . "COUNT(ciniki_poma_orders.date_id) AS num_orders "
            . "FROM ciniki_poma_order_dates "
            . "LEFT JOIN ciniki_poma_orders ON ("
                . "ciniki_poma_order_dates.id = ciniki_poma_orders.date_id "
                . "AND ciniki_poma_orders.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE ciniki_poma_order_dates.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "GROUP BY ciniki_poma_order_dates.id "
            . "ORDER BY order_date DESC "
            . "";
    }
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'dates', 'fname'=>'id', 
            'fields'=>array('id', 'order_date', 'display_name', 'status', 'status_text'=>'status', 'flags', 'autolock_date', 'autolock_date', 'num_orders'),
            'maps'=>array('status_text'=>$maps['orderdate']['status']),
            'utctotz'=>array(
                'autolock_date'=>array('timezone'=>$intl_timezone, 'format'=>$date_format),
                'autolock_time'=>array('timezone'=>$intl_timezone, 'format'=>$time_format),
                ),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['dates']) ) {
        $dates = $rc['dates'];
        $date_ids = array();
        foreach($dates as $iid => $date) {
            $date_ids[] = $date['id'];
        }
    } else {
        $dates = array();
        $date_ids = array();
    }

    return array('stat'=>'ok', 'dates'=>$dates, 'nplist'=>$date_ids);
}
?>
