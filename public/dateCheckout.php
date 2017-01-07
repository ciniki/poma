<?php
//
// Description
// -----------
// This method returns the orders for a specific date, and the details of a specific order if specified.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:        The ID of the business to get Product for.
//
// Returns
// -------
//
function ciniki_poma_dateCheckout($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'),
        'date_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Date'),
        'order'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'New Order'),
        'order_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Order'),
        'customer_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Customer'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to business_id as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'foodmarket', 'private', 'checkAccess');
    $rc = ciniki_foodmarket_checkAccess($ciniki, $args['business_id'], 'ciniki.foodmarket.productList');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    $rsp = array('stat'=>'ok', 'dates'=>array(), 'open_orders'=>array(), 'closed_orders'=>array(), 
        'nplists'=>array('orderitems'=>array()),
        );

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');

    //
    // If the date wasn't set, then choose the closest date to now
    //
    if( !isset($args['date_id']) || $args['date_id'] == 0 ) {
        $strsql = "SELECT id, ABS(DATEDIFF(NOW(), order_date)) AS age "
            . "FROM ciniki_poma_order_dates "
            . "ORDER BY age ASC "
            . "LIMIT 1 "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['date']['id']) ) {
            return array('stat'=>'ok', 'dates'=>array(), 'open_orders'=>array(), 'closed_orders'=>array(), 'order'=>array());
        }
        $args['date_id'] = $rc['date']['id'];
        $rsp['date_id'] = $rc['date']['id'];
    }

    //
    // Get all the dates from latest date to earliest
    //
    $strsql = "SELECT ciniki_poma_order_dates.id, "
        . "ciniki_poma_order_dates.order_date, "
        . "ciniki_poma_order_dates.display_name, "
        . "ciniki_poma_order_dates.status, "
        . "ciniki_poma_order_dates.flags "
        . "FROM ciniki_poma_order_dates "
        . "WHERE ciniki_poma_order_dates.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
        . "GROUP BY ciniki_poma_order_dates.id "
        . "ORDER BY ciniki_poma_order_dates.order_date DESC "
        . "LIMIT 15"
        . "";
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'dates', 'fname'=>'id', 'fields'=>array('id', 'order_date', 'display_name', 'status', 'flags')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['dates']) || count($rc['dates']) < 1 ) {
        return array('stat'=>'ok', 'dates'=>array(), 'open_orders'=>array(), 'closed_orders'=>array(), 'order'=>array());
    }
    $rsp['dates'] = $rc['dates'];
    
    //
    // Check if a new order should be created
    //
    if( isset($args['order']) && $args['order'] == 'new' 
        && isset($args['customer_id']) && $args['customer_id'] > 0 
        && isset($args['date_id']) && $args['date_id'] > 0 
        ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'newOrderForDate');
        $rc = ciniki_poma_newOrderForDate($ciniki, $args['business_id'], array(
            'customer_id'=>$args['customer_id'],
            'date_id'=>$args['date_id'],
            'checkdate'=>'no',
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $rsp['order'] = $rc['order'];
    }

    //
    // Find the customers order if no order specified
    //
    elseif( (!isset($args['order_id']) || $args['order_id'] == 0) && isset($args['customer_id']) && $args['customer_id'] > 0 ) {
        $strsql = "SELECT id "
            . "FROM ciniki_poma_orders "
            . "WHERE date_id = '" . ciniki_core_dbQuote($ciniki, $args['date_id']) . "' "
            . "AND customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
            . "ORDER BY status "
            . "LIMIT 1 "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['order']['id']) ) {
            $args['order_id'] = $rc['order']['id'];
        }
    }

    //
    // Get the order
    //
    if( isset($args['order_id']) && $args['order_id'] > 0 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderLoad');
        $rc = ciniki_poma_orderLoad($ciniki, $args['business_id'], $args['order_id']);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['order']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.28', 'msg'=>'Unable to find order'));
        }
        $rsp['order'] = $rc['order'];
        $rsp['orderitems'] = $rc['order']['items'];
        $rsp['tallies'] = $rc['order']['tallies'];

        //
        // Build the nplists
        //
        foreach($rsp['orderitems'] as $item) {
            $rsp['nplists']['orderitems'][] = $item['id'];
        }
    }

    if( isset($rsp['order']['customer_id']) && $rsp['order']['customer_id'] > 0 ) {
        if( $rsp['order']['customer_id'] > 0 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'hooks', 'customerDetails');
            $rc = ciniki_customers_hooks_customerDetails($ciniki, $args['business_id'], array('customer_id'=>$rsp['order']['customer_id']));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            if( isset($rc['details']) ) {
                $rsp['customer_details'] = $rc['details'];
            }
        }
    }

    //
    // Get the list of open & closed orders
    //
    $strsql = "SELECT ciniki_poma_orders.id, "
        . "IF(ciniki_poma_orders.status < 70, 'open', 'closed') AS state, "
        . "ciniki_poma_orders.billing_name "
        . "FROM ciniki_poma_orders "
        . "WHERE date_id = '" . ciniki_core_dbQuote($ciniki, $args['date_id']) . "' "
        . "AND business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
        . "ORDER BY state, billing_name "
        . "";
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'states', 'fname'=>'state', 'fields'=>array('state')),
        array('container'=>'orders', 'fname'=>'id', 'fields'=>array('id', 'state', 'billing_name')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['states']) ) {
        foreach($rc['states'] as $state) {
            if( $state['state'] == 'open' ) {
                $rsp['open_orders'] = $state['orders'];
            } else {
                $rsp['closed_orders'] = $state['orders'];
            }
        }
    }

    return $rsp;
}
?>
