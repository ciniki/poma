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
            'order_number'=>array('name'=>'Order Number'),
            'customer_id'=>array('name'=>'Customer'),
            'date_id'=>array('name'=>'Date', 'default'=>'0'),
            'order_date'=>array('name'=>'Order Date', 'default'=>''),
            'status'=>array('name'=>'Status', 'default'=>'10'),
            'payment_status'=>array('name'=>'Payment Status', 'default'=>'0'),
            'flags'=>array('name'=>'Options', 'default'=>'0'),
            'billing_name'=>array('name'=>'Billing Name', 'default'=>''),
            'subtotal_amount'=>array('name'=>'Subtotal Amount', 'default'=>'0'),
            'subtotal_discount_amount'=>array('name'=>'Subtotal Discount Amount', 'default'=>'0'),
            'subtotal_discount_percentage'=>array('name'=>'Subtotal Discount Percentage', 'default'=>'0'),
            'discount_amount'=>array('name'=>'Discount Amount', 'default'=>'0'),
            'total_amount'=>array('name'=>'Total Amount', 'default'=>'0'),
            'total_savings'=>array('name'=>'Total Savings', 'default'=>'0'),
            'paid_amount'=>array('name'=>'Paid Amount', 'default'=>'0'),
            'balance_amount'=>array('name'=>'Balance', 'default'=>'0'),
            'customer_notes'=>array('name'=>'Customer Notes', 'default'=>''),
            'order_notes'=>array('name'=>'Order Notes', 'default'=>''),
            'internal_notes'=>array('name'=>'Internal Notes', 'default'=>''),
            ),
        'history_table'=>'ciniki_poma_history',
        );
    $objects['orderitem'] = array(
        'name'=>'Order Item',
        'sync'=>'yes',
        'o_name'=>'item',
        'o_container'=>'items',
        'table'=>'ciniki_poma_order_items',
        'fields'=>array(
            'order_id'=>array('name'=>'Order'),
            'parent_id'=>array('name'=>'Parent', 'default'=>'0'),
            'line_number'=>array('name'=>'Line', 'default'=>'1'),
            'flags'=>array('name'=>'Options', 'default'=>'0'),
            'object'=>array('name'=>'Object', 'default'=>''),
            'object_id'=>array('name'=>'Object ID', 'default'=>'0'),
            'code'=>array('name'=>'Code', 'default'=>''),
            'description'=>array('name'=>'Description'),
            'itype'=>array('name'=>'Type'),
            'weight_units'=>array('name'=>'Weight Units', 'default'=>'0'),
            'weight_quantity'=>array('name'=>'Weight Quantity', 'default'=>'0'),
            'unit_quantity'=>array('name'=>'Unit Quantity', 'default'=>'0'),
            'unit_suffix'=>array('name'=>'Unit Suffix', 'default'=>''),
            'packing_order'=>array('name'=>'Packing Order', 'default'=>'10'),
            'unit_amount'=>array('name'=>'Amount'),
            'unit_discount_amount'=>array('name'=>'Discount Amount', 'default'=>'0'),
            'unit_discount_percentage'=>array('name'=>'Discount Percentage', 'default'=>'0'),
            'subtotal_amount'=>array('name'=>'Subtotal Amount', 'default'=>'0'),
            'discount_amount'=>array('name'=>'Discount Amount', 'default'=>'0'),
            'total_amount'=>array('name'=>'Total Amount', 'default'=>'0'),
            'taxtype_id'=>array('name'=>'Tax Type', 'default'=>'0'),
            'notes'=>array('name'=>'Notes', 'default'=>''),
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
            'description'=>array('name'=>'Description'),
            'repeat_days'=>array('name'=>'Repeat Days', 'default'=>'7'),
            'last_order_date_id'=>array('name'=>'Last Order Date', 'ref'=>'ciniki.poma.orderdate', 'default'=>'0'),
            'next_order_date_id'=>array('name'=>'Next Order Date', 'ref'=>'ciniki.poma.orderdate', 'default'=>'0'),
            'quantity'=>array('name'=>'Quantity'),
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
