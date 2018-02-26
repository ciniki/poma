<?php
//
// Description
// -----------
// This function returns the int to text mappings for the module.
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_poma_maps(&$ciniki) {
    //
    // Build the maps object
    //
    $maps = array();
    $maps['orderdate'] = array(
        'status'=>array(
            '5'=>'Pending',
            '10'=>'Open',
            '20'=>'Open - Repeats Added',
            '30'=>'Substitutions',
            '50'=>'Locked',
            '90'=>'Closed',
        ),
    );
    $maps['order'] = array(
        'status'=>array(
            '0'=>'',
            '10'=>'Open',
            '30'=>'Closed',
            '50'=>'Ready',
            '70'=>'Delivered',
        ),
        'payment_status'=>array(
            '0'=>'',
            '10'=>'Payment Required',
            '40'=>'Deposit',
            '50'=>'Paid',
        ),
    );
    $maps['queueditem'] = array(
        'status'=>array(
            '10'=>'Queued',
            '40'=>'Ordered',
            '90'=>'Invoiced',
        ),
    );
    $maps['customerledger'] = array('source'=>array(
        '0'=>'',
        '10'=>'Paypal',
        '20'=>'Square',
        '50'=>'Visa',
        '55'=>'Mastercard',
        '60'=>'Discover',
        '65'=>'Amex',
        '90'=>'Interac',
        '100'=>'Cash',
        '105'=>'Cheque',
        '110'=>'Email',
        '120'=>'Other',
        ));
    $maps['note'] = array(
        'status'=>array(
            '0'=>'',
            '10'=>'Active',
            '60'=>'Archived',
        ),
    );
    return array('stat'=>'ok', 'maps'=>$maps);
}
?>
