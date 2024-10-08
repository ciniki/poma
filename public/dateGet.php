<?php
//
// Description
// ===========
// This method will return all the information about an order date.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the order date is attached to.
// date_id:          The ID of the order date to get the details for.
//
// Returns
// -------
//
function ciniki_poma_dateGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'date_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Order Date'),
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
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.dateGet');
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
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'timeFormat');
    $time_format = ciniki_users_timeFormat($ciniki, 'php');

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
    // Return default for new Order Date
    //
    if( $args['date_id'] == 0 ) {
        //
        // Get the last date in the system
        //
        $strsql = "SELECT MAX(order_date) AS order_date "
            . "FROM ciniki_poma_order_dates "
            . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "LIMIT 1 "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'orderdate');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['orderdate']) ) {
            $dt = new DateTime($rc['orderdate']['order_date'], new DateTimeZone($intl_timezone));
            // FIXME: Allow for multiple orders per week, setup defaults in settings
        } else {
            $dt = new DateTime('now', new DateTimeZone($intl_timezone));
        }
        $odt = clone $dt;
        $adt = clone $dt;
        $adt->add(new DateInterval('P5D'));
        $ldt = clone $dt;
        $ldt->add(new DateInterval('P4D'));
        $dt->add(new DateInterval('P7D'));
        $rdt = clone($dt);
        $rdt->sub(new DateInterval('P6D'));
        $date = array('id'=>0,
            'order_date'=>$dt->format($date_format),
            'status'=>'10',
            'flags'=>'0x43',
            'open_date'=>$odt->format($date_format),
            'open_time'=>'1:00 AM',
            'repeats_date'=>$rdt->format($date_format),
            'repeats_time'=>'1:00 AM',
            'autolock_date'=>$adt->format($date_format),
            'autolock_time'=>'9:00 AM',
            'lockreminder_date'=>$ldt->format($date_format),
            'lockreminder_time'=>'6:00 AM',
            'pickupreminder_date'=>$dt->format($date_format),
            'pickupreminder_time'=>'6:00 AM',
            'notices'=>'',
        );
        if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.poma', 0x08) ) {
            //
            // Get the settings for dates
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQueryDash');
            $rc = ciniki_core_dbDetailsQueryDash($ciniki, 'ciniki_poma_settings', 'tnid', $args['tnid'], 'ciniki.poma', 'settings', 'dates');
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            $settings = $rc['settings'];

            if( isset($settings['dates-pickup-start']) && $settings['dates-pickup-start'] != '' ) {
                $pickupstart_dt = new DateTime($date['order_date'] . ' ' . $settings['dates-pickup-start'], new DateTimeZone($intl_timezone));
                if( $pickupstart_dt !== FALSE || $pickupstart_dt < 1 ) {
                    $date['pickupstart_time'] = $pickupstart_dt->format('g:i a');
                }
            }
            if( isset($settings['dates-pickup-end']) && $settings['dates-pickup-end'] != '' ) {
                $pickupend_dt = new DateTime($date['order_date'] . ' ' . $settings['dates-pickup-end'], new DateTimeZone($intl_timezone));
                if( $pickupend_dt !== FALSE || $pickupend_dt < 1 ) {
                    $date['pickupend_time'] = $pickupend_dt->format('g:i a');
                }
            }

            if( isset($pickupstart_dt) ) {
                $date['pickupstart_dt'] = $pickupstart_dt->format('Y-m-d H:i:s');
            }
            if( isset($pickupend_dt) ) {
                $date['pickupend_dt'] = $pickupend_dt->format('Y-m-d H:i:s');
            }
            if( isset($settings['dates-pickup-interval']) && $settings['dates-pickup-interval'] != '' && $settings['dates-pickup-interval'] > 0 ) {
                $date['pickupinterval'] = $settings['dates-pickup-interval'];
            } else {
                $date['pickupinterval'] = '5';
            }
        }
    }

    //
    // Get the details for an existing Order Date
    //
    else {
        $strsql = "SELECT ciniki_poma_order_dates.id, "
            . "ciniki_poma_order_dates.order_date, "
            . "ciniki_poma_order_dates.display_name, "
            . "ciniki_poma_order_dates.status, "
            . "ciniki_poma_order_dates.flags, "
            . "ciniki_poma_order_dates.open_dt AS open_date, "
            . "ciniki_poma_order_dates.open_dt AS open_time, "
            . "ciniki_poma_order_dates.repeats_dt AS repeats_date, "
            . "ciniki_poma_order_dates.repeats_dt AS repeats_time, "
            . "ciniki_poma_order_dates.autolock_dt AS autolock_date, "
            . "ciniki_poma_order_dates.autolock_dt AS autolock_time, "
            . "ciniki_poma_order_dates.lockreminder_dt AS lockreminder_date, "
            . "ciniki_poma_order_dates.lockreminder_dt AS lockreminder_time, "
            . "ciniki_poma_order_dates.pickupreminder_dt AS pickupreminder_date, "
            . "ciniki_poma_order_dates.pickupreminder_dt AS pickupreminder_time, "
            . "ciniki_poma_order_dates.pickupstart_dt AS pickupstart_time, "
            . "ciniki_poma_order_dates.pickupend_dt AS pickupend_time, "
            . "ciniki_poma_order_dates.pickupinterval, "
            . "ciniki_poma_order_dates.notices "
            . "FROM ciniki_poma_order_dates "
            . "WHERE ciniki_poma_order_dates.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND ciniki_poma_order_dates.id = '" . ciniki_core_dbQuote($ciniki, $args['date_id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'dates', 'fname'=>'id', 
                'fields'=>array('order_date', 'display_name', 'status', 'flags', 'open_date', 'open_time', 'repeats_date', 'repeats_time', 
                    'autolock_date', 'autolock_time', 'lockreminder_date', 'lockreminder_time', 'pickupreminder_date', 'pickupreminder_time', 
                    'pickupstart_time', 'pickupend_time', 'pickupinterval',
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
                    'pickupstart_time'=>array('timezone'=>$intl_timezone, 'format'=>$time_format),
                    'pickupend_time'=>array('timezone'=>$intl_timezone, 'format'=>$time_format),
                    ),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.7', 'msg'=>'Order Date not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['dates'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.8', 'msg'=>'Unable to find Order Date'));
        }
        $date = $rc['dates'][0];
    }

    return array('stat'=>'ok', 'date'=>$date);
}
?>
