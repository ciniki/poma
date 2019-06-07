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
function ciniki_poma_web_processRequestUpcoming(&$ciniki, $settings, $tnid, $args) {

    
    $page = array(
        'title'=>$args['page_title'],
        'breadcrumbs'=>$args['breadcrumbs'],
        'blocks'=>array(),
        'submenu'=>array(),
        );

    $page['breadcrumbs'][] = array('name'=>'Upcoming', 'url'=>$args['base_url']);
    
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
    // Get the list of available dates
    //
    $strsql = "SELECT ciniki_poma_order_dates.id, "
        . "ciniki_poma_order_dates.order_date, "
        . "ciniki_poma_order_dates.display_name, "
        . "ciniki_poma_order_dates.status, "
        . "ciniki_poma_order_dates.flags "
        . "FROM ciniki_poma_order_dates "
//        . "LEFT JOIN ciniki_poma_orders ON ("
//            . "ciniki_poma_order_dates.id = ciniki_poma_orders.date_id "
//            . "AND ciniki_poma_orders.customer_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['customer']['id']) . "' "
//            . ") "
        . "WHERE ciniki_poma_order_dates.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND ciniki_poma_order_dates.order_date >= '" . ciniki_core_dbQuote($ciniki, $dt->format('Y-m-d')) . "' "
        . "AND ciniki_poma_order_dates.status > 5 "
        . "AND ciniki_poma_order_dates.status < 50 "
        . "ORDER BY order_date ASC "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'dates', 'fname'=>'id', 'fields'=>array('id', 'order_date', 'display_name', 'status', 'flags')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'500', 'err'=>array('code'=>'ciniki.poma.19', 'msg'=>'Unable to find order dates'));
    }
    if( !isset($rc['dates']) || count($rc['dates']) == 0 ) {
        $page['blocks'][] = array('type'=>'formmessage', 'level'=>'error', 'message'=>"Oops, it looks like we forgot to add more available dates. Please contact us and we'll get more dates added.");
    } else {
        $content = "<form action='' method='POST'>"
            . "Your current order date: "
            . "<input type='hidden' name='action' value='switchdate' />";
        $content .= "<select name='date_id' onchange='this.form.submit();'>";
        foreach($rc['dates'] as $odate) {
            if( isset($_POST['action']) && $_POST['action'] == 'switchdate' && isset($_POST['date_id']) && $_POST['date_id'] > 0 
                && $_POST['date_id'] == $odate['id']
                && $_POST['date_id'] != $ciniki['session']['ciniki.poma']['date']['id'] 
                ) {
                $odate['order_dt'] = new DateTime($odate['order_date'] . ' 12:00:00', new DateTimezone($intl_timezone));
                $odate['order_date_text'] = $odate['order_dt']->format('M d, Y');
                $ciniki['session']['ciniki.poma']['date'] = $odate;
                header("Location: " . $_SERVER['REDIRECT_URL'], true, 303);
                return array('stat'=>'exit');
            }
            if( isset($ciniki['session']['ciniki.poma']['date']['id']) && $odate['id'] == $ciniki['session']['ciniki.poma']['date']['id'] ) {
                $content .= "<option value='" . $odate['id'] . "' selected>" . $odate['display_name'] . "</option>";
            } else {
                $content .= "<option value='" . $odate['id'] . "'>" . $odate['display_name'] . "</option>";
            }
        }
        $content .= "</select></form>";
        $page['blocks'][] = array('type'=>'content', 'section'=>'order-date', 'html'=>$content);
    }
//    $page['blocks'][] = array('type'=>'content', 'html'=>"<pre class='wide'>" . print_r($ciniki['session']['ciniki.poma'], true) . "</pre>");
//    $page['blocks'][] = array('type'=>'content', 'html'=>"<pre class='wide'>" . print_r($_SERVER, true) . "</pre>");

    //
    // Display the current order
    //
    if( isset($ciniki['session']['ciniki.poma']['date']['id']) && $ciniki['session']['ciniki.poma']['date']['id'] > 0 ) {
        //
        // Load the current order
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'orderLoad');
        $rc = ciniki_poma_web_orderLoad($ciniki, $settings, $tnid, array(
            'date_id'=>$ciniki['session']['ciniki.poma']['date']['id'],
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['order']) ) {
            $page['blocks'][] = array('type'=>'formmessage', 'title'=>'Order', 'size'=>'wide', 'level'=>'error', 
                'message'=>"Oops, we were unable to find your order.");
        } else {
            $order = $rc['order'];
            
            if( isset($rc['order']['items']) && count($rc['order']['items']) > 0 ) {
                $page['blocks'][] = array('type'=>'orderdetails', 'section'=>'order-details', 'size'=>'wide', 
                    'title'=>$rc['order']['order_date_text'], 
                    'api_item_update'=>$api_item_update,
                    'api_repeat_update'=>$api_repeat_update,
                    'base_url'=>$args['base_url'],
                    'order'=>$rc['order']);
            } else {
                $page['blocks'][] = array('type'=>'message', 'title'=>'Order', 'size'=>'wide', 'level'=>'warning', 
                    'content'=>"Your order is currently empty.");
            }
        }

        //
        // Check for any repeats that will be ordered on this date
        //
    }

    return array('stat'=>'ok', 'page'=>$page);
}
?>
