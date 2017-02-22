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
function ciniki_poma_orderEmailGet(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
        'order_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Invoice'), 
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];

    //
    // Load the settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQueryDash');
    $rc = ciniki_core_dbDetailsQueryDash($ciniki, 'ciniki_poma_settings', 'business_id', $args['business_id'], 'ciniki.poma', 'settings', 'email');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['settings']) ) {
        $settings = $rc['settings'];
    } else {
        $settings = array();
    }
    
    //
    // Get the invoice record
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderLoad');
    $rc = ciniki_poma_orderLoad($ciniki, $args['business_id'], $args['order_id']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $order = $rc['order'];

    //
    // Load the customer
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
    // Setup default email
    //
    $email = array(
        'subject'=>'',
        'textmsg'=>'',
        );

    //
    // Setup the email
    //
    if( $order['payment_status'] == 0 ) {
        if( isset($settings['email-order-details-subject']) && $settings['email-order-details-subject'] != '' ) {
            $email['subject'] = $settings['email-order-details-subject'];
        } 
        if( isset($settings['email-order-details-message']) && $settings['email-order-details-message'] != '' ) {
            $email['textmsg'] = $settings['email-order-details-message'];
        } 
    } elseif( $order['payment_status'] > 0 && $order['payment_status'] < 50 ) {
        if( isset($settings['email-invoice-unpaid-subject']) && $settings['email-invoice-unpaid-subject'] != '' ) {
            $email['subject'] = $settings['email-invoice-unpaid-subject'];
        } 
        if( isset($settings['email-invoice-unpaid-message']) && $settings['email-invoice-unpaid-message'] != '' ) {
            $email['textmsg'] = $settings['email-invoice-unpaid-message'];
        } 
    } elseif( $order['payment_status'] == 50 ) {
        if( isset($settings['email-invoice-paid-subject']) && $settings['email-invoice-paid-subject'] != '' ) {
            $email['subject'] = $settings['email-invoice-paid-subject'];
        } 
        if( isset($settings['email-invoice-paid-message']) && $settings['email-invoice-paid-message'] != '' ) {
            $email['textmsg'] = $settings['email-invoice-paid-message'];
        } 
    } 

    //
    // Make substitutions
    //
    $email['subject'] = str_replace('{_firstname_}', $customer['first'], $email['subject']); 
    $email['subject'] = str_replace('{_orderdate_}', $order['order_date_text'], $email['subject']); 
    $email['subject'] = str_replace('{_ordernumber_}', $order['order_number'], $email['subject']); 

    $email['textmsg'] = str_replace('{_firstname_}', $customer['first'], $email['textmsg']); 
    $email['textmsg'] = str_replace('{_orderdate_}', $order['order_date_text'], $email['textmsg']); 
    $email['textmsg'] = str_replace('{_ordernumber_}', $order['order_number'], $email['textmsg']); 

    return array('stat'=>'ok', 'email'=>$email);
}
?>
