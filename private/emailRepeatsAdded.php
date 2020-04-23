<?php
//
// Description
// -----------
// This function will email a customer their order after the repeat items have been added.
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure.
// tnid:     The ID of the tenant to get poma web options for.
//
//
// Returns
// -------
//
function ciniki_poma_emailRepeatsAdded(&$ciniki, $tnid, $order_id, $added_items) {
    //
    // Load the order
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderLoad');
    $rc = ciniki_poma_orderLoad($ciniki, $tnid, $order_id);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $order = $rc['order'];

    //
    // Load the email settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQueryDash');
    $rc = ciniki_core_dbDetailsQueryDash($ciniki, 'ciniki_poma_settings', 'tnid', $tnid, 'ciniki.poma', 'settings', 'email-repeats-added');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['settings']) ) {
        $settings = $rc['settings'];
    } else {
        $settings = array();
    }

    //
    // Check email template exists
    //
    if( !isset($settings['email-repeats-added-subject']) || $settings['email-repeats-added-subject'] == '' ) {
        return array('stat'=>'warn', 'err'=>array('code'=>'ciniki.poma.172', 'msg'=>'No order email subject specified'));
    }
    if( !isset($settings['email-repeats-added-message']) || $settings['email-repeats-added-message'] == '' ) {
        return array('stat'=>'warn', 'err'=>array('code'=>'ciniki.poma.173', 'msg'=>'No order email message specified'));
    }

    //
    // Prepare the item list
    //
    $html_items = '';
    $text_items = '';

    if( !isset($added_items) || count($added_items) == 0 ) {
        $html_items = "<b>Your order is currently empty.</b>";
        $text_items = "Your order is currently empty.";
    } else {
        $html_items = "<table cellpadding='7' cellspacing='0'>";
        $html_items .= "<tr><th>Item</th><th>Quantity/Price</th><th style='text-align: right;'>Total</th></tr>";
        foreach($added_items as $added_item) {
            //
            // Find the item in the ordered items
            //
            $item = null;
            foreach($order['items'] as $order_item) {
                if( $added_item['id'] == $order_item['id'] ) {
                    $item = $order_item;
                    break;
                }
            }
            if( $item == null ) {
                return array('stat'=>'ok', 'err'=>array('code'=>'ciniki.poma.174', 'msg'=>'No order email message specified'));
            }

            if( ($item['flags']&0x0200) == 0x0200 ) {
                $html_items .= "<tr><td>" . $item['description'] . "</td><td>" . $item['quantity'] . "</td><td></td></tr>";
                $text_items .= $item['description'] . ' - ' . $item['quantity'] . "\n";
            } else {
                $quantity_text = (($item['quantity']>0&&$item['quantity']!=1)?($item['quantity'].' @ '):'')
                    . '$' . number_format($item['unit_amount'], 2);
                $html_items .= "<tr><td>" . $item['description'] . "</td><td>" 
                    . $quantity_text 
                    . ($item['discount_text'] != '' ? "<br/>" . $item['discount_text'] : '')
                    . ($item['deposit_text'] != '' ? "<br/>" . $item['deposit_text'] : '')
                    . "</td><td style='text-align: right;'>"
                    . '$' . number_format($item['total_amount'], 2)
                    . ($item['taxtype_name'] != '' ? "<br/>" . $item['taxtype_name'] : '')
                    . "</td></tr>";
                $text_items .= $item['description'] 
                    . ' - ' 
                    . $quantity_text
                    . ($item['discount_text'] != '' ? " (" . $item['discount_text'] . ")" : '')
                    . ($item['deposit_text'] != '' ? " (" . $item['deposit_text'] . ")" : '')
                    . ' - ' 
                    . ' = $' . number_format($item['total_amount'], 2)
                    . "\n";
            }
        }

//        $html_items .= "<tr><th style='text-align: right;' colspan='2'>Total</th><th style='text-align: right;'>$" . number_format($order['total_amount'], 2) . "</th></tr>";
        $html_items .= "</table>";
    }

    //
    // Format the email
    //
    $subject = $settings['email-repeats-added-subject'];
    $html_message = $settings['email-repeats-added-message'];
    $text_message = $settings['email-repeats-added-message'];

    //
    // If the {_addeditems_} was not specified in the message, attach to bottom of message
    //
    if( strpos($html_message, '{_addeditems_}') !== FALSE ) {
        $html_message = str_replace('{_addeditems_}', $html_items, $html_message);
    } else {
//        $html_message .= "<br/>" . $html_items;
    }
    if( strpos($text_message, '{_addeditems_}') !== FALSE ) {
        $text_message = str_replace('{_addeditems_}', $text_items, $text_message);
    } else {
//        $text_message .= "\n" . $text_items;
    } 

    //
    // Load the customer
    //
    if( !isset($order['customer_id']) || $order['customer_id'] == '' || $order['customer_id'] < 1 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.175', 'msg'=>'No customer attached to the order, we are unable to send the email.'));
    }
    ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'hooks', 'customerDetails');
    $rc = ciniki_customers_hooks_customerDetails($ciniki, $tnid, 
        array('customer_id'=>$order['customer_id'], 'phones'=>'no', 'emails'=>'yes', 'addresses'=>'no', 'subscriptions'=>'no'));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['customer']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.176', 'msg'=>'No customer attached to the invoice, we are unable to send the email.'));
    }
    $customer = $rc['customer'];

    //
    // Make substitutions
    //
    $subject = str_ireplace('{_firstname_}', $customer['first'], $subject);
    $subject = str_ireplace('{_orderdate_}', $order['order_date_text'], $subject);
    $subject = str_ireplace('{_ordernumber_}', $order['order_number'], $subject);

    $html_message = str_ireplace('{_firstname_}', $customer['first'], $html_message);
    $html_message = str_ireplace('{_orderdate_}', $order['order_date_text'], $html_message);
    $html_message = str_ireplace('{_ordernumber_}', $order['order_number'], $html_message);

    $text_message = str_ireplace('{_firstname_}', $customer['first'], $text_message);
    $text_message = str_ireplace('{_orderdate_}', $order['order_date_text'], $text_message);
    $text_message = str_ireplace('{_ordernumber_}', $order['order_number'], $text_message);

    //
    // Add the message to the outgoing queue
    //
    error_log("Add emamil to " . $customer['display_name'] . " - " . $subject);
    error_log($text_message);
    ciniki_core_loadMethod($ciniki, 'ciniki', 'mail', 'hooks', 'addMessage');
    $rc = ciniki_mail_hooks_addMessage($ciniki, $tnid, array(
        'object'=>'ciniki.poma.order',
        'object_id'=>$order_id,
        'customer_id'=>$order['customer_id'],
        'customer_email'=>$customer['emails'][0]['email']['address'],
        'customer_name'=>(isset($customer['display_name'])?$customer['display_name']:''),
        'subject'=>$subject,
        'html_content'=>$html_message,
        'text_content'=>$text_message,
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.177', 'msg'=>'Unable to create mail message.', 'err'=>$rc['err']));
    }
    
    return array('stat'=>'ok');
}
?>
