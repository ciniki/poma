<?php
//
// Description
// -----------
// This method is used to add multiple dates based on settings for dates.
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
function ciniki_poma_datesAdd(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'order_date'=>array('required'=>'yes', 'blank'=>'no', 'type'=>'date', 'name'=>'Date'),
        'repeat_days'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Repeat Days'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];
    $args['flags'] = 0;
    $args['status'] = 10;

    //
    // Check access to tnid as owner
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.datesAdd');
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
    // Get the settings for dates
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQueryDash');
    $rc = ciniki_core_dbDetailsQueryDash($ciniki, 'ciniki_poma_settings', 'tnid', $args['tnid'], 'ciniki.poma', 'settings', 'dates');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $settings = $rc['settings'];

    //
    // Prepare the autolock date
    //
    if( isset($settings['dates-lock-auto']) && $settings['dates-lock-auto'] == 'yes' 
        && isset($settings['dates-lock-time']) && $settings['dates-lock-time'] != '' 
        ) {
        $ts = strtotime($args['order_date'] . ' ' . $settings['dates-lock-time']);
        if( $ts === FALSE || $ts < 1 ) {
            $args['autolock_dt'] = '';
        } else {
            $autolock_dt = new DateTime('@'.$ts, new DateTimeZone($intl_timezone));
            //
            // Check for the offset
            //
            if( isset($settings['dates-lock-offset']) && $settings['dates-lock-offset'] > 0 ) {
                $autolock_dt->sub(new DateInterval('P' . $settings['dates-lock-offset'] . 'D'));
            }
            $args['flags'] |= 0x01;
        }
    }
    
    //
    // Prepare the pickup reminder date
    //
    if( isset($settings['dates-pickup-reminder']) && $settings['dates-pickup-reminder'] == 'yes' 
        && isset($settings['dates-pickup-reminder-time']) && $settings['dates-pickup-reminder-time'] != '' 
        ) {
        $ts = strtotime($args['order_date'] . ' ' . $settings['dates-pickup-reminder-time']);
        if( $ts === FALSE || $ts < 1 ) {
            $args['pickupreminder_dt'] = '';
        } else {
            $pickupreminder_dt = new DateTime('@'.$ts, new DateTimeZone($intl_timezone));
            //
            // Check for the offset
            //
            if( isset($settings['dates-pickup-reminder-offset']) && $settings['dates-pickup-reminder-offset'] > 0 ) {
                $pickupreminder_dt->sub(new DateInterval('P' . $settings['dates-pickup-reminder-offset'] . 'D'));
            }
            $args['flags'] |= 0x40;
        }
    }
    
    //
    // Prepare the repeats date
    //
    if( isset($settings['dates-apply-repeats-offset']) && $settings['dates-apply-repeats-offset'] != '' 
        && isset($settings['dates-apply-repeats-time']) && $settings['dates-apply-repeats-time'] != '' 
        ) {
        $ts = strtotime($args['order_date'] . ' ' . $settings['dates-apply-repeats-time']);
        if( $ts === FALSE || $ts < 1 ) {
            $args['repeats_dt'] = '';
        } else {
            $repeats_dt = new DateTime('@'.$ts, new DateTimeZone($intl_timezone));
            //
            // Check for the offset
            //
            if( isset($settings['dates-apply-repeats-offset']) && $settings['dates-apply-repeats-offset'] > 0 ) {
                $repeats_dt->sub(new DateInterval('P' . $settings['dates-apply-repeats-offset'] . 'D'));
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

    $num_days = 1;
    if( isset($args['repeat_days']) && $args['repeat_days'] > 1 && $args['repeat_days'] < 8 ) {
        $num_days = $args['repeat_days'];
    }

    $dt = date_create_from_format('Y-m-d', $args['order_date']);
    for($i = 0; $i < $num_days; $i++) {
        //
        // Format the display_name
        //
        if( $i > 0 ) {
            $dt->add(new DateInterval('P1D'));
        }
        $args['order_date'] = $dt->format('Y-m-d');
        $args['display_name'] = $dt->format('D M jS');

        //
        // Increase and format dates
        //
        if( isset($autolock_dt) ) {
            if( $i > 0 ) {
                $autolock_dt->add(new DateInterval('P1D'));
            }
            $args['autolock_dt'] = $autolock_dt->format('Y-m-d H:i:s');
        }
        if( isset($pickupreminder_dt) ) {
            if( $i > 0 ) {
                $pickupreminder_dt->add(new DateInterval('P1D'));
            }
            $args['pickupreminder_dt'] = $pickupreminder_dt->format('Y-m-d H:i:s');
        }
        if( isset($repeats_dt) ) {
            if( $i > 0 ) {
                $repeats_dt->add(new DateInterval('P1D'));
            }
            $args['repeats_dt'] = $repeats_dt->format('Y-m-d H:i:s');
        }

        //
        // Add the date
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
        $rc = ciniki_core_objectAdd($ciniki, $args['tnid'], 'ciniki.poma.orderdate', $args, 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
            return $rc;
        }
        $date_id = $rc['id'];

        //
        // Update the web index if enabled
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'hookExec');
        ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'web', 'indexObject', array('object'=>'ciniki.poma.orderdate', 'object_id'=>$date_id));
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

    return array('stat'=>'ok');
}
?>
