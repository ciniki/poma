<?php
//
// Description
// ===========
// This cron job will autolock an order date and add any standing order items to the orders.
//
// Arguments
// =========
// 
// Returns
// =======
// <rsp stat="ok" />
//
function ciniki_poma_cron_jobs(&$ciniki) {
    ciniki_cron_logMsg($ciniki, 0, array('code'=>'0', 'msg'=>'Checking for sapos jobs', 'severity'=>'5'));

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'dateRepeatsAdd');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'dateLock');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'emailUpdatedOrder');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'emailPickupReminders');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');

    //
    // FIXME: Check tenant for orders dates < 4 weeks out
    //

    //
    // Get the list of tenants with dates that need to have repeats applied
    //
    $strsql = "SELECT id, tnid "
        . "FROM ciniki_poma_order_dates "
        . "WHERE status < 20 "
        . "AND repeats_dt <= UTC_TIMESTAMP() "
        . "ORDER BY order_date ASC "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    if( isset($rc['rows']) ) {
        foreach($rc['rows'] as $row) {
            //
            // Start transaction
            //
            $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.poma');
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            
            error_log("Adding repeats: " . $row['id']);
            $rc = ciniki_poma_dateRepeatsAdd($ciniki, $row['tnid'], $row['id']);
            if( $rc['stat'] != 'ok' ) {
                ciniki_cron_logMsg($ciniki, $row['tnid'], array('code'=>'ciniki.poma.104', 'msg'=>'Unable to add repeats.',
                    'cron_id'=>0, 'severity'=>50, 'err'=>$rc['err'],
                    ));
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
        }
    }

    //
    // Get the list of tenants with dates that need to be locked
    //
    $strsql = "SELECT id, tnid "
        . "FROM ciniki_poma_order_dates "
        . "WHERE status < 50 "
        . "AND (flags&0x01) = 0x01 "
        . "AND autolock_dt <= UTC_TIMESTAMP() "
        . "ORDER BY order_date ASC "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    if( isset($rc['rows']) ) {
        foreach($rc['rows'] as $row) {
            //
            // Start transaction
            //
            $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.poma');
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }

            error_log("locking: " . $row['id']);
            $rc = ciniki_poma_dateLock($ciniki, $row['tnid'], $row['id']);
            if( $rc['stat'] != 'ok' ) {
                ciniki_cron_logMsg($ciniki, $row['tnid'], array('code'=>'ciniki.poma.98', 'msg'=>'Unable to lock date.',
                    'cron_id'=>0, 'severity'=>50, 'err'=>$rc['err'],
                    ));
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
        }
    }

    //
    // Check for orders that are marked for email and last modified more than 30 minutes ago
    //
    $dt = new DateTime('now', new DateTimezone('UTC'));
    $dt->sub(new DateInterval('PT30M'));

    $strsql = "SELECT id, tnid "
        . "FROM ciniki_poma_orders "
        . "WHERE (flags&0x10) = 0x10 "
        . "AND last_updated < '" . ciniki_core_dbQuote($ciniki, $dt->format('Y-m-d H:i:s')) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'orders');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['rows']) ) {
        foreach($rc['rows'] as $row) {
            //
            // Start transaction
            //
            $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.poma');
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }

            $rc = ciniki_poma_emailUpdatedOrder($ciniki, $row['tnid'], $row['id']);
            if( $rc['stat'] != 'ok' ) {
                ciniki_cron_logMsg($ciniki, $row['tnid'], array('code'=>'ciniki.poma.144', 'msg'=>'Unable to email order.',
                    'cron_id'=>0, 'severity'=>50, 'err'=>$rc['err'],
                    ));
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
        }
    }

    //
    // Check for order dates where pickup reminder should be sent
    //
    $strsql = "SELECT id, tnid "
        . "FROM ciniki_poma_order_dates "
        . "WHERE status = 50 "
        . "AND (flags&0x40) = 0x40 "
        . "AND pickupreminder_dt <= UTC_TIMESTAMP() "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    if( isset($rc['rows']) ) {
        foreach($rc['rows'] as $row) {
            //
            // Start transaction
            //
            $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.poma');
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }

            $rc = ciniki_poma_emailPickupReminders($ciniki, $row['tnid'], $row['id']);
            if( $rc['stat'] != 'ok' ) {
                ciniki_cron_logMsg($ciniki, $row['tnid'], array('code'=>'ciniki.poma.148', 'msg'=>'Unable to email pickup reminders.',
                    'cron_id'=>0, 'severity'=>50, 'err'=>$rc['err'],
                    ));
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
        }
    }

    return array('stat'=>'ok');
}
?>
