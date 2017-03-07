<?php
//
// Description
// ===========
// This method will produce a PDF of the invoice.
//
// Arguments
// ---------
// 
// Returns
// -------
// <rsp stat='ok' id='34' />
//
function ciniki_poma_templates_invoice(&$ciniki, $business_id, $order_id) {

    //
    // Load business details
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'hooks', 'businessDetails');
    $rc = ciniki_businesses_hooks_businessDetails($ciniki, $business_id, array());
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['details']) && is_array($rc['details']) ) {    
        $business_details = $rc['details'];
    } else {
        $business_details = array();
    }

    //
    // Load the invoice settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQueryDash');
    $rc = ciniki_core_dbDetailsQueryDash($ciniki, 'ciniki_poma_settings', 'business_id', $business_id, 'ciniki.poma', 'settings', 'invoice');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['settings']) ) {
        $poma_settings = $rc['settings'];
    } else {
        $poma_settings = array();
    }
    
    //
    // Get the invoice record
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'orderLoad');
    $rc = ciniki_poma_orderLoad($ciniki, $business_id, $order_id);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $order = $rc['order'];
    
    //
    // Load TCPDF library
    //
    require_once($ciniki['config']['ciniki.core']['lib_dir'] . '/tcpdf/tcpdf.php');

    class MYPDF extends TCPDF {
        //Page header
        public $header_image = null;
        public $header_name = '';
        public $header_addr = array();
        public $header_details = array();
        public $header_height = 0;      // The height of the image and address
        public $business_details = array();
        public $poma_settings = array();

        public function Header() {
            //
            // Check if there is an image to be output in the header.   The image
            // will be displayed in a narrow box if the contact information is to
            // be displayed as well.  Otherwise, image is scaled to be 100% page width
            // but only to a maximum height of the header_height (set far below).
            //
            $img_width = 0;
            if( $this->header_image != null ) {
                $height = $this->header_image->getImageHeight();
                $width = $this->header_image->getImageWidth();
                $image_ratio = $width/$height;
                if( count($this->header_addr) == 0 && $this->header_name == '' ) {
                    $img_width = 180;
                } else {
                    $img_width = 120;
                }
                $available_ratio = $img_width/$this->header_height;
                // Check if the ratio of the image will make it too large for the height,
                // and scaled based on either height or width.
                if( $available_ratio < $image_ratio ) {
                    $this->Image('@'.$this->header_image->getImageBlob(), 15, 12, 
                        $img_width, 0, 'JPEG', '', 'L', 2, '150');
                } else {
                    $this->Image('@'.$this->header_image->getImageBlob(), 15, 12, 
                        0, $this->header_height-5, 'JPEG', '', 'L', 2, '150');
                }
            }

            //
            // Add the contact information
            //
            if( !isset($this->poma_settings['invoice-header-contact-position']) 
                || $this->poma_settings['invoice-header-contact-position'] != 'off' ) {
                if( isset($this->poma_settings['invoice-header-contact-position'])
                    && $this->poma_settings['invoice-header-contact-position'] == 'left' ) {
                    $align = 'L';
                } elseif( isset($this->poma_settings['invoice-header-contact-position'])
                    && $this->poma_settings['invoice-header-contact-position'] == 'right' ) {
                    $align = 'R';
                } else {
                    $align = 'C';
                }
                $this->Ln(8);
                if( $this->header_name != '' ) {
                    $this->SetFont('times', 'B', 20);
                    if( $img_width > 0 ) {
                        $this->Cell($img_width, 10, '', 0);
                    }
                    $this->Cell(180-$img_width, 10, $this->header_name, 
                        0, false, $align, 0, '', 0, false, 'M', 'M');
                    $this->Ln(5);
                }
                $this->SetFont('times', '', 10);
                if( count($this->header_addr) > 0 ) {
                    $address_lines = count($this->header_addr);
                    if( $img_width > 0 ) {
                        $this->Cell($img_width, ($address_lines*5), '', 0);
                    }
                    $this->MultiCell(180-$img_width, $address_lines, implode("\n", $this->header_addr), 
                        0, $align, 0, 0, '', '', true, 0, false, true, 0, 'M', false);
                    $this->Ln();
                }
            }

            //
            // Output the invoice details which should be at the top of each page.
            //
            $this->SetCellPadding(2);
            if( count($this->header_details) <= 6 ) {
                if( $this->header_name == '' && count($this->header_addr) == 0 ) {
                    $this->Ln($this->header_height+6);
                } elseif( $this->header_name == '' && count($this->header_addr) > 0 ) {
                    $used_space = 4 + count($this->header_addr)*5;
                    if( $used_space < 30 ) {
                        $this->Ln(30-$used_space+5);
                    } else {
                        $this->Ln(7);
                    }
                } elseif( $this->header_name != '' && count($this->header_addr) > 0 ) {
                    $used_space = 10 + count($this->header_addr)*5;
                    if( $used_space < 30 ) {
                        $this->Ln(30-$used_space+6);
                    } else {
                        $this->Ln(5);
                    }
                } elseif( $this->header_name != '' && count($this->header_addr) == 0 ) {
                    $this->Ln(25);
                }
                $this->SetFont('times', '', 10);
                $num_elements = count($this->header_details);
                if( $num_elements == 3 ) {
                    $w = array(60,60,60);
                } elseif( $num_elements == 4 ) {
                    $w = array(45,45,45,45);
                } elseif( $num_elements == 5 ) {
                    $w = array(36,36,36,36,36);
                } else {
                    $w = array(30,30,30,30,30,30);
                }
                $lh = 6;
                $this->SetFont('', 'B');
                for($i=0;$i<$num_elements;$i++) {
                    if( $this->header_details[$i]['label'] != '' ) {
                        $this->SetFillColor(224);
                        $this->Cell($w[$i], $lh, $this->header_details[$i]['label'], 1, 0, 'C', 1);
                    } else {
                        $this->SetFillColor(255);
                        $this->Cell($w[$i], $lh, '', 'T', 0, 'C', 1);
                    }
                }
                $this->Ln();
                $this->SetFillColor(255);
                $this->SetFont('');
                for($i=0;$i<$num_elements;$i++) {
                    if( $this->header_details[$i]['label'] != '' ) {
                        $this->Cell($w[$i], $lh, $this->header_details[$i]['value'], 1, 0, 'C', 1);
                    } else {
                        $this->Cell($w[$i], $lh, '', 0, 0, 'C', 1);
                    }
                }
                $this->Ln();
            }
        }

        // Page footer
        public function Footer() {
            // Position at 15 mm from bottom
            $this->SetY(-15);
            // Set font
            $this->SetFont('helvetica', 'I', 8);
            if( isset($this->poma_settings['invoice-footer-message']) 
                && $this->poma_settings['invoice-footer-message'] != '' ) {
                $this->Cell(90, 10, $this->poma_settings['invoice-footer-message'],
                    0, false, 'L', 0, '', 0, false, 'T', 'M');
                $this->Cell(90, 10, 'Page ' . $this->pageNo().'/'.$this->getAliasNbPages(), 
                    0, false, 'R', 0, '', 0, false, 'T', 'M');
            } else {
                // Center the page number if no footer message.
                $this->Cell(0, 10, 'Page ' . $this->pageNo().'/'.$this->getAliasNbPages(), 
                    0, false, 'C', 0, '', 0, false, 'T', 'M');
            }
        }
    }

    //
    // Start a new document
    //
    $pdf = new MYPDF('P', PDF_UNIT, 'LETTER', true, 'UTF-8', false);

    //
    // Figure out the header business name and address information
    //
    $pdf->header_height = 0;
    $pdf->header_name = '';
    if( !isset($poma_settings['invoice-header-contact-position'])
        || $poma_settings['invoice-header-contact-position'] != 'off' ) {
        if( !isset($poma_settings['invoice-header-business-name'])
            || $poma_settings['invoice-header-business-name'] == 'yes' ) {
            $pdf->header_name = $business_details['name'];
            $pdf->header_height = 8;
        }
        if( !isset($poma_settings['invoice-header-business-address'])
            || $poma_settings['invoice-header-business-address'] == 'yes' ) {
            if( isset($business_details['contact.address.street1']) 
                && $business_details['contact.address.street1'] != '' ) {
                $pdf->header_addr[] = $business_details['contact.address.street1'];
            }
            if( isset($business_details['contact.address.street2']) 
                && $business_details['contact.address.street2'] != '' ) {
                $pdf->header_addr[] = $business_details['contact.address.street2'];
            }
            $city = '';
            if( isset($business_details['contact.address.city']) 
                && $business_details['contact.address.city'] != '' ) {
                $city .= $business_details['contact.address.city'];
            }
            if( isset($business_details['contact.address.province']) 
                && $business_details['contact.address.province'] != '' ) {
                $city .= ($city!='')?', ':'';
                $city .= $business_details['contact.address.province'];
            }
            if( isset($business_details['contact.address.postal']) 
                && $business_details['contact.address.postal'] != '' ) {
                $city .= ($city!='')?'  ':'';
                $city .= $business_details['contact.address.postal'];
            }
            if( $city != '' ) {
                $pdf->header_addr[] = $city;
            }
        }
        if( !isset($poma_settings['invoice-header-business-phone'])
            || $poma_settings['invoice-header-business-phone'] == 'yes' ) {
            if( isset($business_details['contact.phone.number']) 
                && $business_details['contact.phone.number'] != '' ) {
                $pdf->header_addr[] = 'phone: ' . $business_details['contact.phone.number'];
            }
            if( isset($business_details['contact.tollfree.number']) 
                && $business_details['contact.tollfree.number'] != '' ) {
                $pdf->header_addr[] = 'phone: ' . $business_details['contact.tollfree.number'];
            }
        }
        if( !isset($poma_settings['invoice-header-business-cell'])
            || $poma_settings['invoice-header-business-cell'] == 'yes' ) {
            if( isset($business_details['contact.cell.number']) 
                && $business_details['contact.cell.number'] != '' ) {
                $pdf->header_addr[] = 'cell: ' . $business_details['contact.cell.number'];
            }
        }
        if( (!isset($poma_settings['invoice-header-business-fax'])
            || $poma_settings['invoice-header-business-fax'] == 'yes')
            && isset($business_details['contact.fax.number']) 
            && $business_details['contact.fax.number'] != '' ) {
            $pdf->header_addr[] = 'fax: ' . $business_details['contact.fax.number'];
        }
        if( (!isset($poma_settings['invoice-header-business-email'])
            || $poma_settings['invoice-header-business-email'] == 'yes')
            && isset($business_details['contact.email.address']) 
            && $business_details['contact.email.address'] != '' ) {
            $pdf->header_addr[] = $business_details['contact.email.address'];
        }
        if( (!isset($poma_settings['invoice-header-business-website'])
            || $poma_settings['invoice-header-business-website'] == 'yes')
            && isset($business_details['contact-website-url']) 
            && $business_details['contact-website-url'] != '' ) {
            $pdf->header_addr[] = $business_details['contact-website-url'];
        }
    }
    $pdf->header_height += (count($pdf->header_addr)*5);

    //
    // Set the minimum header height
    //
    if( $pdf->header_height < 30 ) {
        $pdf->header_height = 30;
    }

    //
    // Load the header image
    //
    if( isset($poma_settings['invoice-header-image']) && $poma_settings['invoice-header-image'] > 0 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'loadImage');
        $rc = ciniki_images_loadImage($ciniki, $business_id, 
            $poma_settings['invoice-header-image'], 'original');
        if( $rc['stat'] == 'ok' ) {
            $pdf->header_image = $rc['image'];
        }
    }

    $pdf->business_details = $business_details;
    $pdf->poma_settings = $poma_settings;

    //
    // Determine the header details
    //
    $pdf->header_details = array(
        array('label'=>'Invoice Number', 'value'=>$order['order_number']),
        array('label'=>'Invoice Date', 'value'=>$order['order_date_text']),
        );
    $pdf->header_details[] = array('label'=>'Status', 'value'=>$order['payment_status_text']);
    $pdf->header_details[] = array('label'=>'Balance', 'value'=>'$' . number_format($order['balance_amount'], 2));

    //
    // Setup the PDF basics
    //
    $pdf->SetCreator('Ciniki');
    $pdf->SetAuthor($business_details['name']);
    $pdf->SetTitle('Invoice #' . $order['order_number']);
    $pdf->SetSubject('');
    $pdf->SetKeywords('');

    // set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, $pdf->header_height+33, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);


    // set font
    $pdf->SetFont('times', 'BI', 10);
    $pdf->SetCellPadding(2);

    // add a page
    $pdf->AddPage();
    $pdf->SetFillColor(255);
    $pdf->SetTextColor(0);
    $pdf->SetDrawColor(51);
    $pdf->SetLineWidth(0.15);

    //
    // Determine the billing address information
    //
    $baddr = array();
    if( isset($order['billing_name']) && $order['billing_name'] != '' ) {
        $baddr[] = $order['billing_name'];
    }
    if( isset($order['billing_address1']) && $order['billing_address1'] != '' ) {
        $baddr[] = $order['billing_address1'];
    }
    if( isset($order['billing_address2']) && $order['billing_address2'] != '' ) {
        $baddr[] = $order['billing_address2'];
    }
    $city = '';
    if( isset($order['billing_city']) && $order['billing_city'] != '' ) {
        $city = $order['billing_city'];
    }
    if( isset($order['billing_province']) && $order['billing_province'] != '' ) {
        $city .= (($city!='')?', ':'') . $order['billing_province'];
    }
    if( isset($order['billing_postal']) && $order['billing_postal'] != '' ) {
        $city .= (($city!='')?',  ':'') . $order['billing_postal'];
    }
    if( $city != '' ) {
        $baddr[] = $city;
    }
    if( isset($order['billing_country']) && $order['billing_country'] != '' ) {
        $baddr[] = $order['billing_country'];
    }

    //
    // Determine the shipping information
    //
    $saddr = array();
