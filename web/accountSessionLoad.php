<?php
//
// Description
// -----------
// This function will check for a logged in customer and setup the ciniki.poma session information.
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_poma_web_accountSessionLoad(&$ciniki, $settings, $tnid) {

    //
    // Load tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    //
    // Check if the customer is signed in and look for an existing order id
    //
    if( isset($ciniki['session']['customer']['id']) && $ciniki['session']['customer']['id'] > 0 ) {
        if( !isset($ciniki['session']['ciniki.poma']) ) {
            $ciniki['session']['ciniki.poma'] = array();
        }

        //
        // If a session order date has been setup, then make sure it is still valid for orders.
        //
        if( isset($ciniki['session']['ciniki.poma']['date']['id']) && $ciniki['session']['ciniki.poma']['date']['id'] > 0 ) {
            $strsql = "SELECT id, order_date, display_name, status, flags, pickupstart_dt, pickupend_dt, pickupinterval "
                . "FROM ciniki_poma_order_dates "
                . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . "AND id = '" . ciniki_core_dbQuote($ciniki['session']['ciniki.poma']['date_id']) . "' "
                . "AND status > 5 "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            if( isset($rc['date']) ) {
                $ciniki['session']['ciniki.poma']['date'] = $rc['date'];
            } elseif( isset($ciniki['session']['ciniki.poma']['date']) ) {
                unset($ciniki['session']['ciniki.poma']['date']);
            }
        }

        //
        // Load the next available order date
        //
        else {
            if( isset($ciniki['session']['ciniki.poma']['date']) ) {
                unset($ciniki['session']['ciniki.poma']['date']);
            }
            $dt = new DateTime('now', new DateTimezone($intl_timezone));
            $strsql = "SELECT id, order_date, display_name, status, flags, pickupstart_dt, pickupend_dt, pickupinterval "
                . "FROM ciniki_poma_order_dates "
                . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . "AND status > 5 "
                . "AND status < 50 "
                . "AND order_date >= '" . ciniki_core_dbQuote($ciniki, $dt->format('Y-m-d')) . "' "
                . "ORDER BY order_date ASC "
                . "LIMIT 1 "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            if( isset($rc['date']) ) {
                $ciniki['session']['ciniki.poma']['date'] = $rc['date'];
            }
        }
    } 
   
    //
    // If the customer is not logged in, remove any ciniki.poma session info, just to be safe
    //
    else {
        if( isset($ciniki['session']['ciniki.poma']) ) {
            unset($ciniki['session']['ciniki.poma']);
        }
        if( isset($_SESSION['ciniki.poma']) ) {
            unset($_SESSION['ciniki.poma']);
        }
    }

    //
    // Setup order timestamp
    //
    if( isset($ciniki['session']['ciniki.poma']['date']['order_date']) ) {
        //
        // Default time noon on the order date.
        //
        $ciniki['session']['ciniki.poma']['date']['order_dt'] = new DateTime($ciniki['session']['ciniki.poma']['date']['order_date'] . ' 12:00:00', new DateTimezone($intl_timezone));
        $ciniki['session']['ciniki.poma']['date']['order_date_text'] = $ciniki['session']['ciniki.poma']['date']['order_dt']->format('M d, Y');
    }
    if( isset($ciniki['session']['ciniki.poma']) ) {
        $_SESSION['ciniki.poma'] = $ciniki['session']['ciniki.poma'];
    }

    return array('stat'=>'ok');
}
?>
