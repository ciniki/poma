<?php
//
// Description
// ===========
// This method will produce a PDF of the order.
//
// Arguments
// ---------
// 
// Returns
// -------
//
function ciniki_poma_invoicePDF(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
        'order_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Invoice'), 
        'subject'=>array('required'=>'no', 'blank'=>'yes', 'default'=>'no', 'name'=>'Subject'),
        'textmsg'=>array('required'=>'no', 'blank'=>'yes', 'default'=>'no', 'name'=>'Text Message'),
        'email'=>array('required'=>'no', 'blank'=>'yes', 'default'=>'no', 'name'=>'Email PDF'),
        'template'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Invoice Template'),
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];

    //  
    // Make sure this module is activated, and
    // check permission to run this function for this business
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['business_id'], 'ciniki.poma.invoicePDF'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }

    if( isset($args['template']) && $args['template'] == 'rawinvoice' ) {
        $invoice_template = 'rawinvoice'; 
    } else {
        $invoice_template = 'invoice'; 
    }
    $rc = ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'templates', $invoice_template);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $fn = $rc['function_call'];

    if( isset($args['email']) && $args['email'] == 'yes' ) {
        $rc = $fn($ciniki, $args['business_id'], $args['order_id']);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        //
        // Email the pdf to the customer
        //
        $filename = $rc['filename'];
        $order = $rc['order'];
        $pdf = $rc['pdf'];

        //
        // Get the customer emails
        //
        if( !isset($order['customer_id']) || $order['customer_id'] == '' || $order['customer_id'] < 1 ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.119', 'msg'=>'No customer attached to the invoice, we are unable to send the email.'));
        }
        ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'hooks', 'customerDetails');
        $rc = ciniki_customers_hooks_customerDetails($ciniki, $args['business_id'], 
            array('customer_id'=>$order['customer_id'], 'phones'=>'no', 'emails'=>'yes', 'addresses'=>'no', 'subscriptions'=>'no'));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['customer']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.120', 'msg'=>'No customer attached to the invoice, we are unable to send the email.'));
        }
        $customer = $rc['customer'];

        //
        // if customer is set
        //
        if( !isset($customer['emails'][0]['email']['address']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.121', 'msg'=>"The customer doesn't have an email address, we are unable to send the email."));
        }

        if( isset($args['subject']) && isset($args['textmsg']) ) {
            $subject = $args['subject'];
            $textmsg = $args['textmsg'];
        } /*else {
            $subject = 'Invoice #' . $invoice['invoice_number'];
            if( isset($poma_settings['invoice-email-message']) && $poma_settings['invoice-email-message'] != '' ) {
                $textmsg = $poma_settings['invoice-email-message'];
            } else {
                $textmsg = 'Please find your invoice attached.';
            }
        } */

        //
        // Start transaction
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
        $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.mail');
        if( $rc['stat'] != 'ok' ) { 
            return $rc;
        }   

        //
        // Add to the mail module
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'mail', 'hooks', 'addMessage');
        $rc = ciniki_mail_hooks_addMessage($ciniki, $args['business_id'], array(
            'object'=>'ciniki.poma.order',
            'object_id'=>$args['order_id'],
            'customer_id'=>$order['customer_id'],
            'customer_email'=>$customer['emails'][0]['email']['address'],
            'customer_name'=>(isset($customer['display_name'])?$customer['display_name']:''),
            'subject'=>$subject,
            'html_content'=>$textmsg,
            'text_content'=>$textmsg,
            'attachments'=>array(array('content'=>$pdf->Output('invoice', 'S'), 'filename'=>$filename)),
            ));
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.mail');
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.122', 'msg'=>'Unable to create mail message.', 'err'=>$rc['err']));
        }
        $ciniki['emailqueue'][] = array('mail_id'=>$rc['id'], 'business_id'=>$args['business_id']);

        //
        // Commit the transaction
        //
        $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.mail');
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.mail');
            return $rc;
        }

        return array('stat'=>'ok');
    }

    $rc = $fn($ciniki, $args['business_id'], $args['order_id']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    if( isset($rc['pdf']) ) {
        $rc['pdf']->Output($rc['filename'], 'D');
    }

    return array('stat'=>'exit');
}
?>
