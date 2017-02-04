<?php
//
// Description
// -----------
// This function will add another modules object/objectid as a favourite.
//
// Arguments
// ---------
// ciniki:
// business_id:                 The business ID to check the session user against.
// method:                      The requested method.
//
// Returns
// -------
// <rsp stat='ok' />
//
function ciniki_poma_repeatItemUpdate(&$ciniki, $business_id, $args) {
    
    //
    // Check args
    //
    if( !isset($args['object']) || $args['object'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.93', 'msg'=>'No item specified.'));
    }
    if( !isset($args['object_id']) || $args['object_id'] < 1 || $args['object_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.94', 'msg'=>'No item specified.'));
    }
    if( !isset($args['customer_id']) || $args['customer_id'] < 1 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.95', 'msg'=>'No customer specified.'));
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
    // Get the details for the item
    //
    list($pkg, $mod, $obj) = explode('.', $args['object']);
    $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'itemLookup');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.96', 'msg'=>'Unable to add favourite.'));
    }
    $fn = $rc['function_call'];
    $rc = $fn($ciniki, $business_id, $args);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['item']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.97', 'msg'=>'Unable to add favourite.'));
    }
    $object_item = $rc['item'];

    //
    // Check if item already exists as a repeat
    //
    $strsql = "SELECT ciniki_poma_customer_items.id, "
        . "ciniki_poma_customer_items.uuid, "
        . "ciniki_poma_customer_items.status, "
        . "ciniki_poma_customer_items.quantity, "
        . "ciniki_poma_customer_items.repeat_days, "
        . "ciniki_poma_customer_items.last_order_date, "
        . "ciniki_poma_customer_items.next_order_date "
        . "FROM ciniki_poma_customer_items "
        . "WHERE ciniki_poma_customer_items.customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
        . "AND ciniki_poma_customer_items.itype = 40 "
        . "AND ciniki_poma_customer_items.object = '" . ciniki_core_dbQuote($ciniki, $args['object']) . "' "
        . "AND ciniki_poma_customer_items.object_id = '" . ciniki_core_dbQuote($ciniki, $args['object_id']) . "' "
        . "AND ciniki_poma_customer_items.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['item']) ) {
        $existing_item = $rc['item'];
    }

    //
    // If item already exists as a repeat, then update quantity, dates, etc.
    //
    if( isset($existing_item) ) {
        $update_args = array();
        if( isset($args['quantity']) && $args['quantity'] != $existing_item['quantity'] ) {
            $update_args['quantity'] = $args['quantity'];
            $existing_item['quantity'] = $args['quantity'];
        }
        if( isset($args['repeat_days']) && $args['repeat_days'] != $existing_item['repeat_days'] && $args['repeat_days'] > 0 ) {
            $update_args['repeat_days'] = $args['repeat_days'];
            $repeat_days = $args['repeat_days'];
            $existing_item['repeat_days'] = $args['repeat_days'];
            // Calculate the next date
            if( $existing_item['last_order_date'] != '0000-00-00' ) {
                $dt = new DateTime($existing_item['last_order_date'], new DateTimezone($intl_timezone));
                $dt->add(new DateInterval('P' . $repeat_days . 'D'));
                $update_args['next_order_date'] = $dt->format('Y-m-d');
                $existing_item['next_order_date'] = $dt->format('Y-m-d');
                $existing_item['next_order_date_text'] = ($existing_item['quantity'] > 0 ? $dt->format('M j, Y') : 'Never');
            }
        } else {
            $repeat_days = $existing_item['repeat_days'];
        }
            
        if( isset($args['skip']) && $args['skip'] == 'yes' ) {
            //
            // Check if next_order_date is blank, then assign to next order date
            //
            if( $existing_item['next_order_date'] == '' || $existing_item['next_order_date'] == '0000-00-00' ) {
                //
                // FIXME: Get the next order date
                //
                $existing_item['next_order_date_text'] = '';
            } else {
                //
                // Add the repeat_days to the next_date and find the next date
                //
                $dt = new DateTime($existing_item['next_order_date'], new DateTimezone($intl_timezone));
                $dt->add(new DateInterval('P' . $repeat_days . 'D'));
                $update_args['next_order_date'] = $dt->format('Y-m-d');
                $existing_item['next_order_date'] = $dt->format('Y-m-d');
                $existing_item['next_order_date_text'] = ($existing_item['quantity'] > 0 ? $dt->format('M j, Y') : 'Never');
            }
        } else {
            $dt = new DateTime($existing_item['next_order_date'], new DateTimezone($intl_timezone));
            $existing_item['next_order_date_text'] = ($existing_item['quantity'] > 0 ? $dt->format('M j, Y') : 'Never');
        }

        //
        // Delete the item if quantity is zero
        //
        if( isset($update_args['quantity']) && $update_args['quantity'] <= 0 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
            $rc = ciniki_core_objectDelete($ciniki, $business_id, 'ciniki.poma.customeritem', $existing_item['id'], $existing_item['uuid'], 0x04);
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
        }
        //
        // Update the existing item
        //
        elseif( count($update_args) > 0 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            $rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.poma.customeritem', $existing_item['id'], $update_args, 0x04);
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
        }
        //
        // Return the quantity, repeat_days and next_date
        //
        $item = array(
            'quantity'=>$existing_item['quantity'],
            'repeat_days'=>$existing_item['repeat_days'],
            'next_order_date_text'=>$existing_item['next_order_date_text'],
            );
    }
    //
    // Object doesn't exist as a repeat, add now
    //
    else {
        //
        // Set the defaults
        //
        $args['itype'] = 40;
        if( !isset($args['quantity']) ) {
            $args['quantity'] = 1;
        }
        if( !isset($args['repeat_days']) || $args['repeat_days'] < 7 ) {
            $args['repeat_days'] = 7;
        }
        $args['description'] = $object_item['description'];

        //
        // No last order, then find the next order
        //
        $strsql = "SELECT id, order_date "
            . "FROM ciniki_poma_order_dates "
            . "WHERE status < 50 "
            . "AND business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
            . "AND order_date > NOW() "
            . "ORDER BY order_date ASC "
            . "LIMIT 1 "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['date']['order_date']) ) {
            $next_order_date = $rc['date']['order_date'];
            $ndt = new DateTime($next_order_date, new DateTimezone($intl_timezone));
        } else {
            $ndt = new DateTime('now', new DateTimezone($intl_timezone));
            $ndt->add('P' . $args['repeat_days'] . 'D');
            $next_order_date = $ndt->format('Y-m-d');
        }

        //
        // Check if ordered before
        //
        $strsql = "SELECT MAX(ciniki_poma_order_dates.order_date) AS last_order_date "
            . "FROM ciniki_poma_order_items, ciniki_poma_orders, ciniki_poma_order_dates "
            . "WHERE ciniki_poma_order_items.object = '" . ciniki_core_dbQuote($ciniki, $args['object']) . "' "
            . "AND ciniki_poma_order_items.object_id = '" . ciniki_core_dbQuote($ciniki, $args['object_id']) . "' "
            . "AND ciniki_poma_order_items.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
            . "AND ciniki_poma_order_items.order_id = ciniki_poma_orders.id "
            . "AND ciniki_poma_orders.customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
            . "AND ciniki_poma_orders.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
            . "AND ciniki_poma_orders.date_id = ciniki_poma_order_dates.id "
            . "AND ciniki_poma_order_dates.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['item']['last_order_date']) ) {
            $last_order_date = $rc['item']['last_order_date'];
            $ldt = new DateTime($last_order_date, new DateTimezone($intl_timezone));
            $diff = $ndt->diff($ldt);
            if( $diff->d < $args['repeat_days'] ) {
                $ndt = clone($ldt);
                $ndt->add(new DateInterval('P' . $args['repeat_days'] . 'D'));
                $next_order_date = $ndt->format('Y-m-d');
            }
            $args['last_order_date'] = $last_order_date;
        }
         
        $args['next_order_date'] = $next_order_date;

        //
        // Add the item
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
        $rc = ciniki_core_objectAdd($ciniki, $business_id, 'ciniki.poma.customeritem', $args, 0x04);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $item_id = $rc['id'];

        $item = array(
            'quantity'=>$args['quantity'],
            'repeat_days'=>$args['repeat_days'],
            'next_order_date_text'=>($args['quantity']>0 ? $ndt->format('M j, Y') : 'Never'),
            );
    }


    return array('stat'=>'ok', 'item'=>$item);
}
?>
