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
        'year'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Year'),
        'month'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Month'),
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
    // Get the first date of orders
    //
    $strsql = "SELECT MIN(YEAR(order_date)) AS first_order_year, MAX(YEAR(order_date)) AS last_order_year "
        . "FROM ciniki_poma_order_dates "
        . "ORDER BY order_date "
        . "LIMIT 1 ";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.foodmarket', 'date');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.foodmarket.149', 'msg'=>'Unable to load date', 'err'=>$rc['err']));
    }
    if( isset($rc['date']['first_order_year']) ) {
        $first_order_year = $rc['date']['first_order_year'];
        $last_order_year = $rc['date']['first_order_year'];
    } else {
        $first_order_year = date('Y');
        $last_order_year = date('Y');
    }

    if( !isset($args['year']) || $args['year'] == '' ) {
        $args['year'] = $last_order_year;
    }

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
            . "WHERE ciniki_poma_order_dates.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' ";
        if( isset($args['year']) && $args['year'] != '' ) {
            $strsql .= "AND YEAR(ciniki_poma_order_dates.order_date) = '" . ciniki_core_dbQuote($ciniki, $args['year']) . "' ";
            if( isset($args['month']) && $args['month'] != '' && $args['month'] != '0' ) {
                $strsql .= "AND MONTH(ciniki_poma_order_dates.order_date) = '" . ciniki_core_dbQuote($ciniki, $args['month']) . "' ";
            }
        }
        $strsql .= "GROUP BY ciniki_poma_order_dates.id "
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

    return array('stat'=>'ok', 'dates'=>$dates, 'first_year'=>$first_order_year, 'nplist'=>$date_ids);
}
?>
