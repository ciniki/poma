<?php
//
// Description
// -----------
// This method returns the orders for a specific date, and the details of a specific order if specified.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Product for.
//
// Returns
// -------
//
function ciniki_poma_dateCheckout($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'date_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Date'),
        'order'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'New Order'),
        'order_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Order'),
        'customer_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Customer'),
        'new_object'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'New Object'),
        'new_object_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'new Object ID'),
        'item_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Item'),
        'new_quantity'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Quantity'),
        'new_unit_quantity'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Unit Quantity'),
        'new_weight_quantity'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Weight Quantity'),
        'action'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Action'),
        'newdate_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'New Date'),
        'pickup_time'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Pickup Time'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'foodmarket', 'private', 'checkAccess');
    $rc = ciniki_foodmarket_checkAccess($ciniki, $args['tnid'], 'ciniki.foodmarket.productList');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load poma maps
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'maps');
    $rc = ciniki_poma_maps($ciniki);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $maps = $rc['maps'];

    //
    // Load tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');

    $rsp = array('stat'=>'ok', 'dates'=>array(), 'open_orders'=>array(), 'closed_orders'=>array(), 
        'nplists'=>array('orderitems'=>array()),
        );

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');

    //
    // If the date wasn't set, then choose the closest date to now
    //
    if( !isset($args['date_id']) || $args['date_id'] == 0 ) {
        $strsql = "SELECT id, ABS(DATEDIFF(NOW(), order_date)) AS age "
            . "FROM ciniki_poma_order_dates "
            . "WHERE ciniki_poma_order_dates.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "ORDER BY age ASC "
            . "LIMIT 1 "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['date']['id']) ) {
            return array('stat'=>'ok', 'dates'=>array(), 'open_orders'=>array(), 'closed_orders'=>array(), 'order'=>array());
        }
        $args['date_id'] = $rc['date']['id'];
        $rsp['date_id'] = $rc['date']['id'];
    }

    //
    // Get all the dates from latest date to earliest
    //
    $strsql = "SELECT ciniki_poma_order_dates.id, "
        . "ciniki_poma_order_dates.order_date, "
        . "ciniki_poma_order_dates.display_name, "
        . "ciniki_poma_order_dates.status, "
        . "ciniki_poma_order_dates.flags "
        . "FROM ciniki_poma_order_dates "
        . "WHERE ciniki_poma_order_dates.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND ciniki_poma_order_dates.status > 5 "
        . "GROUP BY ciniki_poma_order_dates.id "
        . "ORDER BY ciniki_poma_order_dates.order_date DESC "
        . "LIMIT 75"
        . "";
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'dates', 'fname'=>'id', 'fields'=>array('id', 'order_date', 'display_name', 'status', 'flags')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['dates']) || count($rc['dates']) < 1 ) {
        return array('stat'=>'ok', 'dates'=>array(), 'open_orders'=>array(), 'closed_orders'=>array(), 'order'=>array());
    }
    $rsp['dates'] = $rc['dates'];
    foreach($rsp['dates'] as $did => $date) {
        $rsp['dates'][$did]['name_status'] = $date['display_name'] . ' - ' . $maps['orderdate']['status'][$date['status']];
    }
    
    //
    // Check if a new order should be created
    //
    if( isset($args['order']) && $args['order'] == 'new' 
        && isset($args['customer_id']) && $args['customer_id'] > 0 
        && isset($args['date_id']) && $args['date_id'] > 0 
        ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'newOrderForDate');
        $rc = ciniki_poma_newOrderForDate($ciniki, $args['tnid'], array(
            'customer_id'=>$args['customer_id'],
            'date_id'=>$args['date_id'],
            'checkdate'=>'no',
            'pickup_time'=>'last',
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $args['order_id'] = $rc['order']['id'];
    }

    //
    // Find the customers order if no order specified
    //
    elseif( (!isset($args['order_id']) || $args['order_id'] == 0) && isset($args['customer_id']) && $args['customer_id'] > 0 ) {
        $strsql = "SELECT id "
            . "FROM ciniki_poma_orders "
            . "WHERE date_id = '" . ciniki_core_dbQuote($ciniki, $args['date_id']) . "' "
            . "AND customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "ORDER BY status "
            . "LIMIT 1 "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['order']['id']) ) {
            $args['order_id'] = $rc['order']['id'];
        }
    }

    if( isset($args['new_object']) && $args['new_object'] != '' 
        && isset($args['new_object_id']) && $args['new_object_id'] != ''
        && isset($args['order_id']) && $args['order_id'] != ''
        ) {
        if( !isset($args['new_unit_quantity']) || $args['new_unit_quantity'] == '' || $args['new_unit_quantity'] == '0' ) {
            $args['new_unit_quantity'] = (isset($args['new_quantity']) ? $args['new_quantity'] : 1);
        }
        if( !isset($args['new_weight_quantity']) || $args['new_weight_quantity'] == '' || $args['new_weight_quantity'] == '0' ) {
            $args['new_weight_quantity'] = 0;
        }

        //
        // Get the details for the item
        //
        list($pkg, $mod, $obj) = explode('.', $args['new_object']);
        $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'itemLookup');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.71', 'msg'=>'Unable to add favourite.'));
        }
        $fn = $rc['function_call'];
        $rc = $fn($ciniki, $args['tnid'], array('object'=>$args['new_object'], 'object_id'=>$args['new_object_id'], 'date_id'=>$args['date_id']));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['item']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.72', 'msg'=>'Unable to add item.'));
        }
        $item = $rc['item'];

        //
        // Get the next line number
        //
        $strsql = "SELECT MAX(line_number) AS max_line_number "
            . "FROM ciniki_poma_order_items "
            . "WHERE ciniki_poma_order_items.order_id = '" . ciniki_core_dbQuote($ciniki, $args['order_id']) . "' "
            . "AND ciniki_poma_order_items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['order']) ) {
            $item['line_number'] = $rc['order']['max_line_number'] + 1;
        } else {
            $item['line_number'] = 1;
        }

        //
        // Start transaction
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
        $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.poma');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }

        //
        // Add the order item to the database
        //
        $item['order_id'] = $args['order_id'];
        if( $item['itype'] == 10 ) {
            $item['weight_quantity'] = (isset($args['new_quantity']) ? $args['new_quantity'] : $args['new_weight_quantity']);
        } elseif( $item['itype'] == 20 ) {
            $item['unit_quantity'] = $args['new_unit_quantity'];
            $item['weight_quantity'] = $args['new_weight_quantity'];
        } else {
            $item['unit_quantity'] = $args['new_unit_quantity'];
        }
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
        $rc = ciniki_core_objectAdd($ciniki, $args['tnid'], 'ciniki.poma.orderitem', $item, 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
            return $rc;
        }
        $item_id = $rc['id'];

        //
        // Check if there are subitems
        //
        if( isset($item['subitems']) ) {
            foreach($item['subitems'] as $subitem) {
                $subitem['order_id'] = $args['order_id'];
                $subitem['parent_id'] = $item_id;
                $rc = ciniki_core_objectAdd($ciniki, $args['tnid'], 'ciniki.poma.orderitem', $subitem, 0x04);
                if( $rc['stat'] != 'ok' ) {
                    ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                    return $rc;
                }
            }
        }

        //
        // Update the order
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderUpdateStatusBalance');
        $rc = ciniki_poma_orderUpdateStatusBalance($ciniki, $args['tnid'], $args['order_id']);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }

        //
        // Commit the transaction
        //
        $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.poma');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
    }

    if( isset($args['item_id']) && $args['item_id'] != '' && $args['item_id'] > 0 
        && ((isset($args['new_unit_quantity']) && $args['new_unit_quantity'] != '') || (isset($args['new_weight_quantity']) && $args['new_weight_quantity'] != ''))
        ) {
        //
        // Get the order item
        //
        $strsql = "SELECT id, uuid, order_id, itype, weight_quantity, unit_quantity "
            . "FROM ciniki_poma_order_items "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
            . "AND order_id = '" . ciniki_core_dbQuote($ciniki, $args['order_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['item']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.128', 'msg'=>'Unable to find item.'));
        }
        $item = $rc['item'];

        //
        // Get any subitems
        //
        $strsql = "SELECT id, uuid, order_id, itype, weight_quantity, unit_quantity "
            . "FROM ciniki_poma_order_items "
            . "WHERE order_id = '" . ciniki_core_dbQuote($ciniki, $args['order_id']) . "' "
            . "AND parent_id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'item');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $subitems = array();
        if( isset($rc['rows']) ) {
            $subitems = $rc['rows'];
        }


        $update_args = array();
        $delete = 'no';
        if( $item['itype'] == 10 ) {
            if( isset($args['new_weight_quantity']) && $item['weight_quantity'] != $args['new_weight_quantity'] ) {
                if( $args['new_weight_quantity'] > 0 ) {
                    $update_args['weight_quantity'] = $args['new_weight_quantity'];
                } else {
                    $delete = 'yes';
                }
            }
        } else {
            if( $item['itype'] == 20 ) {
                if( isset($args['new_weight_quantity']) && $item['weight_quantity'] != $args['new_weight_quantity'] ) {
                    if( $args['new_weight_quantity'] > 0 ) {
                        $update_args['weight_quantity'] = $args['new_weight_quantity'];
                    }
                }
            }
            if( isset($args['new_unit_quantity']) && $item['unit_quantity'] != $args['new_unit_quantity'] ) {
                if( $args['new_unit_quantity'] > 0 ) {
                    $update_args['unit_quantity'] = $args['new_unit_quantity'];
                } else {
                    $delete = 'yes';
                }
            }
        }

        //
        // Start transaction
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
        $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.poma');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }

        //
        // Update the Order Item in the database
        //
        if( $delete == 'yes' ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
            //
            // Remove the subitems if any
            //
            if( isset($subitems) && count($subitems) > 0 ) {
                foreach($subitems as $subitem) {
                    $rc = ciniki_core_objectDelete($ciniki, $args['tnid'], 'ciniki.poma.orderitem', $subitem['id'], $subitem['uuid'], 0x04);
                    if( $rc['stat'] != 'ok' ) {
                        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                        return $rc;
                    }
                }
            }
            //
            // Delete the item
            //
            $rc = ciniki_core_objectDelete($ciniki, $args['tnid'], 'ciniki.poma.orderitem', $item['id'], $item['uuid'], 0x04);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                return $rc;
            }
        } elseif( count($update_args) > 0 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.poma.orderitem', $args['item_id'], $update_args, 0x04);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.poma');
                return $rc;
            }
        }

        if( $delete == 'yes' || count($update_args) > 0 ) {
            //
            // Update the order
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderUpdateStatusBalance');
            $rc = ciniki_poma_orderUpdateStatusBalance($ciniki, $args['tnid'], $item['order_id']);
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
        }

        //
        // Commit the transaction
        //
        $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.poma');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
    }

    if( isset($args['action']) && $args['action'] == 'invoiceorder' && isset($args['order_id']) && $args['order_id'] > 0 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'invoiceOrder');
        $rc = ciniki_poma_invoiceOrder($ciniki, $args['tnid'], $args['order_id']);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
    }
    
    if( isset($args['action']) && $args['action'] == 'newpickuptime' && isset($args['pickup_time']) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderLoad');
        $rc = ciniki_poma_orderLoad($ciniki, $args['tnid'], $args['order_id']);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $order = $rc['order'];
        //
        // load the order date for start end of pickup time
        //
        $strsql = "SELECT id, order_date, pickupstart_dt, pickupend_dt "
            . "FROM ciniki_poma_order_dates "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['date_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'date');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.218', 'msg'=>'Unable to load order date', 'err'=>$rc['err']));
        }
        if( !isset($rc['date']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.219', 'msg'=>'Unable to find requested order date'));
        }
        $date = $rc['date'];

        if( $args['pickup_time'] != '' ) {
            $pickup_dt = new DateTime($date['order_date'] . ' ' . $args['pickup_time'], new DateTimezone($intl_timezone));
            $start_dt = new DateTime($date['pickupstart_dt'], new DateTimezone('UTC'));
            $start_dt->setTimezone(new DateTimezone($intl_timezone));
            $end_dt = new DateTime($date['pickupend_dt'], new DateTimezone('UTC'));
            $end_dt->setTimezone(new DateTimezone($intl_timezone));
            
            //
            // Check if no am/pm specified, then make sure within pickup window
            //
            if( !preg_match("/(am|pm)/i", $args['pickup_time']) ) {
                if( $pickup_dt < $start_dt ) { 
                    $pickup_dt->add(new DateInterval('PT12H'));
                } elseif( $pickup_dt > $end_dt ) {
                    $pickup_dt->sub(new DateInterval('PT12H'));
                }
            }
            $args['pickup_time'] = $pickup_dt->format("g:i a");
        }

        //
        // Update the pickup time on the order
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
        $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.poma.order', $args['order_id'], array(
            'pickup_time' => $args['pickup_time'],
            ), 0x04);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.216', 'msg'=>'Unable to update the order', 'err'=>$rc['err']));
        }
    }
    
    
    if( isset($args['action']) && $args['action'] == 'moveorder' && isset($args['order_id']) && $args['order_id'] > 0 ) {
        if( !isset($args['newdate_id']) || $args['newdate_id'] <= 0 ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.191', 'msg'=>'No date specified to move order to.'));
        }
        $moved = 'no';
        foreach($rsp['dates'] as $orderdate) {
            if( $orderdate['id'] == $args['newdate_id'] ) {
                $strsql = "SELECT customer_id "
                    . "FROM ciniki_poma_orders "
                    . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['order_id']) . "' "
                    . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                    . "";
                $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.241', 'msg'=>'Unable to load order', 'err'=>$rc['err']));
                }
                if( !isset($rc['order']) ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.242', 'msg'=>'Unable to find requested order'));
                }
                $order = $rc['order'];
                
                //
                // Check if there is already an order for the new date for this customer
                //
                $strsql = "SELECT COUNT(*) AS num "
                    . "FROM ciniki_poma_orders "
                    . "WHERE date_id = '" . ciniki_core_dbQuote($ciniki, $args['newdate_id']) . "' "
                    . "AND customer_id = '" . ciniki_core_dbQuote($ciniki, $order['customer_id']) . "' "
                    . "";
                ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbSingleCount');
                $rc = ciniki_core_dbSingleCount($ciniki, $strsql, 'ciniki.poma', 'num');
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.243', 'msg'=>'Unable to load get the number of items', 'err'=>$rc['err']));
                }
                if( isset($rc['num']) && $rc['num'] > 0 ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.242', 'msg'=>'Order alreadys exists for that date, please move items.'));
                }

                // 
                // Move the order
                //
                ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderMoveDate');
                $rc = ciniki_poma_orderMoveDate($ciniki, $args['tnid'], $args['order_id'], $args['newdate_id']);
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.207', 'msg'=>'Unable to move order', 'err'=>$rc['err']));
                }
                $moved = 'yes';
                $args['order_id'] = 0;
                $args['customer_id'] = 0;
                break;
            }
        }
        if( $moved == 'no' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.192', 'msg'=>'Invalid date specified to move order to.'));
        }
        
    } elseif( isset($args['action']) && $args['action'] == 'closeorder' && isset($args['order_id']) && $args['order_id'] > 0 ) {
        //
        // Check the current status
        //
        $strsql = "SELECT id, status "
            . "FROM ciniki_poma_orders "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['order_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'order');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['order']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.106', 'msg'=>'Unable to find order.'));
        }
        $order = $rc['order'];

        //
        // Start transaction
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
        $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.poma');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }

        //
        // Update the Order status in the database
        //
        if( $order['status'] < 70 ) {
            //
            // If the current status is < 50, the items have not been removed from inventory
            //
            if( $order['status'] < 50 ) {
                ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderRemoveFromInventory');
                $rc = ciniki_poma_orderRemoveFromInventory($ciniki, $args['tnid'], $args['order_id']);
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.204', 'msg'=>'Unable to remove items from inventory', 'err'=>$rc['err']));
                }
            }

            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.poma.order', $args['order_id'], array('status'=>70), 0x04);
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
        }

        //
        // Commit the transaction
        //
        $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.poma');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
    }

    if( isset($args['action']) && $args['action'] == 'recalc' && isset($args['customer_id']) && $args['customer_id'] > 0 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'accountUpdate');
        $rc = ciniki_poma_accountUpdate($ciniki, $args['tnid'], array('customer_id'=>$args['customer_id']));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
    }

    //
    // Get the order
    //
    if( isset($args['order_id']) && $args['order_id'] > 0 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderLoad');
        $rc = ciniki_poma_orderLoad($ciniki, $args['tnid'], $args['order_id']);
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['order']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.194', 'msg'=>'Unable to find order'));
        }
        $rsp['order'] = $rc['order'];
        $rsp['order']['default_payment_amount'] = $rc['order']['balance_amount'];
