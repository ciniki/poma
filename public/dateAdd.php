<?php
//
// Description
// -----------
// This method will add a new order date for the tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to add the Order Date to.
//
// Returns
// -------
//
function ciniki_poma_dateAdd(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'order_date'=>array('required'=>'yes', 'blank'=>'no', 'type'=>'date', 'name'=>'Date'),
        'status'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Status'),
        'flags'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Options'),
        'autoopen_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'Auto Open Date'),
        'autoopen_time'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'time', 'name'=>'Auto Open Time'),
        'autolock_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'Auto Lock Date'),
        'autolock_time'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'time', 'name'=>'Auto Lock Time'),
        'repeats_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'Repeat Date'),
        'repeats_time'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'time', 'name'=>'Repeat Time'),
        'lockreminder_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'Lock Reminder Date'),
        'lockreminder_time'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'time', 'name'=>'Lock Reminder Time'),
        'pickupreminder_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'Pickup Reminder Date'),
        'pickupreminder_time'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'time', 'name'=>'Pickup Reminder Time'),
        'notices'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Notices'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.dateAdd');
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

    //
    // Format the display_name
    //
    $dt = date_create_from_format('Y-m-d', $args['order_date']);
    $args['display_name'] = $dt->format('D M jS');

    //
    // Parse dates
    //
    $args['open_dt'] = (isset($args['open_date']) ? $args['open_date'] : '') . ' ' . (isset($args['open_time']) ? $args['open_time'] : '');
    if( trim($args['open_dt']) != '' ) {
        $ts = strtotime($args['open_dt']);
        if( $ts === FALSE || $ts < 1 ) {
            $args['open_dt'] = '';
        } else {
            $dt = new DateTime('@'.$ts, new DateTimeZone($intl_timezone));
            $args['open_dt'] = $dt->format('Y-m-d H:i:s');
        }
    }
    $args['repeats_dt'] = (isset($args['repeats_date']) ? $args['repeats_date'] : '') . ' ' . (isset($args['repeats_time']) ? $args['repeats_time'] : '');
    if( trim($args['repeats_dt']) != '' ) {
        $ts = strtotime($args['repeats_dt']);
        if( $ts === FALSE || $ts < 1 ) {
            $args['repeats_dt'] = '';
        } else {
            $dt = new DateTime('@'.$ts, new DateTimeZone($intl_timezone));
            $args['repeats_dt'] = $dt->format('Y-m-d H:i:s');
        }
    }
    $args['autolock_dt'] = (isset($args['autolock_date']) ? $args['autolock_date'] : '') . ' ' . (isset($args['autolock_time']) ? $args['autolock_time'] : '');
    if( trim($args['autolock_dt']) != '' ) {
        $ts = strtotime($args['autolock_dt']);
        if( $ts === FALSE || $ts < 1 ) {
            $args['autolock_dt'] = '';
        } else {
            $dt = new DateTime('@'.$ts, new DateTimeZone($intl_timezone));
            $args['autolock_dt'] = $dt->format('Y-m-d H:i:s');
        }
    }
    $args['lockreminder_dt'] = (isset($args['lockreminder_date']) ? $args['lockreminder_date'] : '') . ' ' . (isset($args['lockreminder_time']) ? $args['lockreminder_time'] : '');
    if( trim($args['lockreminder_dt']) != '' ) {
        $ts = strtotime($args['lockreminder_dt']);
        if( $ts === FALSE || $ts < 1 ) {
            $args['lockreminder_dt'] = '';
        } else {
            $dt = new DateTime('@'.$ts, new DateTimeZone($intl_timezone));
            $args['lockreminder_dt'] = $dt->format('Y-m-d H:i:s');
        }
    }
    $args['pickupreminder_dt'] = (isset($args['pickupreminder_date']) ? $args['pickupreminder_date'] : '') . ' ' . (isset($args['pickupreminder_time']) ? $args['pickupreminder_time'] : '');
    if( trim($args['pickupreminder_dt']) != '' ) {
        $ts = strtotime($args['pickupreminder_dt']);
        if( $ts === FALSE || $ts < 1 ) {
            $args['pickupreminder_dt'] = '';
        } else {
            $dt = new DateTime('@'.$ts, new DateTimeZone($intl_timezone));
            $args['pickupreminder_dt'] = $dt->format('Y-m-d H:i:s');
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
    // Add the order date to the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    $rc = ciniki_core_objectAdd($ciniki, $args['tnid'], 'ciniki.poma.orderdate', $args, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }
    $date_id = $rc['id'];

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
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'web', 'indexObject', array('object'=>'ciniki.poma.orderdate', 'object_id'=>$date_id));

    return array('stat'=>'ok', 'id'=>$date_id);
}
?>
