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
function ciniki_poma_formatAmount($ciniki, $amount) {

    if( $amount < 0 ) {
        $text = '(-$' . number_format(abs($amount), 2) . ')';
    } else {
        $text = '$' . number_format($amount, 2);
    }

    return $text;
}
?>