/*    if( isset($order['shipping_status']) && $order['shipping_status'] > 0 ) {
        if( isset($order['shipping_name']) && $order['shipping_name'] != '' ) {
            $saddr[] = $order['shipping_name'];
        }
        if( isset($order['shipping_address1']) && $order['shipping_address1'] != '' ) {
            $saddr[] = $order['shipping_address1'];
        }
        if( isset($order['shipping_address2']) && $order['shipping_address2'] != '' ) {
            $saddr[] = $order['shipping_address2'];
        }
        $city = '';
        if( isset($order['shipping_city']) && $order['shipping_city'] != '' ) {
            $city = $order['shipping_city'];
        }
        if( isset($order['shipping_province']) && $order['shipping_province'] != '' ) {
            $city .= (($city!='')?', ':'') . $order['shipping_province'];
        }
        if( isset($order['shipping_postal']) && $order['shipping_postal'] != '' ) {
            $city .= (($city!='')?',  ':'') . $order['shipping_postal'];
        }
        if( $city != '' ) {
            $saddr[] = $city;
        }
        if( isset($order['shipping_country']) && $order['shipping_country'] != '' ) {
            $saddr[] = $order['shipping_country'];
        }
        if( isset($order['shipping_phone']) && $order['shipping_phone'] != '' ) {
            $saddr[] = 'Phone: ' . $order['shipping_phone'];
        }
    } */

    //
    // Output the bill to and ship to information
    //
    $w = array(100, 80);
    $lh = 6;
    $pdf->SetFillColor(224);
    $pdf->setCellPadding(2);
    if( count($baddr) > 0 || count($saddr) > 0 ) {
        $pdf->SetFont('', 'B');
        $pdf->Cell($w[0], $lh, 'Bill To:', 1, 0, 'L', 1);
        $border = 1;
/*        if( $order['shipping_status'] > 0 ) {
            $pdf->Cell($w[1], $lh, 'Ship To:', 1, 0, 'L', 1);
            $border = 1;
            $diff_lines = (count($baddr) - count($saddr));
            // Add padding so the boxes line up
            if( $diff_lines > 0 ) {
                for($i=0;$i<$diff_lines;$i++) {
                    $saddr[] = " ";
                }
            } elseif( $diff_lines < 0 ) {
                for($i=0;$i<abs($diff_lines);$i++) {
                    $baddr[] = " ";
                }
            }
        } */
        $pdf->Ln($lh);  
        $pdf->SetFont('');
        $pdf->setCellPaddings(2, 4, 2, 2);
        $pdf->MultiCell($w[0], $lh, implode("\n", $baddr), $border, 'L', 0, 0, '', '', true, 0, false, true, 0, 'T', false);
        $pdf->Ln($lh);
    }
    $pdf->Ln();

    //
    // Add an extra space for invoices with few items
    //
    if( count($baddr) == 0 && count($saddr) == 0 && count($order['items']) < 5 ) {
        $pdf->Ln(10);
    }

    //
    // Add the invoice items
    //
    $w = array(100, 50, 30);
    $pdf->SetFillColor(224);
    $pdf->SetFont('', 'B');
    $pdf->SetCellPadding(2);
    $pdf->Cell($w[0], 6, 'Item', 1, 0, 'C', 1);
    $pdf->Cell($w[1], 6, 'Quantity/Price', 1, 0, 'C', 1);
    $pdf->Cell($w[2], 6, 'Total', 1, 0, 'C', 1);
    $pdf->Ln();
    $pdf->SetFillColor(236);
    $pdf->SetTextColor(0);
    $pdf->SetFont('');

    $fill=0;
    foreach($order['items'] as $item) {
        $discount = '';
/*        if( $item['discount_amount'] != 0 ) {
            if( $item['unit_discount_amount'] > 0 ) {
                $discount .= '-$' . number_format($item['unit_discount_amount'], 2) . (($item['quantity']>0&&$item['quantity']!=1)?('x'.$item['quantity']):'');
            }
            if( $item['unit_discount_percentage'] > 0 ) {
                if( $discount != '' ) { 
                    $discount .= ', '; 
                }
                $discount .= '-' . $item['unit_discount_percentage'] . '%';
            }
            $discount .= ' (-$' . number_format($item['discount_amount'], 2) . ')';
        } */
        if( $item['discount_text'] != '' && $item['deposit_text'] != '' ) {
            $lh = 17.5;
        } elseif( $item['discount_text'] != '' || $item['deposit_text'] != '' || $item['taxtype_name'] != '' ) {
            $lh = 13;
        } else {
            $lh = 6;
        }
//        $lh = ($item['discount_text']!='')?13:6;
        if( isset($item['code']) && $item['code'] != '' ) {
            $item['description'] = $item['code'] . ' - ' . $item['description'];
        }
        if( isset($item['notes']) && $item['notes'] != '' ) {
            $item['description'] .= "\n    " . $item['notes'];
        }
        $nlines = $pdf->getNumLines($item['description'], $w[0]);
        if( $nlines == 2 ) {
            if( (3+($nlines*5)) > $lh ) {
                $lh = 3+($nlines*5);
            }
        } elseif( $nlines > 2 ) {
            if( (2+($nlines*5)) > $lh ) {
                $lh = 2+($nlines*5);
            }
        }
        // Check if we need a page break
        if( $pdf->getY() > ($pdf->getPageHeight() - 30) ) {
            $pdf->AddPage();
            $pdf->SetFillColor(224);
            $pdf->SetFont('', 'B');
            $pdf->Cell($w[0], 6, 'Item', 1, 0, 'C', 1);
            $pdf->Cell($w[1], 6, 'Quantity/Price', 1, 0, 'C', 1);
            $pdf->Cell($w[2], 6, 'Total', 1, 0, 'C', 1);
            $pdf->Ln();
            $pdf->SetFillColor(236);
            $pdf->SetTextColor(0);
            $pdf->SetFont('');
        }
        $pdf->MultiCell($w[0], $lh, $item['description'], 1, 'L', $fill, 
            0, '', '', true, 0, false, true, 0, 'T', false);
        $quantity = (($item['quantity']>0&&$item['quantity']!=1)?($item['quantity'].' @ '):'');
        if( $item['discount_text'] != '' && $item['deposit_text'] != '' ) {
            $pdf->MultiCell($w[1], $lh, $quantity . '$' . number_format($item['unit_amount'], 2) 
                . "\n" . $item['discount_text'] . "\n" . $item['deposit_text'], 1, 'R', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
        } elseif( $item['discount_text'] != '' ) {
            $pdf->MultiCell($w[1], $lh, $quantity . '$' . number_format($item['unit_amount'], 2) 
                . "\n" . $item['discount_text'], 1, 'R', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
        } elseif( $item['deposit_text'] != '' ) {
            $pdf->MultiCell($w[1], $lh, $quantity . '$' . number_format($item['unit_amount'], 2) 
                . "\n" . $item['deposit_text'], 1, 'R', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
        } else {
            $pdf->MultiCell($w[1], $lh, $quantity . '$' . number_format($item['unit_amount'], 2), 1, 'R', $fill, 
                0, '', '', true, 0, false, true, 0, 'T', false);
        }
        if( isset($item['taxtype_name']) && $item['taxtype_name'] != '' ) {
            $pdf->MultiCell($w[2], $lh, '$' . number_format($item['total_amount'], 2)
                . "\n" . $item['taxtype_name'], 1, 'R', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
        } else {
            $pdf->MultiCell($w[2], $lh, '$' . number_format($item['total_amount'], 2), 1, 'R', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
        }
        $pdf->Ln(); 
        $fill=!$fill;
    }

    // Check if we need a page break
    if( $pdf->getY() > ($pdf->getPageHeight() - 40) ) {
        $pdf->AddPage();
    }

    //
    // Output the invoice tallies
    //
    $lh = 6;
    $blank_border = '';
    $pdf->Cell($w[0], $lh, '', $blank_border);
    $pdf->Cell($w[1], $lh, 'Subtotal', 1, 0, 'R', $fill, '', 0, false, 'T', 'T');
    $pdf->Cell($w[2], $lh, '$' . number_format($order['subtotal_amount'], 2), 1, 0, 'R', $fill, '', 0, false, 'T', 'T');
    $pdf->Ln();
    $fill=!$fill;
    if( $order['discount_amount'] > 0 ) {
        $discount = '';
        if( $order['subtotal_discount_amount'] != 0 ) {
            $discount = '-' . $order['subtotal_discount_amount_display'];
        }
        if( $order['subtotal_discount_percentage'] != 0 ) {
            $discount .= (($order['subtotal_discount_amount']!=0)?', ':'') . '-' . $order['subtotal_discount_percentage'] . '%';
        }
        $pdf->Cell($w[0], $lh, '', $blank_border);
        $pdf->Cell($w[1], $lh, 'Overall Discount (' . $discount . ')', 1, 0, 'R', $fill, '', 0, false, 'T', 'T');
        $pdf->Cell($w[2], $lh, '$' . number_format($order['subtotal_amount'], 2), 1, 0, 'R', $fill, '', 0, false, 'T', 'T');
        $pdf->Ln();
        $fill=!$fill;
    }

    //
    // Add taxes
    //
    if( isset($order['taxes']) && count($order['taxes']) > 0 ) {
        foreach($order['taxes'] as $tax) {
            $pdf->Cell($w[0], $lh, '', $blank_border);
            $pdf->Cell($w[1], $lh, $tax['description'], 1, 0, 'R', $fill, '', 0, false, 'T', 'T');
            $pdf->Cell($w[2], $lh, $tax['amount_display'], 1, 0, 'R', $fill, '', 0, false, 'T', 'T');
            $pdf->Ln();
            $fill=!$fill;
        }
    }


    //
    // If paid_amount > 0
    //
    if( $order['paid_amount'] > 0 ) {
        $pdf->SetFont('', 'B');
        $pdf->Cell($w[0], $lh, '', $blank_border);
        $pdf->Cell($w[1], $lh, 'Total:', 1, 0, 'R', $fill, '', 0, false, 'T', 'T');
        $pdf->Cell($w[2], $lh, '$' . number_format($order['total_amount'], 2), 1, 0, 'R', $fill, '', 0, false, 'T', 'T');
        $pdf->Ln();
        $fill=!$fill;

        $pdf->SetFont('', '');
        $pdf->Cell($w[0], $lh, '', $blank_border);
        $pdf->Cell($w[1], $lh, 'Paid:', 1, 0, 'R', $fill, '', 0, false, 'T', 'T');
        $pdf->Cell($w[2], $lh, '$' . number_format($order['paid_amount'], 2), 1, 0, 'R', $fill, '', 0, false, 'T', 'T');
        $pdf->Ln();
        $fill=!$fill;

        $pdf->SetFont('', '');
        $pdf->Cell($w[0], $lh, '', (($blank_border!='')?'LB':''));
        $pdf->Cell($w[1], $lh, 'Balance:', 1, 0, 'R', $fill, '', 0, false, 'T', 'T');
        $pdf->Cell($w[2], $lh, '$' . number_format($order['balance_amount'], 2), 1, 0, 'R', $fill, '', 0, false, 'T', 'T');
        $pdf->Ln();
        $fill=!$fill;
       
    } else {
        $pdf->SetFont('', 'B');
        $pdf->Cell($w[0], $lh, '', (($blank_border!='')?'LB':''));
        $pdf->Cell($w[1], $lh, 'Total:', 1, 0, 'R', $fill, '', 0, false, 'T', 'T');
        $pdf->Cell($w[2], $lh, '$' . number_format($order['total_amount'], 2), 1, 0, 'R', $fill, '', 0, false, 'T', 'T');
        $pdf->Ln();
        $fill=!$fill;
    }
    if( $order['total_savings'] > 0 ) {
        $pdf->SetFont('', '');
        $pdf->Cell($w[0], $lh, '', (($blank_border!='')?'LB':''));
        $pdf->Cell($w[1], $lh, 'Savings:', 1, 0, 'R', $fill, '', 0, false, 'T', 'T');
        $pdf->Cell($w[2], $lh, '$' . number_format($order['total_savings'], 2), 1, 0, 'R', $fill, '', 0, false, 'T', 'T');
        $pdf->Ln();
        $fill=!$fill;
    }

    //
    // Check if there is a notes to be displayed
    //
    if( isset($order['customer_notes']) 
        && $order['customer_notes'] != '' ) {
        $pdf->Ln();
        $pdf->SetFont('');
        $pdf->MultiCell(180, 5, $order['customer_notes'], 0, 'L');
    }

    //
    // Check if there is a message to be displayed
    //
    if( isset($poma_settings['invoice-bottom-message']) 
        && $poma_settings['invoice-bottom-message'] != '' ) {
        $pdf->Ln();
        $pdf->SetFont('');
        $pdf->MultiCell(180, 5, $poma_settings['invoice-bottom-message'], 0, 'L');
    }

    $filename = preg_replace("/[^a-zA-Z0-9_]/", '_', 'invoice_' . $order['order_number']);

    //
    // Let the calling function decide how they want to send the file
    //
    return array('stat'=>'ok', 'pdf'=>$pdf, 'filename'=>$filename, 'order'=>$order);
}
?>
