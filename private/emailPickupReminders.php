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
function ciniki_poma_emailPickupReminders(&$ciniki, $business_id, $date_id) {
    
    //
    // Load the date details
    //
    $strsql = "SELECT status, flags "
        . "FROM ciniki_poma_order_dates "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $date_id) . "' "
        . "AND business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['date']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.149', 'msg'=>'Order date not found.'));
    }
    $date = $rc['date'];

    //
    // Make sure the order date is locked for pickup reminders
    //
    if( $date['status'] != 50 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.150', 'msg'=>'Order date is not locked, unable to send pickup reminders'));
    }
    if( ($date['flags']&0x40) != 0x40 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.151', 'msg'=>'Order date is not locked, unable to send pickup reminders'));
    }

    $strsql = "SELECT id "
        . "FROM ciniki_poma_orders "
        . "WHERE date_id = '" . ciniki_core_dbQuote($ciniki, $date_id) . "' "
        . "AND business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "AND (flags&0x40) = 0 "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['rows']) || count($rc['rows']) == 0 ) {
        $orders = array();
    } else {
        $orders = $rc['rows'];
    }

    //
    // Load the email settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQueryDash');
    $rc = ciniki_core_dbDetailsQueryDash($ciniki, 'ciniki_poma_settings', 'business_id', $business_id, 'ciniki.poma', 'settings', 'email-pickup-reminder');
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
    if( !isset($settings['email-pickup-reminder-subject']) || $settings['email-pickup-reminder-subject'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.152', 'msg'=>'No order email subject specified'));
    }
    if( !isset($settings['email-pickup-reminder-message']) || $settings['email-pickup-reminder-message'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.153', 'msg'=>'No order email message specified'));
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderLoad');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'templates', 'invoice');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'mail', 'hooks', 'addMessage');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'hooks', 'customerDetails');

    //
    // Load each order and send
    //
    foreach($orders as $oid => $o) {
        $order_id = $o['id'];

        //
        // Load the order
        //
        $rc = ciniki_poma_templates_invoice($ciniki, $business_id, $order_id);
//        $rc = ciniki_poma_orderLoad($ciniki, $business_id, $order_id);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $order = $rc['order'];
        $pdf = $rc['pdf'];
        $filename = $rc['filename'];
    
        //
        // Skip this order if it's already been emailed
        //
        if( ($order['flags']&0x40) == 0x40 ) {
            continue;
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
        $subject = $settings['email-pickup-reminder-subject'];
        $html_message = $settings['email-pickup-reminder-message'];
        $text_message = $settings['email-pickup-reminder-message'];

        //
        // Load the customer
        //
        if( !isset($order['customer_id']) || $order['customer_id'] == '' || $order['customer_id'] < 1 ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.155', 'msg'=>'No customer attached to the order, we are unable to send the email.'));
        }
        $rc = ciniki_customers_hooks_customerDetails($ciniki, $business_id, 
            array('customer_id'=>$order['customer_id'], 'phones'=>'no', 'emails'=>'yes', 'addresses'=>'no', 'subscriptions'=>'no'));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['customer']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.156', 'msg'=>'No customer attached to the invoice, we are unable to send the email.'));
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
        $html_message = str_ireplace('{_orderitems_}', $html_items, $html_message);

        $text_message = str_ireplace('{_firstname_}', $customer['first'], $text_message);
        $text_message = str_ireplace('{_orderdate_}', $order['order_date_text'], $text_message);
        $text_message = str_ireplace('{_ordernumber_}', $order['order_number'], $text_message);
        $text_message = str_ireplace('{_orderitems_}', $text_items, $text_message);

        //
        // Add the message to the outgoing queue
        //
        $rc = ciniki_mail_hooks_addMessage($ciniki, $business_id, array(
            'object'=>'ciniki.poma.order',
            'object_id'=>$order_id,
            'customer_id'=>$order['customer_id'],
            'customer_email'=>$customer['emails'][0]['email']['address'],
            'customer_name'=>(isset($customer['display_name'])?$customer['display_name']:''),
            'subject'=>$subject,
            'html_content'=>$html_message,
            'text_content'=>$text_message,
            'attachments'=>array(array('content'=>$pdf->Output('invoice', 'S'), 'filename'=>$filename)),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.157', 'msg'=>'Unable to create mail message.', 'err'=>$rc['err']));
        }
        
        //
        // Mark the pickup reminder for this order as emailed
        //
        $rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.poma.order', $order_id, array('flags'=>(int)($order['flags'] | 0x40)), 0x04);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
    }

    //
    // All was successful, mark the date as done for pickup reminders
    //
    $rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.poma.orderdate', $date_id, array('flags'=>(int)($date['flags']&~0x40)), 0x04);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    return array('stat'=>'ok');
}
?>
