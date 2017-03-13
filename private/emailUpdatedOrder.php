<?php
//
// Description
// -----------
// This function will email a customer their order after 30 minutes after it was last updated.
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure.
// business_id:     The ID of the business to get poma web options for.
//
//
// Returns
// -------
//
function ciniki_poma_emailUpdatedOrder(&$ciniki, $business_id, $order_id) {

    //
    // Load the order
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderLoad');
    $rc = ciniki_poma_orderLoad($ciniki, $business_id, $order_id);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $order = $rc['order'];

    //
    // Load the email settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQueryDash');
    $rc = ciniki_core_dbDetailsQueryDash($ciniki, 'ciniki_poma_settings', 'business_id', $business_id, 'ciniki.poma', 'settings', 'email-updated-order');
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
    if( !isset($settings['email-updated-order-subject']) || $settings['email-updated-order-subject'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.135', 'msg'=>'No order email subject specified'));
    }
    if( !isset($settings['email-updated-order-message']) || $settings['email-updated-order-message'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.143', 'msg'=>'No order email message specified'));
    }

    //
    // Prepare the item list
    //
    $html_items = '';
    $text_items = '';

    if( !isset($order['items']) || count($order['items']) == 0 ) {
        $html_items = "<b>Your order is currently empty.</b>";
        $text_items = "Your order is currently empty.";
    } else {
        $html_items = "<table cellpadding='7' cellspacing='0'>";
        $html_items .= "<tr><th>Item</th><th>Quantity/Price</th><th style='text-align: right;'>Total</th></tr>";
        foreach($order['items'] as $item) {
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
            if( isset($item['subitems']) && count($item['subitems']) > 0 ) {
                foreach($item['subitems'] as $subitem) {
                    $quantity_text = $subitem['quantity'];
                    $html_items .= "<tr><td>&nbsp;&nbsp;-&nbsp;" . $subitem['description'] . "</td><td>" 
                        . $quantity_text 
                        . "</td><td>"
                        . "</td></tr>";
                    $text_items .= "    - " . $quantity_text . ' - ' . $item['description'] . "\n";
                }
            }
        }

        $html_items .= "<tr><th style='text-align: right;' colspan='2'>Total</th><th style='text-align: right;'>$" . number_format($order['total_amount'], 2) . "</th></tr>";
        $html_items .= "</table>";
    }

    //
    // Format the email
    //
    $subject = $settings['email-updated-order-subject'];
    $html_message = $settings['email-updated-order-message'];
    $text_message = $settings['email-updated-order-message'];

    //
    // If the {_orderitems_} was not specified in the message, attach to bottom of message
    //
    if( strpos($html_message, '{_orderitems_}') !== FALSE ) {
        $html_message = str_replace('{_orderitems_}', $html_items, $html_message);
    } else {
        $html_message .= "<br/>" . $html_items;
    }
    if( strpos($text_message, '{_orderitems_}') !== FALSE ) {
        $text_message = str_replace('{_orderitems_}', $text_items, $text_message);
    } else {
        $text_message .= "\n" . $text_items;
    }

    //
    // Load the customer
    //
    if( !isset($order['customer_id']) || $order['customer_id'] == '' || $order['customer_id'] < 1 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.145', 'msg'=>'No customer attached to the order, we are unable to send the email.'));
    }
    ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'hooks', 'customerDetails');
    $rc = ciniki_customers_hooks_customerDetails($ciniki, $business_id, 
        array('customer_id'=>$order['customer_id'], 'phones'=>'no', 'emails'=>'yes', 'addresses'=>'no', 'subscriptions'=>'no'));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['customer']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.146', 'msg'=>'No customer attached to the invoice, we are unable to send the email.'));
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
    ciniki_core_loadMethod($ciniki, 'ciniki', 'mail', 'hooks', 'addMessage');
    $rc = ciniki_mail_hooks_addMessage($ciniki, $business_id, array(
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
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.147', 'msg'=>'Unable to create mail message.', 'err'=>$rc['err']));
    }
    
    //
    // Mark the order as emailed
    //
    if( ($order['flags']&0x10) == 0x10 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
        $rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.poma.order', $order_id, array('flags'=>(int)($order['flags']&~0x10)), 0x04);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
    }

    return array('stat'=>'ok');
}
?>
