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
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'date_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Order Date'),
        'order_date'=>array('required'=>'no', 'blank'=>'no', 'type'=>'date', 'name'=>'Date'),
        'status'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Status'),
        'flags'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Options'),
        'open_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'Auto Open Date'),
        'open_time'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'time', 'name'=>'Auto Open Time'),
        'repeats_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'Repeat Date'),
        'repeats_time'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'time', 'name'=>'Repeat Time'),
        'autolock_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'Auto Lock Date'),
        'autolock_time'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'time', 'name'=>'Auto Lock Time'),
        'lockreminder_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'Auto Lock Date'),
        'lockreminder_time'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'time', 'name'=>'Auto Lock Time'),
        'pickupreminder_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'Pickup Reminder Date'),
        'pickupreminder_time'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'time', 'name'=>'Pickup Reminder Time'),
        'pickupstart_time'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'time', 'name'=>'Pickup Start Time'),
        'pickupend_time'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'time', 'name'=>'Pickup End Time'),
        'notices'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Notices'),
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
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.dateUpdate');
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
        . "ciniki_poma_order_dates.open_dt, "
        . "ciniki_poma_order_dates.open_dt AS open_date, "
        . "ciniki_poma_order_dates.open_dt AS open_time, "
        . "ciniki_poma_order_dates.repeats_dt, "
        . "ciniki_poma_order_dates.repeats_dt AS repeats_date, "
        . "ciniki_poma_order_dates.repeats_dt AS repeats_time, "
        . "ciniki_poma_order_dates.autolock_dt, "
        . "ciniki_poma_order_dates.autolock_dt AS autolock_date, "
        . "ciniki_poma_order_dates.autolock_dt AS autolock_time, "
        . "ciniki_poma_order_dates.lockreminder_dt, "
        . "ciniki_poma_order_dates.lockreminder_dt AS lockreminder_date, "
        . "ciniki_poma_order_dates.lockreminder_dt AS lockreminder_time, "
        . "ciniki_poma_order_dates.pickupreminder_dt, "
        . "ciniki_poma_order_dates.pickupreminder_dt AS pickupreminder_date, "
        . "ciniki_poma_order_dates.pickupreminder_dt AS pickupreminder_time, "
        . "ciniki_poma_order_dates.pickupstart_dt, "
        . "ciniki_poma_order_dates.pickupstart_dt AS pickupstart_date, "
        . "ciniki_poma_order_dates.pickupstart_dt AS pickupstart_time, "
        . "ciniki_poma_order_dates.pickupend_dt, "
        . "ciniki_poma_order_dates.pickupend_dt AS pickupend_date, "
        . "ciniki_poma_order_dates.pickupend_dt AS pickupend_time, "
        . "ciniki_poma_order_dates.notices "
        . "FROM ciniki_poma_order_dates "
        . "WHERE ciniki_poma_order_dates.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND ciniki_poma_order_dates.id = '" . ciniki_core_dbQuote($ciniki, $args['date_id']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'dates', 'fname'=>'id', 
            'fields'=>array('order_date', 'display_name', 'status', 'flags', 
                'open_dt', 'open_date', 'open_time', 
                'repeats_dt', 'repeats_date', 'repeats_time', 
                'autolock_dt', 'autolock_date', 'autolock_time', 
                'lockreminder_dt', 'lockreminder_date', 'lockreminder_time', 
                'pickupreminder_dt', 'pickupreminder_date', 'pickupreminder_time', 
                'pickupstart_dt', 'pickupstart_date', 'pickupstart_time', 
                'pickupend_dt', 'pickupend_date', 'pickupend_time', 
                'notices'),
            'utctotz'=>array(
                'order_date'=>array('timezone'=>'UTC', 'format'=>$date_format),
                'open_date'=>array('timezone'=>$intl_timezone, 'format'=>$date_format),
                'open_time'=>array('timezone'=>$intl_timezone, 'format'=>$time_format),
                'repeats_date'=>array('timezone'=>$intl_timezone, 'format'=>$date_format),
                'repeats_time'=>array('timezone'=>$intl_timezone, 'format'=>$time_format),
                'autolock_date'=>array('timezone'=>$intl_timezone, 'format'=>$date_format),
                'autolock_time'=>array('timezone'=>$intl_timezone, 'format'=>$time_format),
                'lockreminder_date'=>array('timezone'=>$intl_timezone, 'format'=>$date_format),
                'lockreminder_time'=>array('timezone'=>$intl_timezone, 'format'=>$time_format),
                'pickupreminder_date'=>array('timezone'=>$intl_timezone, 'format'=>$date_format),
                'pickupreminder_time'=>array('timezone'=>$intl_timezone, 'format'=>$time_format),
                'pickupstart_date'=>array('timezone'=>$intl_timezone, 'format'=>$date_format),
                'pickupstart_time'=>array('timezone'=>$intl_timezone, 'format'=>$time_format),
                'pickupend_date'=>array('timezone'=>$intl_timezone, 'format'=>$date_format),
                'pickupend_time'=>array('timezone'=>$intl_timezone, 'format'=>$time_format),
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
    if( isset($args['open_date']) || isset($args['open_time']) ) {
        $args['open_dt'] = (isset($args['open_date']) ? $args['open_date'] : $date['open_date']) . ' ' . (isset($args['open_time']) ? $args['open_time'] : $date['open_time']);
        if( trim($args['open_dt']) != '' ) {
            $ts = strtotime($args['open_dt']);
            if( $ts === FALSE || $ts < 1 ) {
                $args['open_dt'] = '';
            } else {
                $dt = new DateTime('@'.$ts, new DateTimeZone($intl_timezone));
                if( $dt->format('Y-m-d H:i:s') != $date['open_dt'] ) {
                    $args['open_dt'] = $dt->format('Y-m-d H:i:s');
                }
            }
        }
    }
    if( isset($args['repeats_date']) || isset($args['repeats_time']) ) {
        $args['repeats_dt'] = (isset($args['repeats_date']) ? $args['repeats_date'] : $date['repeats_date']) . ' ' . (isset($args['repeats_time']) ? $args['repeats_time'] : $date['repeats_time']);
        if( trim($args['repeats_dt']) != '' ) {
            $ts = strtotime($args['repeats_dt']);
            if( $ts === FALSE || $ts < 1 ) {
                $args['repeats_dt'] = '';
            } else {
                $dt = new DateTime('@'.$ts, new DateTimeZone($intl_timezone));
                if( $dt->format('Y-m-d H:i:s') != $date['repeats_dt'] ) {
                    $args['repeats_dt'] = $dt->format('Y-m-d H:i:s');
                }
            }
        }
    }
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
    if( isset($args['lockreminder_date']) || isset($args['lockreminder_time']) ) {
        $args['lockreminder_dt'] = (isset($args['lockreminder_date']) ? $args['lockreminder_date'] : $date['lockreminder_date']) . ' ' . (isset($args['lockreminder_time']) ? $args['lockreminder_time'] : $date['lockreminder_time']);
        if( trim($args['lockreminder_dt']) != '' ) {
            $ts = strtotime($args['lockreminder_dt']);
            if( $ts === FALSE || $ts < 1 ) {
                $args['lockreminder_dt'] = '';
            } else {
                $dt = new DateTime('@'.$ts, new DateTimeZone($intl_timezone));
                if( $dt->format('Y-m-d H:i:s') != $date['lockreminder_dt'] ) {
                    $args['lockreminder_dt'] = $dt->format('Y-m-d H:i:s');
                }
            }
        }
    }
    if( isset($args['pickupreminder_date']) || isset($args['pickupreminder_time']) ) {
        $args['pickupreminder_dt'] = (isset($args['pickupreminder_date']) ? $args['pickupreminder_date'] : $date['pickupreminder_date']) . ' ' . (isset($args['pickupreminder_time']) ? $args['pickupreminder_time'] : $date['pickupreminder_time']);
        if( trim($args['pickupreminder_dt']) != '' ) {
            $ts = strtotime($args['pickupreminder_dt']);
            if( $ts === FALSE || $ts < 1 ) {
                $args['pickupreminder_dt'] = '';
            } else {
                $dt = new DateTime('@'.$ts, new DateTimeZone($intl_timezone));
                if( $dt->format('Y-m-d H:i:s') != $date['pickupreminder_dt'] ) {
                    $args['pickupreminder_dt'] = $dt->format('Y-m-d H:i:s');
                }
            }
        }
    }
    if( isset($args['pickupstart_time']) ) {
        $args['pickupstart_dt'] = (isset($args['order_date']) ? $args['order_date'] : $date['order_date']) 
            . ' ' . $args['pickupstart_time'];
        if( trim($args['pickupstart_dt']) != '' ) {
            $ts = strtotime($args['pickupstart_dt']);
            if( $ts === FALSE || $ts < 1 ) {
                $args['pickupstart_dt'] = '';
            } else {
                $dt = new DateTime('@'.$ts, new DateTimeZone($intl_timezone));
                if( $dt->format('Y-m-d H:i:s') != $date['pickupstart_dt'] ) {
                    $args['pickupstart_dt'] = $dt->format('Y-m-d H:i:s');
                }
            }
        }
    }
    if( isset($args['pickupend_time']) ) {
        $args['pickupend_dt'] = (isset($args['order_date']) ? $args['order_date'] : $date['order_date']) 
            . ' ' . $args['pickupend_time'];
        if( trim($args['pickupend_dt']) != '' ) {
            $ts = strtotime($args['pickupend_dt']);
            if( $ts === FALSE || $ts < 1 ) {
                $args['pickupend_dt'] = '';
            } else {
                $dt = new DateTime('@'.$ts, new DateTimeZone($intl_timezone));
                if( $dt->format('Y-m-d H:i:s') != $date['pickupend_dt'] ) {
                    $args['pickupend_dt'] = $dt->format('Y-m-d H:i:s');
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
    $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.poma.orderdate', $args['date_id'], $args, 0x04);
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
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $args['tnid'], 'ciniki', 'poma');

    //
    // Update the web index if enabled
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'hookExec');
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'web', 'indexObject', array('object'=>'ciniki.poma.orderdate', 'object_id'=>$args['date_id']));

    return array('stat'=>'ok');
}
?>
