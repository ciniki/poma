<?php
//
// Description
// -----------
// This function will process a web request for closed orders.
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
function ciniki_poma_web_processRequestClosed(&$ciniki, $settings, $tnid, $args) {

    
    $page = array(
        'title'=>$args['page_title'],
        'breadcrumbs'=>$args['breadcrumbs'],
        'blocks'=>array(),
        'submenu'=>array(),
        );

    $page['breadcrumbs'][] = array('name'=>'Closed', 'url'=>$args['base_url']);
    
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
    // Get the list of available dates
    //
    $strsql = "SELECT ciniki_poma_order_dates.id, "
        . "ciniki_poma_order_dates.order_date, "
        . "ciniki_poma_order_dates.display_name, "
        . "ciniki_poma_order_dates.status, "
        . "ciniki_poma_order_dates.pickupstart_dt, "
        . "ciniki_poma_order_dates.pickupend_dt, "
        . "ciniki_poma_order_dates.flags "
        . "FROM ciniki_poma_order_dates "
        . "INNER JOIN ciniki_poma_orders ON ("
            . "ciniki_poma_order_dates.id = ciniki_poma_orders.date_id "
            . "AND ciniki_poma_orders.customer_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['customer']['id']) . "' "
            . ") "
        . "WHERE ciniki_poma_order_dates.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
//        . "AND ciniki_poma_order_dates.order_date < '" . ciniki_core_dbQuote($ciniki, $dt->format('Y-m-d')) . "' "
        . "AND ciniki_poma_order_dates.status >= 50 "
        . "ORDER BY order_date DESC "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'dates', 'fname'=>'id', 'fields'=>array('id', 'order_date', 'display_name', 'status', 'flags',
            'pickupstart_dt', 'pickupend_dt')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'500', 'err'=>array('code'=>'ciniki.poma.225', 'msg'=>'Unable to find order dates'));
    }
    $date_id = 0;
    $cur_dt = new DateTime('now', new DateTimezone($intl_timezone));
    $cur_order_dt = null;
    if( !isset($rc['dates']) || count($rc['dates']) == 0 ) {
        $page['blocks'][] = array('type'=>'formmessage', 'level'=>'error', 'message'=>"Oops, it looks like we forgot to add more available dates. Please contact us and we'll get more dates added.");
    } else {
        $content = "<form action='" . $args['base_url'] . "/closed' method='GET'>"
            . "Your current order date: ";
        $content .= "<select name='d' onchange='this.form.submit();'>";
        foreach($rc['dates'] as $odate) {
            if( $date_id == 0 ) {
                $date_id = $odate['id'];
            }
            $odate['order_dt'] = new DateTime($odate['order_date'] . ' 12:00:00', new DateTimezone($intl_timezone));
            $odate['display_name'] = $odate['order_dt']->format("M jS, Y");
            if( $cur_order_dt == null ) {
                $cur_order_dt = $odate['order_dt'];
            }
            if( isset($_GET['d']) && $_GET['d'] > 0 && $_GET['d'] == $odate['order_date'] ) {
                $date_id = $odate['id'];
                $cur_order_dt = $odate['order_dt'];
                $odate['order_date_text'] = $odate['order_dt']->format('M d, Y');
                $content .= "<option value='" . $odate['order_date'] . "' selected>" . $odate['display_name'] . "</option>";
            } else {
                $content .= "<option value='" . $odate['order_date'] . "'>" . $odate['display_name'] . "</option>";
            }
        }
        $content .= "</select></form>";

        $page['blocks'][] = array('type'=>'content', 'section'=>'order-date', 'html'=>$content);
    }

    //
    // Display the current order
    //
    if( $date_id > 0 ) {
        //
        // Load the current order
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'orderLoad');
        $rc = ciniki_poma_web_orderLoad($ciniki, $settings, $tnid, array('date_id'=>$date_id));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['order']) ) {
            $page['blocks'][] = array('type'=>'formmessage', 'title'=>'Order', 'size'=>'wide', 'level'=>'error', 
                'message'=>"Oops, we were unable to find your order.");
        } else {
            $order = $rc['order'];
            
            //
            // Check if request to change pickup time
            //
            if( isset($args['uri_split'][0]) && $args['uri_split'][0] == 'pickuptime' ) {
                //
                // Get the list of existing taken timeslots
                //
                $strsql = "SELECT id, pickup_time " 
                    . "FROM ciniki_poma_orders "
                    . "WHERE date_id = '" . ciniki_core_dbQuote($ciniki, $date_id) . "' "
                    . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                    . "";
                ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
                $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.poma', array(
                    array('container'=>'times', 'fname'=>'pickup_time', 'fields'=>array('pickup_time')),
                    array('container'=>'orders', 'fname'=>'id', 'fields'=>array('id')),
                    ));
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.217', 'msg'=>'Unable to load pickup times', 'err'=>$rc['err']));
                }
                $picked_times = isset($rc['times']) ? $rc['times'] : array();
                if( isset($_GET['t']) && $_GET['t'] != '' ) {
                    $new_dt = new DateTime($cur_order_dt->format('Y-m-d') . ' ' . $_GET['t'], new DateTimezone($intl_timezone));
                    if( isset($picked_times[$new_dt->format('g:i a')]) ) {
                        $page['blocks'][] = array('type'=>'formmessage', 'level'=>'error', 'message'=>'That time is unavailable');
                    } else {
                        //
                        // Update the order
                        //
                        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
                        $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.poma.order', $order['id'], array('pickup_time'=>$new_dt->format('g:i a')), 0x04);
                        if( $rc['stat'] != 'ok' ) {
                            $page['blocks'][] = array('type'=>'formmessage', 'level'=>'error', 'message'=>'Unable to update order');
                        } else {
                            header("Location: " . $args['base_url'] . '/closed?d=' . $cur_order_dt->format('Y-m-d'));
                            exit;
                        }
                    }
                }

                //
                // List the available times
                //
                $start_dt = new DateTime($ciniki['session']['ciniki.poma']['date']['pickupstart_dt'], new DateTimezone('UTC'));
                $start_dt->setTimezone(new DateTimezone($intl_timezone));
                $end_dt = new DateTime($ciniki['session']['ciniki.poma']['date']['pickupend_dt'], new DateTimezone('UTC'));
                $end_dt->setTimezone(new DateTimezone($intl_timezone));
                $interval = new DateInterval('PT5M');
                $options = array();
                while($start_dt < $end_dt) {
                    $option = array(
                        'label' => $start_dt->format('g:i a'),
                        'url' => $args['base_url'] . '/closed/pickuptime?d=' . $cur_order_dt->format('Y-m-d') . '&t=' . $start_dt->format('H:i'), 
                        );
                    if( !isset($picked_times[$start_dt->format('g:i a')]) ) {
                        $option['available'] = 'yes';
                    } elseif( $order['pickup_time'] == $start_dt->format('g:i a') ) {
                        $option['message'] = '<b>Current</b>';
                    } else {
                        $option['message'] = 'Unavailable';
                    }
                    $options[] = $option;
                    // Move to next time slot
                    $start_dt->add($interval);
                }
                $page['blocks'][] = array('type' => 'optionlist', 
                    'title' => 'Choose your pickup time', 
                    'wide' => 'yes', 
                    'button-label' => 'Select',
                    'base_url' => $args['base_url'], 
                    'options' => $options,
                    );
            } 
            //
            // Output the order details
            //
            else {
                // Make sure order date is in the future, can't change pickup time of past orders
                $cur_order_dt->setTime(23, 59, 59);
                $cur_dt->add(new DateInterval('P2D'));
                if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.poma', 0x08) && $order['id'] > 0 && $cur_dt <= $cur_order_dt ) {
                    if( $order['pickup_time'] == '' ) {
                        $content = "<br/><b>Please <a href='{$args['base_url']}/closed/pickuptime?d=" . $cur_order_dt->format('Y-m-d') . "'>Choose A Pickup Time</a> for your order.</b>";
                    } else {
                        $content = "<br/><b>Your Pickup Time: " . $order['pickup_time'] . "</b> <a href='{$args['base_url']}/closed/pickuptime?d=" . $cur_order_dt->format('Y-m-d') . "'>Change Time</a>";
                    }
                    $page['blocks'][] = array('type'=>'content', 'section'=>'order-date', 'html'=>$content);
                }

                if( isset($rc['order']['items']) && count($rc['order']['items']) > 0 ) {
                    $page['blocks'][] = array('type'=>'orderdetails', 'section'=>'order-details', 'size'=>'wide', 
                        'title'=>$rc['order']['order_date_text'], 
                        'base_url'=>$args['base_url'],
                        'order'=>$rc['order']);
                } else {
                    $page['blocks'][] = array('type'=>'message', 'title'=>'Order', 'size'=>'wide', 'level'=>'warning', 
                        'content'=>"Your order is currently empty.");
                }
            }
        }
    }

    return array('stat'=>'ok', 'page'=>$page);
}
?>
