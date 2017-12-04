<?php
//
// Description
// ===========
// This method will return all the information about an customer ledger entry.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the customer ledger entry is attached to.
// entry_id:          The ID of the customer ledger entry to get the details for.
//
// Returns
// -------
//
function ciniki_poma_customerLedgerGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'entry_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Customer Ledger Entry'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.customerLedgerGet');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'timeFormat');
    $time_format = ciniki_users_timeFormat($ciniki, 'php');

    //
    // Return default for new Customer Ledger Entry
    //
    if( $args['entry_id'] == 0 ) {
        $dt = new DateTime('now', new DateTimezone($intl_timezone));
        $entry = array('id'=>0,
            'customer_id'=>'',
            'order_id'=>'0',
            'transaction_type'=>'',
            'transaction_date_date'=>$dt->format($date_format),
            'transaction_date_time'=>$dt->format($time_format),
            'source'=>'0',
            'description'=>'',
            'customer_amount'=>'',
            'transaction_fees'=>'',
            'tenant_amount'=>'',
            'notes'=>'',
        );
    }

    //
    // Get the details for an existing Customer Ledger Entry
    //
    else {
        $strsql = "SELECT ciniki_poma_customer_ledgers.id, "
            . "ciniki_poma_customer_ledgers.customer_id, "
            . "ciniki_poma_customer_ledgers.order_id, "
            . "ciniki_poma_customer_ledgers.transaction_type, "
            . "ciniki_poma_customer_ledgers.transaction_date, "
            . "ciniki_poma_customer_ledgers.transaction_date AS transaction_date_date, "
            . "ciniki_poma_customer_ledgers.transaction_date AS transaction_date_time, "
            . "ciniki_poma_customer_ledgers.source, "
            . "ciniki_poma_customer_ledgers.description, "
            . "ciniki_poma_customer_ledgers.customer_amount, "
            . "ciniki_poma_customer_ledgers.transaction_fees, "
            . "ciniki_poma_customer_ledgers.tenant_amount, "
            . "ciniki_poma_customer_ledgers.balance, "
            . "ciniki_poma_customer_ledgers.notes "
            . "FROM ciniki_poma_customer_ledgers "
            . "WHERE ciniki_poma_customer_ledgers.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND ciniki_poma_customer_ledgers.id = '" . ciniki_core_dbQuote($ciniki, $args['entry_id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'entries', 'fname'=>'id', 
                'fields'=>array('customer_id', 'order_id', 'transaction_type', 'transaction_date', 
                    'transaction_date_date', 'transaction_date_time',
                    'source', 'description', 'customer_amount', 'transaction_fees', 'tenant_amount', 'balance', 'notes'),
                'utctotz'=>array(
                    'transaction_date_date'=>array('timezone'=>$intl_timezone, 'format'=>$date_format),
                    'transaction_date_time'=>array('timezone'=>$intl_timezone, 'format'=>$time_format),
                    ),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.110', 'msg'=>'Customer Ledger Entry not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['entries'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.111', 'msg'=>'Unable to find Customer Ledger Entry'));
        }
        $entry = $rc['entries'][0];
    }

    return array('stat'=>'ok', 'entry'=>$entry);
}
?>
