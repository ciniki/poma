<?php
//
// Description
// -----------
// This function will process a web request for upcoming orders.
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure.
// tnid:     The ID of the tenant to get events for.
//
// args:            The possible arguments for posts
//
//
// Returns
// -------
//
function ciniki_poma_web_processRequestCSA(&$ciniki, $settings, $tnid, $args) {

    
    $page = array(
        'title'=>$args['page_title'],
        'breadcrumbs'=>$args['breadcrumbs'],
        'blocks'=>array(),
        'submenu'=>array(),
        );

    $page['breadcrumbs'][] = array('name'=>'CSA Season', 'url'=>$args['base_url']);
    
    $api_item_update = 'ciniki/poma/orderItemUpdate/';
    $api_repeat_update = 'ciniki/poma/repeatObjectUpdate/';

    //
    // Load tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    $dt = new DateTime('now', new DateTimezone($intl_timezone));

    //
    // Load the current season
    //
    $strsql = "SELECT id, start_date, end_date, csa_start_date, csa_end_date, csa_days "
        . "FROM ciniki_foodmarket_seasons "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' ";
    if( isset($args['season_id']) && $args['season_id'] > 0 ) {
        $strsql .= "AND id = '" . ciniki_core_dbQuote($ciniki, $args['season_id']) . "' ";
    }
    $strsql .= "ORDER BY end_date DESC "
        . "LIMIT 1 ";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.foodmarket', 'season');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.foodmarket.110', 'msg'=>'Unable to load season', 'err'=>$rc['err']));
    }
    if( !isset($rc['season']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.foodmarket.111', 'msg'=>'No seasons setup'));
    }
    $season = $rc['season'];

    //
    // Load the list of orders for the customer
    //
    $strsql = "SELECT orders.id, "
        . "orders.order_number, "
        . "orders.order_date, "
        . "dates.status AS date_status, "
        . "orders.status, "
        . "items.id AS item_id, "
        . "items.code, "
        . "items.description, "
        . "items.itype, "
        . "items.weight_quantity, "
        . "items.unit_quantity "
        . "FROM ciniki_poma_orders AS orders "
        . "INNER JOIN ciniki_poma_order_dates AS dates ON ("
            . "orders.date_id = dates.id "
            . "AND dates.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "INNER JOIN ciniki_poma_order_items AS items ON ("
            . "orders.id = items.order_id "
            . "AND (items.flags&0x0200) = 0x0200 "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE orders.customer_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['customer']['id']) . "' "
        . "AND orders.order_date >= '" . ciniki_core_dbQuote($ciniki, $season['csa_start_date']) . "' " 
        . "AND orders.order_date <= '" . ciniki_core_dbQuote($ciniki, $season['csa_end_date']) . "' " 
        . "AND orders.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "ORDER BY orders.order_date ASC "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.foodmarket', array(
        array('container'=>'orders', 'fname'=>'id', 
            'fields'=>array('id', 'order_number', 'order_date', 'date_status', 'status'),
            'utctotz'=>array('order_date'=>array('format'=>'D M j, Y', 'timezone'=>'UTC')),
            ),
        array('container'=>'items', 'fname'=>'item_id', 
            'fields'=>array('id'=>'item_id', 'code', 'description', 'itype', 'weight_quantity', 'unit_quantity'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.foodmarket.123', 'msg'=>'Unable to load orders', 'err'=>$rc['err']));
    }
    $last_order = null;
    $skip_available = 'no';
    $skip_order = null;
    if( isset($args['uri_split'][1]) && $args['uri_split'][0] == 'skip' && $args['uri_split'][1] ) {
        $skip_order_id = $args['uri_split'][1];
    }
    if( isset($rc['orders']) ) {
        $orders = $rc['orders'];
        foreach($orders as $oid => $order) {
            $orders[$oid]['products'] = '';
            if( isset($order['items']) ) {
                foreach($order['items'] as $item) {
                    $orders[$oid]['products'] .= ($orders[$oid]['products'] != '' ? ", \n" : '') 
                        . $item['description'];
                }
            }

            $last_order = $order;
            if( isset($skip_order_id) && $order['id'] == $skip_order_id ) {
                $skip_order = $order;
            }
        }
        
        //
        // If no last order found, display error
        //
        if( $last_order == null ) {
            $page['blocks'][] = array('type'=>'formmessage', 'level'=>'error', 'message'=>'No orders for the season');
        } else {
            //
            // Check if any skip weeks still available
            //
            $last_dt = new DateTime($last_order['order_date']);
            $end_dt = new DateTime($season['csa_end_date']);
            $interval = $last_dt->diff($end_dt);  
            $num_days = (int)$interval->format("%R%a");
            if( $num_days > 7 ) {
                $skip_available = 'yes';
            }
        }
    } else {
        $orders = array();
        $page['blocks'][] = array('type'=>'formmessage', 'level'=>'error', 'message'=>'No orders for the season');
    }

    //
    // Check if skip week requested
    //
    if( $skip_order != null && $skip_available == 'yes' ) {
        $skip_date = clone($last_dt);
        $skip_date->add(new DateInterval('P7D'));
        $strsql = "SELECT id, order_date FROM ciniki_poma_order_dates "
            . "WHERE order_date = '" . ciniki_core_dbQuote($ciniki, $skip_date->format('Y-m-d')) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.209', 'msg'=>'Unable to load date', 'err'=>$rc['err']));
        }
        if( !isset($rc['date']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.210', 'msg'=>'Unable to find requested date'));
        }
        $new_date = $rc['date'];
        
        //
        // Update the order to the new date
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
        $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.poma.order', $skip_order['id'], array(
            'order_date' => $new_date['order_date'],
            'date_id' => $new_date['id'],
            ), 0x04);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.209', 'msg'=>'Unable to update the order'));
        }

        //
        // Remove any basket items for the order so they are re-added when basket is setup for that week
        //
        $strsql = "SELECT id, uuid, parent_id "
            . "FROM ciniki_poma_order_items "
            . "WHERE order_id = '" . ciniki_core_dbQuote($ciniki, $skip_order['id']) . "' "
            . "AND parent_id > 0 "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.214', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
        }
        if( isset($rc['rows']) ) {
            $items = $rc['rows'];
            foreach($items as $item) {
                if( $item['parent_id'] > 0 ) {
                    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
                    $rc = ciniki_core_objectDelete($ciniki, $tnid, 'ciniki.poma.orderitem', $item['id'], $item['uuid'], 0x04);
                    if( $rc['stat'] != 'ok' ) {
                        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.215', 'msg'=>'Unable to remove item', 'err'=>$rc['err']));
                    }
                }
            }
        }
            
        //
        // Reload the page
        //
        header('Location: ' . $args['base_url'] . '/csa');
        exit;
    }

    //
    // List the orders
    //
    $page['blocks'][] = array('type'=>'orderseason', 
        'orders'=>$orders,
        'base_url'=>$args['base_url'],
        'skip_available'=>$skip_available,
        );

    return array('stat'=>'ok', 'page'=>$page);
}
?>
