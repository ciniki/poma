<?php
//
// Description
// -----------
// This function returns the list of items that a customer has favourited, queued or put on repeat order.
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure.
// business_id:     The ID of the business to get poma web options for.
//
// args:            The possible arguments for posts
//
//
// Returns
// -------
//
function ciniki_poma_hooks_customerItemsByType(&$ciniki, $business_id, $args) {

    //
    // Check to make sure the module is enabled
    //
    if( !isset($ciniki['business']['modules']['ciniki.poma']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.15', 'msg'=>"I'm sorry, the page you requested does not exist."));
    }

    //
    // Check to make sure customer has been passed
    //
    if( !isset($args['customer_id']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.16', 'msg'=>"Customer not logged in."));
    }

    //
    // Load business settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'intlSettings');
    $rc = ciniki_businesses_intlSettings($ciniki, $business_id);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    //
    // Get the list of customer items
    //
    $strsql = "SELECT ciniki_poma_customer_items.id, "
        . "ciniki_poma_customer_items.itype, "
        . "ciniki_poma_customer_items.status, "
        . "ciniki_poma_customer_items.object, "
        . "ciniki_poma_customer_items.object_id, "
        . "ciniki_poma_customer_items.repeat_days, "
        . "ciniki_poma_customer_items.quantity, "
        . "DATE_FORMAT(ciniki_poma_customer_items.last_order_date, '%b %e, %Y') AS last_order_date, "
        . "DATE_FORMAT(ciniki_poma_customer_items.next_order_date, '%b %e, %Y') AS next_order_date "
        . "FROM ciniki_poma_customer_items "
        . "WHERE ciniki_poma_customer_items.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "AND ciniki_poma_customer_items.customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
        . "";
    if( isset($args['object']) ) {
        $strsql .= "AND ciniki_poma_customer_items.object = '" . ciniki_core_dbQuote($ciniki, $args['object']) . "' ";
    } else {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.17', 'msg'=>"No object specified."));
    }
    if( isset($args['object_ids']) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteIDs');
        $strsql .= "AND ciniki_poma_customer_items.object_id IN (" . ciniki_core_dbQuoteIDs($ciniki, $args['object_ids']) . ") ";
    } else {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.18', 'msg'=>"No objects specified."));
    }
    $strsql .= "ORDER BY itype, object_id ";

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $types = array(
        'favourite'=>array('items'=>array()),
        'repeat'=>array('items'=>array()),
        'queueactive'=>array('items'=>array()),
        'queueordered'=>array('items'=>array()),
        );
    if( isset($rc['rows']) ) {
        foreach($rc['rows'] as $row) {
            if( $row['itype'] == 20 ) {
                $types['favourite']['items'][$row['object_id']] = array();
            }
            elseif( $row['itype'] == 40 ) {
                $repeat_text = '';
                if( ($row['repeat_days']%7) == 0 ) {
                    $weeks = ($row['repeat_days'] / 7);
                    $repeat_text = $weeks . ' week' . ($weeks > 1 ? 's' : '');
                } else {
                    $repeat_text = $row['repeat_days'] . ' day' . ($row['repeat_days'] > 1 ? 's' : '');
                }
                $last_dt = new DateTime($row['last_order_date'], new DateTimezone($intl_timezone));
                $next_dt = new DateTime($row['next_order_date'], new DateTimezone($intl_timezone));
                $types['repeat']['items'][$row['object_id']] = array(
                    'id'=>$row['id'],
                    'status'=>$row['status'],
                    'quantity'=>$row['quantity'],
                    'repeat_days'=>$row['repeat_days'],
                    'repeat_text'=>$repeat_text,
                    'last_order_date'=>$row['last_order_date'],
                    'last_order_dt'=>$last_dt,
                    'next_order_date'=>$row['next_order_date'],
                    'next_order_dt'=>$next_dt,
                    );
            }
            elseif( $row['itype'] == 60 ) {
                $types['queue']['items'][$row['object_id']] = array(
                    'id'=>$row['id'],
                    'status'=>$row['status'],
                    'quantity'=>$row['quantity'],
                    );
            }
        }
    } else {
        $types = array();
    }

    //
    // Check for queued items
    //
    $strsql = "SELECT items.id, "
        . "items.status, "
        . "items.object, "
        . "items.object_id, "
        . "items.quantity "
        . "FROM ciniki_poma_queued_items AS items "
        . "WHERE items.customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
        . "AND items.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "AND items.status < 90 "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['rows']) ) {
        foreach($rc['rows'] as $row) {
            if( $row['status'] == 10 ) {
                $types['queueactive']['items'][$row['object_id']] = array(
                    'id'=>$row['id'],
                    'quantity'=>(float)$row['quantity'],
                    );
            } elseif( $row['status'] == 40 ) {
                $types['queueordered']['items'][$row['object_id']] = array(
                    'id'=>$row['id'],
                    'quantity'=>(float)$row['quantity'],
                    );
                
            }
        }
    }

    return array('stat'=>'ok', 'types'=>$types);
}
?>
