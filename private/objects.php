<?php
//
// Description
// -----------
// This function returns the list of objects for the module.
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_poma_objects(&$ciniki) {
    //
    // Build the objects
    //
    $objects = array();
    $objects['order'] = array(
        'name'=>'Order',
        'sync'=>'yes',
        'o_name'=>'order',
        'o_container'=>'orders',
        'table'=>'ciniki_poma_orders',
        'fields'=>array(
            ),
        'history_table'=>'ciniki_poma_history',
        );
    $objects['orderitem'] = array(
        'name'=>'Order Item',
        'sync'=>'yes',
        'o_name'=>'item',
        'o_container'=>'items',
        'table'=>'ciniki_poma_order_item',
        'fields'=>array(
            ),
        'history_table'=>'ciniki_poma_history',
        );
    $objects['customeritem'] = array(
        'name'=>'Customer Item',
        'sync'=>'yes',
        'o_name'=>'item',
        'o_container'=>'items',
        'table'=>'ciniki_poma_customer_items',
        'fields'=>array(
            'parent_id'=>array('name'=>'Parent', 'ref'=>'ciniki.poma.customeritem', 'default'=>'0'),
            'customer_id'=>array('name'=>'Customer'),
            'itype'=>array('name'=>'Type'),
            'object'=>array('name'=>'Item'),
            'object_id'=>array('name'=>'Item ID'),
            'repeat_days'=>array('name'=>'', 'default'=>'7'),
            'last_order_date'=>array('name'=>'', 'default'=>''),
            'next_order_date'=>array('name'=>'', 'default'=>''),
            'quantity'=>array('name'=>''),
            'single_units_text'=>array('name'=>'', 'default'=>''),
            'plural_units_text'=>array('name'=>'', 'default'=>''),
            ),
        'history_table'=>'ciniki_poma_history',
        );
    $objects['orderdate'] = array(
        'name'=>'Order Date',
        'sync'=>'yes',
        'o_name'=>'date',
        'o_container'=>'dates',
        'table'=>'ciniki_poma_order_dates',
        'fields'=>array(
            'order_date'=>array('name'=>'Date'),
            'display_name'=>array('name'=>'Name', 'default'=>''),
            'status'=>array('name'=>'Status', 'default'=>'10'),
            'flags'=>array('name'=>'Options', 'default'=>'0'),
            'autolock_dt'=>array('name'=>'Auto Lock Date', 'default'=>''),
            'notices'=>array('name'=>'Notices', 'default'=>''),
            ),
        'history_table'=>'ciniki_poma_history',
        );
    return array('stat'=>'ok', 'objects'=>$objects);
}
?>
