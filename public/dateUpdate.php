<?php
//
// Description
// ===========
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_poma_dateUpdate(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'),
        'date_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Order Date'),
        'order_date'=>array('required'=>'no', 'blank'=>'no', 'type'=>'date', 'name'=>'Date'),
        'status'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Status'),
        'flags'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Options'),
        'autolock_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'Auto Lock Date'),
        'autolock_time'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'time', 'name'=>'Auto Lock Time'),
        'notices'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Notices'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];
    error_log('testing');

    //
    // Make sure this module is activated, and
    // check permission to run this function for this business
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['business_id'], 'ciniki.poma.dateUpdate');
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

    //
    // Format the display_name
    //
    if( isset($args['order_date']) ) {
        $dt = date_create_from_format('Y-m-d', $args['order_date']);
        $args['display_name'] = $dt->format('D M jS');
    }

    //
    // Get the existing order date
    //
    $strsql = "SELECT ciniki_poma_order_dates.id, "
        . "ciniki_poma_order_dates.order_date, "
        . "ciniki_poma_order_dates.display_name, "
        . "ciniki_poma_order_dates.status, "
        . "ciniki_poma_order_dates.flags, "
        . "ciniki_poma_order_dates.autolock_dt, "
        . "ciniki_poma_order_dates.autolock_dt AS autolock_date, "
        . "ciniki_poma_order_dates.autolock_dt AS autolock_time, "
        . "ciniki_poma_order_dates.notices "
        . "FROM ciniki_poma_order_dates "
        . "WHERE ciniki_poma_order_dates.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
        . "AND ciniki_poma_order_dates.id = '" . ciniki_core_dbQuote($ciniki, $args['date_id']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'dates', 'fname'=>'id', 
            'fields'=>array('order_date', 'display_name', 'status', 'flags', 'autolock_dt', 'autolock_date', 'autolock_time', 'notices'),
            'utctotz'=>array(
                'order_date'=>array('timezone'=>'UTC', 'format'=>$date_format),
                'autolock_date'=>array('timezone'=>$intl_timezone, 'format'=>$date_format),
                'autolock_time'=>array('timezone'=>$intl_timezone, 'format'=>$time_format),
                ),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.10', 'msg'=>'Order Date not found', 'err'=>$rc['err']));
    }
    if( !isset($rc['dates'][0]) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.11', 'msg'=>'Unable to find Order Date'));
    }
    $date = $rc['dates'][0];

    //
    // Parse dates
    //
    if( isset($args['autolock_date']) || isset($args['autolock_time']) ) {
        $args['autolock_dt'] = (isset($args['autolock_date']) ? $args['autolock_date'] : $date['autolock_date']) . ' ' . (isset($args['autolock_time']) ? $args['autolock_time'] : $date['autolock_time']);
        if( trim($args['autolock_dt']) != '' ) {
            $ts = strtotime($args['autolock_dt']);
            if( $ts === FALSE || $ts < 1 ) {
                $args['autolock_dt'] = '';
            } else {
                $dt = new DateTime('@'.$ts, new DateTimeZone($intl_timezone));
                if( $dt->format('Y-m-d H:i:s') != $date['autolock_dt'] ) {
                    $args['autolock_dt'] = $dt->format('Y-m-d H:i:s');
                }
            }
        }
    }

    //
    // Start transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.poma');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the Order Date in the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    $rc = ciniki_core_objectUpdate($ciniki, $args['business_id'], 'ciniki.poma.orderdate', $args['date_id'], $args, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }

    //
    // Commit the transaction
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.poma');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the last_change date in the business modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
    ciniki_businesses_updateModuleChangeDate($ciniki, $args['business_id'], 'ciniki', 'poma');

    //
    // Update the web index if enabled
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'hookExec');
    ciniki_core_hookExec($ciniki, $args['business_id'], 'ciniki', 'web', 'indexObject', array('object'=>'ciniki.poma.orderdate', 'object_id'=>$args['date_id']));

    return array('stat'=>'ok');
}
?>
