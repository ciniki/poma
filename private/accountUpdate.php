<?php
//
// Description
// -----------
// This function will update the customer ledger, and invoice status if required
//
// Arguments
// ---------
// ciniki:
// tnid:                 The tenant ID to check the session user against.
// method:                      The requested method.
//
// Returns
// -------
// <rsp stat='ok' />
//
function ciniki_poma_accountUpdate(&$ciniki, $tnid, $args) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');

    //
    // Set the default start record to at beginning of ledger, unless someething else is specified
    //
    $start_ledger_id = 0;
    $start_ledger_date = '0000-00-00';
    $prev_balance = 0;

    if( isset($args['order_id']) && $args['order_id'] > 0 ) {
        //
        // Get the order details
        //
        $strsql = "SELECT id, customer_id, payment_status, total_amount "
            . "FROM ciniki_poma_orders "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['order_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['order']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.134', 'msg'=>'Unable to find order to update customer ledger.'));
        }
        $order = $rc['order'];

        //
        // Get the accounting entry for the order
        //
        $strsql = "SELECT id, customer_id, order_id, transaction_date, customer_amount, balance "
            . "FROM ciniki_poma_customer_ledgers "
            . "WHERE order_id = '" . ciniki_core_dbQuote($ciniki, $args['order_id']) . "' "
            . "AND customer_id = '" . ciniki_core_dbQuote($ciniki, $order['customer_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'entry');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['entry']) ) {
            //
            // The order has not been invoiced and added to ledger yet, ignore
            //
            return array('stat'=>'ok');
        }
        $entry = $rc['entry'];
    
        //
        // Check if the total on the invoice is different from the amount in the ledger
        //
        if( $entry['customer_amount'] == $order['total_amount'] ) {
            //
            // Nothing to change, return ok
            //
            return array('stat'=>'ok');
        }

        //
        // Get previous entry
        //
        $strsql = "SELECT id, customer_id, order_id, transaction_date, customer_amount, balance "
            . "FROM ciniki_poma_customer_ledgers "
            . "WHERE customer_id = '" . ciniki_core_dbQuote($ciniki, $order['customer_id']) . "' "
            . "AND transaction_date <= '" . ciniki_core_dbQuote($ciniki, $entry['transaction_date']) . "' "
            . "AND id <> '" . ciniki_core_dbQuote($ciniki, $entry['id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "ORDER BY transaction_date DESC "
            . "LIMIT 1 "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'entry');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['entry']) ) {
            $prev_balance = 0;
        } else {
            $prev_balance = $rc['entry']['balance'];
        }
        
        //
        // Update the amount and balance of current entry
        //
        $update_args = array('customer_amount'=>$order['total_amount']);
        $new_balance = bcsub($prev_balance, $order['total_amount'], 6);
        if( $new_balance != $entry['balance'] ) {
            $update_args['balance'] = $new_balance;
        }
        $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.poma.customerledger', $entry['id'], $update_args, 0x04);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $customer_id = $order['customer_id'];
        $start_ledger_id = $entry['id'];
        $start_ledger_date = $entry['transaction_date'];
        $prev_balance = $new_balance;
    } elseif( isset($args['customer_id']) && $args['customer_id'] > 0 ) {
        $customer_id = $args['customer_id'];
    } else {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.171', 'msg'=>'Must specify an order or customer'));
    }


    //
    // Get all the ledger entries, and make sure balances are correct
    //

    // FIXME: Add processing for rebalance ledger and invoice transactions/invoice payment_status

    $strsql = "SELECT id, customer_id, order_id, transaction_type, transaction_date, customer_amount, balance "
        . "FROM ciniki_poma_customer_ledgers "
        . "WHERE customer_id = '" . ciniki_core_dbQuote($ciniki, $customer_id) . "' "
        . "";
    if( isset($start_ledger_date) ) {
        $strsql .= "AND transaction_date >= '" . ciniki_core_dbQuote($ciniki, $start_ledger_date) . "' "
            . "AND id <> '" . ciniki_core_dbQuote($ciniki, $start_ledger_id) . "' "
            . "";
    }
    $strsql .= "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "ORDER BY transaction_date ASC "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'entry');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['rows']) ) {
        $entries = $rc['rows'];
        foreach($entries as $entry) {
            $new_balance = $entry['balance'];
            if( $entry['transaction_type'] == 10 ) {
                $new_balance = bcadd($prev_balance, $entry['customer_amount'], 6);
            }
            elseif( $entry['transaction_type'] == 30 ) {
                $new_balance = bcsub($prev_balance, $entry['customer_amount'], 6);
            }
            elseif( $entry['transaction_type'] == 60 ) {
                $new_balance = bcadd($prev_balance, $entry['customer_amount'], 6);
            }
           
            //
            // Zero out any fractions left over
            // Not ideal way to do this, but fixed past mistakes with decimal places and taxes
            //
            if( floor(abs($new_balance)*100) != (abs($new_balance)*100) && floor(abs($new_balance)*100) == 0 ) {
                $new_balance = 0;
            }
            
            if( $new_balance != $entry['balance'] ) {
                $update_args = array('balance'=>$new_balance);
                $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.poma.customerledger', $entry['id'], $update_args, 0x04);
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
            }
            $prev_balance = $new_balance;
        }
    }

    return array('stat'=>'ok');
}
?>
