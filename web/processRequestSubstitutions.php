<?php
//
// Description
// -----------
// This function will process a web request for past orders.
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
function ciniki_poma_web_processRequestSubstitutions(&$ciniki, $settings, $tnid, $args) {

    
    $page = array(
        'title'=>$args['page_title'],
        'breadcrumbs'=>$args['breadcrumbs'],
        'blocks'=>array(),
        'submenu'=>array(),
        );

    $api_substitution_add = 'ciniki/poma/orderSubstitutionAdd/';
    $api_substitution_update = 'ciniki/poma/orderSubstitutionUpdate/';

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

    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'orderItemFormat');

    //
    // Display the current order
    //
    if( isset($ciniki['session']['ciniki.poma']['date']['id']) && $ciniki['session']['ciniki.poma']['date']['id'] > 0 ) {
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
            return array('stat'=>'ok', 'page'=>$page);
        } else {
            $order = $rc['order'];
        }
    } else {
        $page['blocks'][] = array('type'=>'formmessage', 'level'=>'error', 
            'message'=>"Oops, it looks like we forgot to add more available dates. Please contact us and we'll get more dates added.");
        return array('stat'=>'ok', 'page'=>$page);
    }

    //
    // Check for item to make substitutions on
    //
    if( !isset($args['uri_split'][0]) || $args['uri_split'][0] == '' ) {
        $page['blocks'][] = array('type'=>'formmessage', 'level'=>'error', 
            'message'=>"Unable to find the item.");
        return array('stat'=>'ok', 'page'=>$page);
    }
    $item_id = $args['uri_split'][0];
    if( !isset($order['items'][$item_id]) ) {
        $page['blocks'][] = array('type'=>'formmessage', 'level'=>'error', 
            'message'=>"Oops, we couldn't find the item you requested. Please try again or contact us for help.");
        return array('stat'=>'ok', 'page'=>$page);
    }
    $item = $order['items'][$item_id];
//    $page['blocks'][] = array('type'=>'content', 'title'=>'Subs', 'html'=>"<pre>" . print_r($item_id, true) . "</pre>");
    if( !isset($item['flags']) || ($item['flags']&0x02) == 0 ) {
        $page['blocks'][] = array('type'=>'formmessage', 'level'=>'error', 
            'message'=>"Oops, we couldn't find the item you requested. Please try again or contact us for help.");
        return array('stat'=>'ok', 'page'=>$page);
    }
    if( !isset($item['subitems']) ) {
        $item['subitems'] = array();
    } else {
        $item['subitems'] = array_values($item['subitems']);
    }
    
    $page['breadcrumbs'][] = array('name'=>$item['description'] . ' - ' . $order['order_date_text'], 'url'=>$args['base_url'] . '/substitutions');

    $page['blocks'][] = array('type'=>'content', 'wide'=>'yes', 'content'=>"To customize your basket, adjust item quantities to enable other items to be added."
        . " Once your basket is full, you will not be able to add any more items.");

    //
    // Get the list of available items from modules
    //
    list($pkg, $mod, $obj) = explode('.', $item['object']);
    $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'itemSubstitutions');
    $substitutions = array();
    if( $rc['stat'] == 'ok' ) {
        $fn = $rc['function_call'];
        $args = array(
            'date_id'=>$order['date_id'],
            'object'=>$item['object'],
            'object_id'=>$item['object_id'],
            );
        $rc = $fn($ciniki, $tnid, $args);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['substitutions']) ) {
            $substitutions = $rc['substitutions'];
            // Remove items already in order
            foreach($substitutions as $sid => $sub) {
                foreach($item['subitems'] as $itm) {
                    if( $sub['object'] == $itm['object'] && $sub['object_id'] == $itm['object_id'] ) {
                        unset($substitutions[$sid]);
                    }
                }
            }
            foreach($substitutions as $sid => $sub) {
                if( $sub['itype'] == 10 ) {
                    $sub['weight_quantity'] = 1;
                } else {
                    $sub['unit_quantity'] = 1;
                }
                $sub['total_amount'] = $sub['unit_amount'];
                $rc = ciniki_poma_web_orderItemFormat($ciniki, $settings, $tnid, $sub);
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
                $substitutions[$sid] = $rc['item'];
//                $substitutions[$sid]['quantity_single'] = $rc['item']['quantity_single'];
//                $substitutions[$sid]['quantity_plural'] = $rc['item']['quantity_plural'];
            }
        }
    }

    //
    // Show the list of available substitution items
    //
    $page['blocks'][] = array('type'=>'ordersubstitutions', 
        'size'=>'wide',
        'subitems'=>$item['subitems'], 
        'substitutions'=>$substitutions,
//        'limit_total'=>(float)$item['total_amount'] + bcmul($item['total_amount'], 0.05, 2),
        'limit_total'=>(float)$item['total_amount'] + 1,
        'api_substitution_add'=>$api_substitution_add . $item_id . '/',
        'api_substitution_update'=>$api_substitution_update . $item_id . '/',
        );

    return array('stat'=>'ok', 'page'=>$page);
}
?>