//        $rsp['checkout_orderitems'] = $rc['order']['items'];
//        $rsp['checkout_tallies'] = $rc['order']['tallies'];
//        $rsp['checkout_orderpayments'] = $rc['order']['payments'];

        //
        // Build the nplists
        //
        foreach($rsp['order']['items'] as $item) {
            $rsp['nplists']['orderitems'][] = $item['id'];
        }
        //
        // Check if there are any messages for this order
        //
        if( isset($ciniki['tenant']['modules']['ciniki.mail']) ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'mail', 'hooks', 'objectMessages');
            $rc = ciniki_mail_hooks_objectMessages($ciniki, $args['tnid'], array('object'=>'ciniki.poma.order', 'object_id'=>$args['order_id']));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            if( isset($rc['messages']) ) {
                $rsp['order']['messages'] = $rc['messages'];
            } else {
                $rsp['order']['messages'] = array();
            }
        } 

        //
        // Get the dates available to move the order to
        //
        $dt = new DateTime('now', new DateTimezone($intl_timezone));
        $strsql = "SELECT ciniki_poma_order_dates.id, "
            . "ciniki_poma_order_dates.order_date, "
            . "ciniki_poma_order_dates.display_name, "
            . "ciniki_poma_order_dates.status, "
            . "ciniki_poma_order_dates.flags "
            . "FROM ciniki_poma_order_dates "
            . "WHERE ciniki_poma_order_dates.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND order_date >= '" . ciniki_core_dbQuote($ciniki, $dt->format('Y-m-d')) . "' "
            . "AND id <> '" . ciniki_core_dbQuote($ciniki, $rsp['order']['date_id']) . "' "
            . "ORDER BY ciniki_poma_order_dates.order_date ASC "
            . "LIMIT 15"
            . "";
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'dates', 'fname'=>'id', 'fields'=>array('id', 'order_date', 'display_name', 'status', 'flags')),
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['dates']) ) {
            $rsp['move_orderdates'] = $rc['dates'];
            foreach($rsp['move_orderdates'] as $did => $date) {
                $rsp['move_orderdates'][$did]['name_status'] = $date['display_name'] . ' - ' . $maps['orderdate']['status'][$date['status']];
            }
        }
    }

    if( isset($rsp['order']['customer_id']) && $rsp['order']['customer_id'] > 0 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'hooks', 'customerDetails');
        $rc = ciniki_customers_hooks_customerDetails($ciniki, $args['tnid'], array('customer_id'=>$rsp['order']['customer_id']));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['details']) ) {
            $rsp['customer_details'] = $rc['details'];
        }
        if( isset($rc['customer']['member_status_text']) && $rc['customer']['member_status_text'] != '' ) {
            if( isset($rc['customer']['member_lastpaid']) && $rc['customer']['member_lastpaid'] != '' ) {
                $rsp['customer_details'][] = array('detail'=>array(
                    'label'=>'Membership',
                    'value'=>$rc['customer']['member_status_text'] . ' <span class="subdue">[' . $rc['customer']['member_lastpaid'] . ']</span>',
                    ));
            } else {
                $rsp['customer_details'][] = array('detail'=>array(
                    'label'=>'Membership',
                    'value'=>$rc['customer']['member_status_text'],
                    ));
            }
        }

        //
        // Get the current account balance
        //
        $strsql = "SELECT ciniki_poma_customer_ledgers.id, "
            . "ciniki_poma_customer_ledgers.description, "
            . "ciniki_poma_customer_ledgers.transaction_date, "
            . "ciniki_poma_customer_ledgers.transaction_type, "
            . "ciniki_poma_customer_ledgers.customer_amount, "
            . "ciniki_poma_customer_ledgers.balance "
            . "FROM ciniki_poma_customer_ledgers "
            . "WHERE customer_id = '" . ciniki_core_dbQuote($ciniki, $rsp['order']['customer_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "ORDER BY transaction_date DESC "
            . "LIMIT 25 "
            . "";
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'entries', 'fname'=>'id', 
                'fields'=>array('id', 'description', 'transaction_date', 'transaction_type', 'customer_amount', 'balance'),
                'utctotz'=>array('transaction_date'=>array('timezone'=>$intl_timezone, 'format'=>$date_format)),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $rsp['checkout_recentledger'] = array();
        if( isset($rc['entries']) ) {
            foreach($rc['entries'] as $entry) {
                if( !isset($balance) ) {
                    $balance = $entry['balance'];
                }
                if( $entry['transaction_type'] == 10 ) {
                    $entry['amount'] = '$' . number_format($entry['customer_amount'], 2);
                } elseif( $entry['transaction_type'] == 30 ) {
                    $entry['amount'] = '-$' . number_format($entry['customer_amount'], 2);
                } elseif( $entry['transaction_type'] == 60 ) {
                    $entry['amount'] = '$' . number_format($entry['customer_amount'], 2);
                }
                $entry['balance_text'] = ($entry['balance'] < 0 ? '-':'') . '$' . number_format(abs($entry['balance']), 2);
                array_unshift($rsp['checkout_recentledger'], $entry);
            }
            if( isset($balance) ) {
                $rsp['customer_details'][] = array('detail'=>array(
                    'label'=>'Account',
                    'value'=>($balance < 0 ? '-' : '') . '$' . number_format(abs($balance), 2),
                ));
                if( $balance < 0 && $balance != $rsp['order']['balance_amount'] ) {
//                    $rsp['order']['payments'][] = array('label'=>'Account Balance', 
//                        'value'=>($balance < 0 ? '-' : '') . '$' . number_format(abs($balance), 2),
//                        );
                    $rsp['order']['default_payment_amount'] = abs($balance);
                }
                $rsp['order']['account_balance'] = $balance;
                if( $balance < 0 ) {
                    $rsp['checkout_account'] = array(
                        array('label'=>'Account Balance Owing', 'status'=>'red', 'value'=>'$' . number_format(abs($balance), 2)),
                        );
                } else {
                }
            }
        }
        $strsql = "SELECT id, "
            . "order_number, "
            . "order_date, "
            . "status, "
            . "status AS status_text, "
            . "payment_status, "
            . "payment_status AS payment_status_text, "
            . "flags, "
            . "total_amount "
            . "FROM ciniki_poma_orders "
            . "WHERE customer_id = '" . ciniki_core_dbQuote($ciniki, $rsp['order']['customer_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND order_date <= UTC_TIMESTAMP() "
            . "ORDER BY order_date DESC "
            . "LIMIT 25 "
            . "";
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'orders', 'fname'=>'id', 
                'fields'=>array('id', 'order_number', 'order_date', 'status', 'status_text', 'payment_status', 'payment_status_text', 'flags', 'total_amount'),
                'maps'=>array('status_text'=>$maps['order']['status'],
                    'payment_status_text'=>$maps['order']['payment_status'],
                    ),
                'utctotz'=>array('order_date'=>array('timezone'=>'UTC', 'format'=>$date_format)),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $rsp['checkout_orderhistory'] = array();
        if( isset($rc['orders']) ) {
            foreach($rc['orders'] as $order) {
                $order['total_amount'] = '$' . number_format($order['total_amount'], 2);
                $rsp['checkout_orderhistory'][] = $order;
            }
        }
    }

    //
    // Get the list of open & closed orders
    //
    $strsql = "SELECT orders.id, "
        . "IF(orders.status < 70, 'open', 'closed') AS state, "
        . "orders.status, "
        . "orders.payment_status, "
        . "IF(orders.pickup_time='', '??', orders.pickup_time) AS pickup_time, "
        . "orders.billing_name, "
        . "COUNT(notes.id) AS num_notes "
        . "FROM ciniki_poma_orders AS orders "
        . "LEFT JOIN ciniki_poma_notes AS notes ON ("
            . "orders.customer_id = notes.customer_id "
            . "AND notes.ntype = 30 "
            . "AND notes.status = 10 "
            . "AND notes.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "WHERE orders.date_id = '" . ciniki_core_dbQuote($ciniki, $args['date_id']) . "' "
        . "AND orders.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "GROUP BY state, orders.id ";
    if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.poma', 0x08) ) {
        $strsql .= "ORDER BY state, pickup_time, orders.billing_name ";
    } else {
        $strsql .= "ORDER BY state, orders.billing_name ";
    }
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'states', 'fname'=>'state', 'fields'=>array('state')),
        array('container'=>'orders', 'fname'=>'id', 
            'fields'=>array('id', 'state', 'status', 'payment_status', 'billing_name', 'num_notes', 'pickup_time'),
            'maps'=>array('payment_status'=>$maps['order']['payment_status']),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['states']) ) {
        foreach($rc['states'] as $state) {
            if( $state['state'] == 'open' ) {
                $rsp['open_orders'] = $state['orders'];
            } else {
                $rsp['closed_orders'] = $state['orders'];
            }
        }
    }

    return $rsp;
}
?>
