<?php
//
// Description
// -----------
// This function will process an order item for display on the website
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure.
// business_id:     The ID of the business to get poma request for.
// item:            The item to be formatted.:w
//
//
// Returns
// -------
//
function ciniki_poma_web_orderItemFormat($ciniki, $settings, $business_id, $item) {
    $item['quantity_single'] = '';
    $item['quantity_plural'] = '';
    if( $item['itype'] == 10 ) {
        if( $item['weight_units'] == 20 ) {
            $item['quantity_single'] = 'lb';
            $item['quantity_plural'] = 'lbs';
        } elseif( $item['weight_units'] == 25 ) {
            $item['quantity_single'] = 'oz';
            $item['quantity_plural'] = 'ozs';
        } elseif( $item['weight_units'] == 60 ) {
            $item['quantity_single'] = 'kg';
            $item['quantity_plural'] = 'kgs';
        } elseif( $item['weight_units'] == 65 ) {
            $item['quantity_single'] = 'g';
            $item['quantity_plural'] = 'gs';
        }
    }
    if( $item['itype'] == 20 ) {
        if( $item['weight_units'] == 20 ) {
            $item['weight_unit_text'] = 'lb';
        } elseif( $item['weight_units'] == 25 ) {
            $item['weight_unit_text'] = 'oz';
        } elseif( $item['weight_units'] == 60 ) {
            $item['weight_unit_text'] = 'kg';
        } elseif( $item['weight_units'] == 65 ) {
            $item['weight_unit_text'] = 'g';
        }
    }
    if( $item['itype'] == 10 ) {
        $item['quantity'] = (float)$item['weight_quantity'];
        $item['price_text'] = "$" . number_format($item['unit_amount'], 2, '.', ',') . '/' . $item['quantity_single'];
        $item['total_text'] = "$" . number_format($item['total_amount'], 2, '.', ',');
    } elseif( $item['itype'] == 20 ) {
        $item['quantity'] = (float)$item['unit_quantity'];
        if( $item['weight_quantity'] > 0 ) {
            $item['price_text'] = (float)$item['weight_quantity'] . " @ "
                . "$" . number_format($item['unit_amount'], 2, '.', ',') . '/' . $item['weight_unit_text'];
            $item['total_text'] = "$" . number_format($item['total_amount'], 2, '.', ',');
        } else {
            $item['price_text'] = "$" . number_format($item['unit_amount'], 2, '.', ',') . '/' . $item['weight_unit_text'];
            $item['total_text'] = "TBD";
        }
    } else {
        $item['quantity'] = (float)$item['unit_quantity'];
        $item['price_text'] = "$" . number_format($item['unit_amount'], 2, '.', ',') 
            . ($item['unit_suffix'] != '' ? ' ' . $item['unit_suffix'] : '');
        $item['total_text'] = "$" . number_format($item['total_amount'], 2, '.', ',');
    }

    return array('stat'=>'ok', 'item'=>$item);
}
?>
