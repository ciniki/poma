<?php
//
// Description
// -----------
// This function will add an object to the queue
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_poma_queueUpdateObject(&$ciniki, $business_id, $args) {

    //
    // Check args
    //
    if( !isset($args['object']) || $args['object'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.163', 'msg'=>'No item specified.'));
    }
    if( !isset($args['object_id']) || $args['object_id'] < 1 || $args['object_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.164', 'msg'=>'No item specified.'));
    }
    if( !isset($args['customer_id']) || $args['customer_id'] == '' || $args['customer_id'] < 1 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.165', 'msg'=>'No customer specified.'));
    }
    if( !isset($args['quantity']) && !isset($args['add_quantity']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.166', 'msg'=>'No quantity specified.'));
    }

    //
    // Get the details for the item
    //
    list($pkg, $mod, $obj) = explode('.', $args['object']);
    $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'queueItemLookup');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.161', 'msg'=>'Unable to add item to queue.'));
    }
    $fn = $rc['function_call'];
    $rc = $fn($ciniki, $business_id, $args);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['item']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.162', 'msg'=>'Unable to add item to queue.'));
    }
    $item = $rc['item'];

    //
    // Start a transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'queueDepositAdd');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderUpdateStatusBalance');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.poma');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Check if the object already exists in the queue in an active state
    //
    $strsql = "SELECT items.id, items.uuid, items.customer_id, items.status, items.quantity, items.description "
        . "FROM ciniki_poma_queued_items AS items "
        . "WHERE items.customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
        . "AND items.object = '" . ciniki_core_dbQuote($ciniki, $args['object']) . "' "
        . "AND items.object_id = '" . ciniki_core_dbQuote($ciniki, $args['object_id']) . "' "
        . "AND items.status = 10 "
        . "AND items.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['item']) || count($rc['rows']) > 0 ) {
        $qitem = $rc['rows'][0];

        if( isset($args['add_quantity']) ) {
            $args['quantity'] = $qitem['quantity'] + $args['add_quantity'];
        }
        
        //
        // Check if item has deposits on it already
        //
        if( isset($item['qdeposit_amount']) && $item['qdeposit_amount'] > 0 ) {
            $strsql = "SELECT items.id, items.uuid, items.object, items.object_id, items.order_id, "
                . "items.unit_amount, items.unit_quantity, "
                . "orders.flags AS order_flags, "
                . "orders.payment_status "
                . "FROM ciniki_poma_orders AS orders, ciniki_poma_order_items AS items "
                . "WHERE orders.customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
                . "AND orders.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
                . "AND orders.id = items.order_id "
                . "AND items.object = 'ciniki.poma.queueditem' "
                . "AND items.object_id = '" . ciniki_core_dbQuote($ciniki, $qitem['id']) . "' "
                . "AND (items.flags&0x40) = 0x40 "
                . "AND items.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            if( isset($rc['rows']) ) {
                $deposits = $rc['rows'];
                $deposit_num_items = 0;     // The quantity of items the deposit is for
                foreach($deposits as $deposit) {
                    $deposit_num_items += $deposit['unit_quantity'];
                }
            }
        }

        if( $args['quantity'] == 0 ) {
            //
            // Remove the item
            //
            $rc = ciniki_core_objectDelete($ciniki, $business_id, 'ciniki.poma.queueditem', $qitem['id'], $qitem['uuid'], 0x04);
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }

            //
            // Remove deposits
            //
            if( isset($item['qdeposit_amount']) && $item['qdeposit_amount'] > 0 && isset($deposits) && count($deposits) > 0 ) {
                foreach($deposits as $deposit) {
                    //
                    // Remove from unpaid invoices
                    //
                    if( $deposit['payment_status'] < 50 ) {
                        $rc = ciniki_core_objectDelete($ciniki, $business_id, 'ciniki.poma.orderitem', $deposit['id'], $deposit['uuid'], 0x04);
                        if( $rc['stat'] != 'ok' ) {
                            return $rc;
                        }
                    }
                }
            }
        } else {
            //
            // Update the quantity
            //
            $new_quantity = $args['quantity'];
            $rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.poma.queueditem', $qitem['id'], array('quantity'=>$new_quantity), 0x04);
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            
            //
            // If a deposit is required, check to see how much deposit has already been paid, and add or update deposits if required
            //
            if( isset($item['qdeposit_amount']) && $item['qdeposit_amount'] > 0 ) {
                //
                // Check for existing deposits
                //
                if( isset($deposits) && count($deposits) > 0 ) {
                    if( $new_quantity > $deposit_num_items ) {
                        $add_quantity = $new_quantity - $deposit_num_items;
                        foreach($deposits as $deposit) {
                            //
                            // Find the first unpaid order with the deposit already added, and increase number
                            //
                            if( $deposit['payment_status'] < 50 ) {
                                $rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.poma.orderitem', $deposit['id'], 
                                    array('unit_quantity'=>$deposit['unit_quantity'] + $add_quantity), 0x04);
                                if( $rc['stat'] != 'ok' ) {
                                    return $rc;
                                }
                                $deposit_num_items = $new_quantity;

                                //
                                // Update the order totals
                                //
                                $rc = ciniki_poma_orderUpdateStatusBalance($ciniki, $business_id, $deposit['order_id']);
                                if( $rc['stat'] != 'ok' ) {
                                    ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                                    return $rc;
                                }

                                //
                                // Update the flag to mail the order to the customer
                                //
                                if( isset($deposit['order_flags']) && ($deposit['order_flags']&0x10) == 0 
                                    && isset($args['customer_id']) && $args['customer_id'] > 0 
                                    ) {
                                    $rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.poma.order', $deposit['order_id'], array('flags'=>$deposit['order_flags'] |= 0x10), 0x04);
                                    if( $rc['stat'] != 'ok' ) {
                                        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                                        return $rc;
                                    }
                                }
                                break;
                            }
                        }
                    } elseif( $new_quantity < $deposit_num_items ) {
                        $sub_quantity = $deposit_num_items - $new_quantity;
                        foreach($deposits as $deposit) {
                            //
                            // Find the first unpaid order with the deposit already added, and increase number
                            //
                            if( $deposit['payment_status'] < 50 ) { 
                                //
                                // Make sure quantity does not go negative
                                //
                                if( $sub_quantity > $deposit['unit_quantity'] ) {
                                    //
                                    // Remove the deposit
                                    //
                                    $rc = ciniki_core_objectDelete($ciniki, $business_id, 'ciniki.poma.orderitem', $deposit['id'], $deposit['uuid'], 0x04);
                                    if( $rc['stat'] != 'ok' ) {
                                        return $rc;
                                    }
                                    $sub_quantity -= $deposit['unit_quantity'];
                                    
                                } else {
                                    //
                                    // Update the deposit 
                                    //
                                    $rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.poma.orderitem', $deposit['id'], 
                                        array('unit_quantity'=>($deposit['unit_quantity'] - $sub_quantity)), 0x04);
                                    if( $rc['stat'] != 'ok' ) {
                                        return $rc;
                                    }
                                    $sub_quantity = 0;
                                }

                                //
                                // Update the order totals
                                //
                                $rc = ciniki_poma_orderUpdateStatusBalance($ciniki, $business_id, $deposit['order_id']);
                                if( $rc['stat'] != 'ok' ) {
                                    ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                                    return $rc;
                                }

                                //
                                // Update the flag to mail the order to the customer, and this is being done via the website as the customer
                                //
                                if( isset($deposit['order_flags']) && ($deposit['order_flags']&0x10) == 0 
                                    && isset($args['customer_id']) && $args['customer_id'] > 0 
                                    ) {
                                    $rc = ciniki_core_objectUpdate($ciniki, $business_id, 'ciniki.poma.order', $deposit['order_id'], array('flags'=>$deposit['order_flags'] |= 0x10), 0x04);
                                    if( $rc['stat'] != 'ok' ) {
                                        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                                        return $rc;
                                    }
                                }
                            }
                            if( $sub_quantity <= 0 ) {
                                break;
                            }
                        }
                    }
                } 

                //
                // Check if required new deposits
                //
                if( $new_quantity > $deposit_num_items ) {
                    $rc = ciniki_poma_queueDepositAdd($ciniki, $business_id, array(
                        'customer_id'=>$args['customer_id'],
                        'flags'=>0x40 & 0x20,
                        'description'=>'Deposit for ' . $item['description'],
                        'object'=>'ciniki.poma.queueditem',
                        'object_id'=>$qitem['id'],
                        'itype'=>30,
                        'unit_quantity'=>($new_quantity - $deposit_num_items),
                        'unit_suffix'=>'',
                        'weight_units'=>0,
                        'weight_quantity'=>0,
                        'unit_amount'=>$item['qdeposit_amount'],
                        ));
                    if( $rc['stat'] != 'ok' ) {
                        return $rc;
                    }
                }
            }
        }

    } else {
        //
        // Add the item
        //
        $dt = new DateTime('now', new DateTimezone('UTC'));
        $rc = ciniki_core_objectAdd($ciniki, $business_id, 'ciniki.poma.queueditem', array(
            'customer_id'=>$args['customer_id'],
            'status'=>10,
            'object'=>$args['object'],
            'object_id'=>$args['object_id'],
            'description'=>$item['description'],
            'quantity'=>(isset($args['add_quantity']) ? $args['add_quantity'] : $args['quantity']),
            'queued_date'=>$dt->format('Y-m-d H:i:s'),
            ), 0x04);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $queued_item_id = $rc['id'];

        //
        // Check if deposits required
        //
        if( isset($item['qdeposit_amount']) && $item['qdeposit_amount'] > 0 ) {
            $rc = ciniki_poma_queueDepositAdd($ciniki, $business_id, array(
                'customer_id'=>$args['customer_id'],
                'flags'=>0x40 & 0x20,
                'description'=>'Deposit for ' . $item['description'],
                'object'=>'ciniki.poma.queueditem',
                'object_id'=>$queued_item_id,
                'itype'=>30,
                'unit_quantity'=>$args['quantity'],
                'unit_suffix'=>'',
                'weight_units'=>0,
                'weight_quantity'=>0,
                'unit_amount'=>$item['qdeposit_amount'],
                ));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
        }
    }

    //
    // Commit the transaction
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.poma');
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
        return $rc;
    }

    return array('stat'=>'ok');
}
?>
