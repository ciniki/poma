<?php
//
// Description
// -----------
// This function loads the current order and recalculates all the numbers and updates status if required.
//
// Arguments
// ---------
// ciniki:
// business_id:                 The business ID to check the session user against.
// method:                      The requested method.
//
// Returns
// -------
// <rsp stat='ok' />
//
function ciniki_poma_orderUpdateStatusBalance(&$ciniki, $business_id, $order_id) {
    
    //
    // Load business settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'intlSettings');
    $rc = ciniki_businesses_intlSettings($ciniki, $business_id);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    //
    // Load the order
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderLoad');
    $rc = ciniki_poma_orderLoad($ciniki, $business_id, $order_id);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $order = $rc['order'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');

    //
    // Recalculate the totals
    //
    $new_order = array(
        'subtotal_amount'=>0,
        'discount_amount'=>0,
        'total_amount'=>0,
        'total_savings'=>0,
        'paid_amount'=>0,
        'balance_amount'=>0,
        );
    $max_line_number = 0;
    if( isset($order['items']) && count($order['items']) > 0 ) {
        $subitemcount = 0;  // The basket items that have substitutions on them, not the individual count of subd items
        $subfeeitem = null;
        foreach($order['items'] as $iid => $item) {
            $unit_amount = $item['unit_amount'];
            if( isset($item['unit_discount_amount']) && $item['unit_discount_amount'] > 0 ) {
                $unit_amount = bcsub($unit_amount, $item['unit_discount_amount'], 6);
                if( $unit_amount < 0 ) {
                    $unit_amount = 0;
                }
            }
            if( isset($item['unit_discount_percentage']) && $item['unit_discount_percentage'] > 0 ) {
                $percentage = bcdiv($item['unit_discount_percentage'], 100, 4);
                $unit_amount = bcsub($unit_amount, bcmul($unit_amount, $percentage, 4), 4);
                if( $unit_amount < 0 ) {
                    $unit_amount = 0;
                }
            }
            if( $item['itype'] == 10 || $item['itype'] == 20 ) {
                $quantity = $item['weight_quantity'];
            } else {
                $quantity = $item['unit_quantity'];
            }
            $new_item = array();
            $new_item['subtotal_amount'] = round(bcmul($quantity, $item['unit_amount'], 6), 2);
            $new_item['total_amount'] = round(bcmul($quantity, $unit_amount, 6), 2);
            $new_item['discount_amount'] = bcsub(bcmul($quantity, $item['unit_amount'], 2), $new_item['total_amount'], 2);

            $new_order['subtotal_amount'] = bcadd($new_order['subtotal_amount'], $new_item['total_amount'], 2);
            if( $new_item['discount_amount'] > 0 ) {
                $new_order['total_savings'] = bcadd($new_order['total_savings'], $new_item['discount_amount'], 2);
            }
    
            $update_args = array();
            foreach(['subtotal_amount', 'discount_amount', 'total_amount'] as $field) {
                if( $item[$field] != $new_item[$field] ) {
                    $update_args[$field] = $new_item[$field];
                    $order['items'][$iid][$field] = $new_item[$field];
                }
            }
            if( count($update_args) > 0 ) {
                $rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.poma.orderitem', $item['id'], $update_args, 0x04);
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
            }
            if( $max_line_number < $item['line_number'] ) {
                $max_line_number = $item['line_number'];
            }

            // 
            // Check for item to keep track of sub fees
            //
            if( ($item['flags']&0x08) == 0x08 ) {
                $subfeeitem = $item;
            }

            //
            // Check for substitutions
            //
            if( ($item['flags']&0x02) == 0x02 ) {
                if( isset($item['subitems']) ) {
                    $sub_found = 0;
                    foreach($item['subitems'] as $subitem) {
                        //
                        // Check if the subitem was alter quantity 0x10 or was a substituted item 0x04
                        //
                        if( ($subitem['flags']&0x14) > 0 ) {
                            $sub_found++;
                        }
                    }
                    if( $sub_found > 0 ) {
                        $subitemcount += 1;
                    }
                }
            }
        }
        if( $subitemcount > 0 ) {
            if( $subfeeitem == null ) {
                $rc = ciniki_core_objectAdd($ciniki, $business_id, 'ciniki.poma.orderitem', array(
                    'line_number'=>$max_line_number+1,
                    'order_id'=>$order_id,
                    'parent_id'=>0,
                    'flags'=>0x28,
                    'itype'=>30,
                    'description'=>'Modification Fee',
                    'unit_quantity'=>$subitemcount,
                    'unit_amount'=>2,
                    'total_amount'=>bcmul($subitemcount, 2, 2),
                    'taxtype_id'=>0,
                    ), 0x04);
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
                //
                // Update order total
                //
                $new_order['subtotal_amount'] = bcadd($new_order['subtotal_amount'], 2, 2);
            } elseif( $subfeeitem['unit_quantity'] != $subitemcount ) {
                $update_args = array(
                    'unit_quantity'=>$subitemcount,
                    'total_amount'=>bcmul($subfeeitem['unit_amount'], $subitemcount, 2),
                    );
                $rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.poma.orderitem', $subfeeitem['id'], $update_args, 0x04);
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
                //
                // Update order total
                //
                $new_order['subtotal_amount'] = bcsub($new_order['subtotal_amount'], $subfeeitem['total_amount'], 2);
                $new_order['subtotal_amount'] = bcadd($new_order['subtotal_amount'], bcmul($subfeeitem['unit_amount'], $subitemcount, 2), 2);
            }
        } elseif( $subfeeitem != null ) {
            //
            // No sub fees, remove the sub fee item
            //
            $rc = ciniki_core_objectDelete($ciniki, $business_id, 'ciniki.poma.orderitem', $subfeeitem['id'], $subfeeitem['uuid'], 0x04);
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            //
            // Update order total
            //
            $new_order['subtotal_amount'] = bcsub($new_order['subtotal_amount'], $subfeeitem['total_amount'], 2);
        }
    }

    $new_order['total_amount'] = $new_order['subtotal_amount'];
    if( isset($order['subtotal_discount_amount']) && $order['subtotal_discount_amount'] > 0 ) {
        $new_order['total_amount'] = bcsub($new_order['total_amount'], $order['subtotal_discount_amount'], 2);
        if( $new_order['total_amount'] < 0 ) {
            $new_order['total_amount'] = 0;
        }
        $new_order['discount_amount'] = bcadd($new_order['discount_amount'], $order['subtotal_discount_amount'], 2);
    }
    if( isset($order['subtotal_discount_percentage']) && $order['subtotal_discount_percentage'] > 0 ) {
        $percentage = bcdiv($order['subtotal_discount_percentage'], 100, 4);
        $discount_amount = bcmul($new_order['subtotal_amount'], $percentage, 2);
        $new_order['total_amount'] = bcsub($new_order['total_amount'], $discount_amount, 2);
        if( $new_order['total_amount'] < 0 ) {
            $new_order['total_amount'] = 0;
        }
        $new_order['discount_amount'] = bcadd($new_order['discount_amount'], $discount_amount, 2);
    }

    if( $new_order['discount_amount'] > 0 ) {
        $new_order['total_savings'] = bcadd($new_order['total_savings'], $new_order['discount_amount'], 2);
    }

    //
    // FIXME: Calculate payments for this order
    //
    $strsql = "SELECT id, ledger_id, payment_type, amount "
        . "FROM ciniki_poma_order_payments "
        . "WHERE order_id = '" . ciniki_core_dbQuote($ciniki, $order_id) . "' "
        . "AND business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'payment');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['rows']) ) {
        $payments = $rc['rows'];
    } else {
        $payments = array();
    }

    $new_order['paid_amount'] = 0;
    foreach($payments as $payment) {
        $new_order['paid_amount'] = bcadd($new_order['paid_amount'], $payment['amount'], 6);
    }
    $new_order['balance_amount'] = bcsub($new_order['total_amount'], $new_order['paid_amount'], 6);
    if( $new_order['total_amount'] > 0 && $new_order['balance_amount'] <= 0 && $new_order['payment_status'] != 50 ) {
        $new_order['payment_status'] = 50;
    }

    //
    // Update the order
    //
    $update_args = array();
    foreach(['payment_status', 'subtotal_amount', 'discount_amount', 'total_amount', 'total_savings', 'paid_amount', 'balance_amount'] as $field) {
        if( isset($new_order[$field]) && $order[$field] != $new_order[$field] ) {
            $update_args[$field] = $new_order[$field];
            $order[$field] = $new_order[$field];
        }
    }
    if( count($update_args) > 0 ) {
        $rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.poma.order', $order['id'], $update_args, 0x04);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
    }

    return array('stat'=>'ok', 'order'=>$order);
}
?>
