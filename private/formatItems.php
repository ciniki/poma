<?php
//
// Description
// -----------
// This function loads the order for a customer.
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure.
// business_id:     The ID of the business to get poma web options for.
//
// args:            The possible arguments for posts
//
//
// Returns
// -------
//
function ciniki_poma_formatItems(&$ciniki, $business_id, $items) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'formatAmount');

    $defaults = array(
        'code'=>'',
        'itype'=>'30',
        'flags'=>0,
//        'weight_quantity'=>0,
        'weight_units'=>0,
//        'unit_quantity'=>0,
        'unit_suffix'=>'',
        'packing_order'=>'10',
        'unit_discount_amount'=>'',
        'unit_discount_percentage'=>'',
        'cdeposit_description'=>'',
        'cdeposit_amount'=>0,
        'taxtype_id'=>0,
        'notes'=>'',
        'subtotal_amount'=>0,
        'discount_amount'=>0,
        'total_amount'=>0,
        );
    foreach($items as $iid => $item) {
        foreach($defaults as $field => $v) {
            if( !isset($item[$field]) ) {
                $items[$iid][$field] = $v;
            }
        }
        $items[$iid]['unit_amount_text'] = ciniki_poma_formatAmount($ciniki, $items[$iid]['unit_amount']);
         
        $items[$iid]['quantity_single'] = '';
        $items[$iid]['quantity_plural'] = '';
        if( $items[$iid]['itype'] == 10 || $items[$iid]['itype'] == 20 ) {
            if( $items[$iid]['weight_units'] == 20 ) {
                $items[$iid]['quantity_single'] = 'lb';
                $items[$iid]['quantity_plural'] = 'lbs';
            } elseif( $items[$iid]['weight_units'] == 25 ) {
                $items[$iid]['quantity_single'] = 'oz';
                $items[$iid]['quantity_plural'] = 'ozs';
            } elseif( $items[$iid]['weight_units'] == 60 ) {
                $items[$iid]['quantity_single'] = 'kg';
                $items[$iid]['quantity_plural'] = 'kgs';
            } elseif( $items[$iid]['weight_units'] == 65 ) {
                $items[$iid]['quantity_single'] = 'g';
                $items[$iid]['quantity_plural'] = 'gs';
            }
        }
        if( $items[$iid]['itype'] == 20 ) {
            if( $items[$iid]['weight_units'] == 20 ) {
                $items[$iid]['weight_unit_text'] = 'lb';
            } elseif( $items[$iid]['weight_units'] == 25 ) {
                $items[$iid]['weight_unit_text'] = 'oz';
            } elseif( $items[$iid]['weight_units'] == 60 ) {
                $items[$iid]['weight_unit_text'] = 'kg';
            } elseif( $items[$iid]['weight_units'] == 65 ) {
                $items[$iid]['weight_unit_text'] = 'g';
            } else {
                $items[$iid]['weight_unit_text'] = '';
            }
        }
        if( $items[$iid]['itype'] == 10 ) {
            if( !isset($items[$iid]['unit_quantity']) ) {
                $items[$iid]['unit_quantity'] = '';
            }
            if( !isset($items[$iid]['weight_quantity']) ) {
                $items[$iid]['weight_quantity'] = '';
            }
            $items[$iid]['quantity'] = (float)$items[$iid]['weight_quantity'];
            // Format the price "2.3lbs @ 1.32/lb"
            $items[$iid]['unit_price_text'] = $items[$iid]['unit_amount_text'] . '/' . $items[$iid]['quantity_single'];
            $items[$iid]['price_text'] = (float)$items[$iid]['weight_quantity'] 
                . ' ' . ($items[$iid]['weight_quantity'] > 1 ? $items[$iid]['quantity_plural'] : $items[$iid]['quantity_single'])
                . ' @ ' . $items[$iid]['unit_amount_text'] . '/' . $items[$iid]['quantity_single'];
            $items[$iid]['total_text'] = ciniki_poma_formatAmount($ciniki, $items[$iid]['total_amount']);
        } elseif( $items[$iid]['itype'] == 20 ) {
            if( !isset($items[$iid]['unit_quantity']) ) {
                $items[$iid]['unit_quantity'] = 1;
            }
            if( !isset($items[$iid]['weight_quantity']) ) {
                $items[$iid]['weight_quantity'] = '';
            }
            $items[$iid]['quantity'] = (float)$items[$iid]['unit_quantity'];
            $items[$iid]['unit_price_text'] = $items[$iid]['unit_amount_text'] . '/' . $items[$iid]['weight_unit_text'];
            if( $items[$iid]['weight_quantity'] > 0 ) {
                $items[$iid]['price_text'] = (float)$items[$iid]['unit_quantity'] . ' - '
                    . (float)$items[$iid]['weight_quantity'] 
                    . ($items[$iid]['weight_quantity'] > 1 ? $items[$iid]['quantity_plural'] : $items[$iid]['quantity_single'])
                    . ' @ ' . $items[$iid]['unit_amount_text'] . '/' . $items[$iid]['weight_unit_text'];
                $items[$iid]['total_text'] = ciniki_poma_formatAmount($ciniki, $items[$iid]['total_amount']);
            } else {
                $items[$iid]['price_text'] = (float)$items[$iid]['unit_quantity'] . ' - '
                    . $items[$iid]['unit_amount_text'] . '/' . $items[$iid]['weight_unit_text'];
                $items[$iid]['total_text'] = "TBD";
            }
        } else {
            if( !isset($items[$iid]['unit_quantity']) ) {
                $items[$iid]['unit_quantity'] = 1;
            }
            if( !isset($items[$iid]['weight_quantity']) ) {
                $items[$iid]['weight_quantity'] = '';
            }
            $items[$iid]['quantity'] = (float)$items[$iid]['unit_quantity'];
            $items[$iid]['unit_price_text'] = $items[$iid]['unit_amount_text'] . ($items[$iid]['unit_suffix'] != '' ? ' ' . $items[$iid]['unit_suffix'] : '');
            $items[$iid]['price_text'] = (float)$items[$iid]['unit_quantity'] . " @ " . $items[$iid]['unit_amount_text']
                . ($items[$iid]['unit_suffix'] != '' ? ' ' . $items[$iid]['unit_suffix'] : '');
            $items[$iid]['total_text'] = ciniki_poma_formatAmount($ciniki, $items[$iid]['total_amount']);
        }
        //
        // Setup discount text
        //
        $items[$iid]['discount_text'] = '';
        if( $items[$iid]['discount_amount'] > 0 ) {
            if( $items[$iid]['unit_discount_amount'] > 0 ) {
                if( $items[$iid]['quantity'] != 1 ) {
                    $items[$iid]['discount_text'] .= '-$' . number_format($items[$iid]['unit_discount_amount'], 2) . 'x' . $items[$iid]['quantity'];
                } else {
                    if( $items[$iid]['unit_discount_percentage'] > 0 ) {
                        $items[$iid]['discount_text'] .= '-$' . number_format($items[$iid]['unit_discount_amount'], 2);
                    }
                }
            }
            if( $items[$iid]['unit_discount_percentage'] > 0 ) {
                $items[$iid]['discount_text'] .= ($items[$iid]['discount_text'] != '' ? ', ' : '')
                    . (float)$items[$iid]['unit_discount_percentage'] . '%';
            }
            $items[$iid]['discount_text'] .= ' (-$' . number_format($items[$iid]['discount_amount'], 2) . ')';
        }
        $items[$iid]['deposit_text'] = '';
        if( ($items[$iid]['flags']&0x08) == 0x08 && $items[$iid]['cdeposit_amount'] > 0 ) {
            $items[$iid]['deposit_text'] = $items[$iid]['cdeposit_description'];
            $items[$iid]['deposit_text'] .= ($items[$iid]['deposit_text'] != '' ? ': ' : '')
                . '$' . number_format(bcmul($items[$iid]['quantity'], $items[$iid]['cdeposit_amount'], 2), 2);
        }
    }

    return array('stat'=>'ok', 'items'=>$items);
}
?>
