<?php

class Elektordi_RecapFacturation_Adminhtml_IndexController extends Mage_Adminhtml_Controller_Action
{
    // CONFIG
    private $tva = 0.21;
    // END CONFIG

     public function indexAction()
     {
          $this->_title("Facturation Recap");
          
          $this->loadLayout();
          $this->_setActiveMenu("outils_fact");
          $this->renderLayout();
     }
     
     /*
     public function downloadAction()
     {
          $p = $this->getRequest()->getParams();
          $file = trim($p['file'],'./');
          $this->_prepareDownloadResponse($file, $content);
     }
     */
     
    public function postAction()
    {
        $post = $this->getRequest()->getPost();
        try {
            if (empty($post)) Mage::throwException($this->__('Erreur formulaire'));
            
            $date_from = $post['recapfactform']['date_from'];
            $date_to = $post['recapfactform']['date_to'];
            $date_fact = $post['recapfactform']['date_fact'];
            $num_fact = $post['recapfactform']['num_fact'];
            
            $pattern = "!^\d\d/\d\d/\d\d\d\d$!";
            if(!preg_match($pattern,$date_from)) Mage::throwException($this->__('Date de début invalide: '.htmlentities($date_from)));
            if(!preg_match($pattern,$date_to)) Mage::throwException($this->__('Date de fin invalide: '.htmlentities($date_from)));
            if(!preg_match($pattern,$date_fact)) Mage::throwException($this->__('Date de facture invalide: '.htmlentities($date_fact)));
            if(!preg_match('!^\d+$!',$num_fact)) Mage::throwException($this->__('Numéro de facture invalide: '.htmlentities($num_fact)));
            $nfact = intval($num_fact);
            
            $e_from = explode('/', $date_from);
            $ts_from = mktime(0, 0, 0, $e_from[1], $e_from[0], $e_from[2]);
            $sql_from = date("Y-m-d H:i:s", $ts_from);
            
            $e_to = explode('/', $date_to);
            $ts_to = mktime(23, 59, 59, $e_to[1], $e_to[0], $e_to[2]);
            $sql_to = date("Y-m-d H:i:s", $ts_to);
            
            $long = round(($ts_to-$ts_from)/(24*3600));
            
            if($ts_to<$ts_from) Mage::throwException($this->__('Période invalide'));
            
            $collection = Mage::getModel('sales/order_invoice')->getCollection()->addAttributeToFilter('state',2)->addAttributeToFilter('created_at',array('from' => $sql_from, 'to' => $sql_to))->setOrder('increment_id','asc');
            if($collection->count()==0) Mage::throwException($this->__('Aucune commande sur la période'));
                     
            $header = explode("\n",html_entity_decode(Mage::getStoreConfig('general/store_information/address'), ENT_QUOTES, 'UTF-8'));
            //$footer = html_entity_decode(Mage::getStoreConfig('design/footer/copyright'), ENT_QUOTES, 'UTF-8');
            $curr = Mage::app()->getStore()->getBaseCurrencyCode();
            $currsy = Mage::app()->getLocale()->currency($curr)->getSymbol();
            
            $countries = array();
            foreach($collection as $data) {
                $c = $data->getBillingAddress()->getCountryId();
                $p = $data->getOrder()->getPayment()->getMethodInstance()->getCode();
                if($p=="m2epropayment") {
                    $a = unserialize($data->getOrder()->getPayment()->getAdditionalData());
                    $p = $a['payment_method'];
                }
                if(strstr(strtolower($p),'paypal')===FALSE) {
                    $p = 'Paiement direct';
                } else {
                    $p = 'PayPal';
                }
                
                if(!is_array($countries[$c])) $countries[$c] = array();
                if(!is_array($countries[$c][$p])) $countries[$c][$p] = array();
                $countries[$c][$p][] = $data;
            }
            
            $pdf = new Zend_Pdf();
            $cpp = 30; // Helvetica 12: 30 lignes possibles
            foreach($countries as $codepays => $payments) {
                //$invoices = array_merge($invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices,$invoices);
                $c = 0;
                foreach($payments as $invoices) $c+=count($invoices);
                $pcount = count($payments);
                $pn = 1;
                //$codes = explode('/',$code);
                $pays = Mage::app()->getLocale()->getCountryTranslation($codepays);
                //$paiement = Mage::getStoreConfig('payment/'.$code[1].'/title')
                
                $num = $nfact++; //'R'.date('Ymd', $ts_from).$long.$country;
                                               
                $i = 0;
                $t = 0;
                $total = 0;
                $pi = 0;
                $lastmode = "";
                
                $ptotal = 0;
                
                foreach($payments as $mode => $invoices) {
                    $pl = 0;
                    foreach($invoices as $data) {
                        if($i==0) {
                            $page = new Zend_Pdf_Page(Zend_Pdf_Page::SIZE_A4);
                            $h = $page->getHeight();
                            $font = Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA);
                            
                            $page->setFont($font, 24);
                            $this->drawCenteredText($page, "FACTURE RÉCAPITULATIVE", $h-40);
                            //$page->drawText("FACTURE RÉCAPITULATIVE", 135, $h-40, 'UTF-8');
                            $page->drawText("Page $pn/".ceil($c/$cpp), 450, $h-110, 'UTF-8');
                                            
                            $page->setFont($font, 12);
                            
                            /*
                            $page->drawText(Mage::getStoreConfig('general/store_information/name'), 40, $h-70, 'UTF-8');
                            $page->drawText(Mage::getStoreConfig('shipping/origin/street_line1'), 40, $h-90, 'UTF-8');
                            $page->drawText(Mage::getStoreConfig('shipping/origin/street_line2'), 40, $h-110, 'UTF-8');
                            $page->drawText(Mage::getStoreConfig('shipping/origin/postcode')." ".Mage::getStoreConfig('shipping/origin/city'), 40, $h-130, 'UTF-8');
                            $page->drawText(Mage::app()->getLocale()->getCountryTranslation(Mage::getStoreConfig('shipping/origin/country_id')), 40, $h-150, 'UTF-8');                
                            */
                            
                            if(isset($header[0])) $page->drawText($header[0], 40, $h-70, 'UTF-8');
                            if(isset($header[1])) $page->drawText($header[1], 40, $h-90, 'UTF-8');
                            if(isset($header[2])) $page->drawText($header[2], 40, $h-110, 'UTF-8');
                            if(isset($header[3])) $page->drawText($header[3], 40, $h-130, 'UTF-8');
                            $page->drawText('TVA: '.Mage::getStoreConfig('general/store_information/merchant_vat_number'), 40, $h-150, 'UTF-8');           
                            
                            $page->drawText("Numéro: $num", 220, $h-70, 'UTF-8');
                            $page->drawText("Date: ".$date_fact, 220, $h-90, 'UTF-8');
                            $page->drawText("Pays: $pays", 220, $h-110, 'UTF-8');
                            $page->drawText("Du $date_from au $date_to", 220, $h-130, 'UTF-8');
                            $page->drawText("Nombre de commandes: $c", 220, $h-150, 'UTF-8');
                            
                            $page->drawLine(20, $h-190, 580, $h-190); // H
                            $page->drawLine(90, $h-170, 90, 30);
                            $page->drawLine(170, $h-170, 170, 30);
                            $page->drawLine(380, $h-170, 380, 30);
                            $page->drawLine(450, $h-170, 450, 30);
                            $page->drawLine(520, $h-170, 520, 30);
                            
                            $page->drawText("Commande", 20, $h-180, 'UTF-8');
                            $page->drawText("Date", 100, $h-180, 'UTF-8');
                            $page->drawText("Client".($pcount==1?(" ($mode)"):''), 180, $h-180, 'UTF-8');
                            $page->drawText("HT", 400, $h-180, 'UTF-8');
                            $page->drawText("TVA", 470, $h-180, 'UTF-8');
                            $page->drawText("TTC", 540, $h-180, 'UTF-8');
                        }
                        
                        if($pl==0 && $pcount>1) {
                            if($pi>0) {
                                $page->drawLine(20, $h-($i*20)-200, 580, $h-($i*20)-200); // H
                                $page->drawText("$lastmode:", 360 - $this->getTextWidth($lastmode, $font, 12), $h-($i*20)-220, 'UTF-8');
                                $page->drawText($currsy.' '.round($ptotal/($this->tva+1),2), 385, $h-($i*20)-220, 'UTF-8');
                                $page->drawText($currsy.' '.round($ptotal-($ptotal/($this->tva+1)),2), 455, $h-($i*20)-220, 'UTF-8');
                                $page->drawText($currsy.' '.round($ptotal,2), 525, $h-($i*20)-220, 'UTF-8');
                                $page->drawLine(20, $h-($i*20)-230, 580, $h-($i*20)-230); // H
                                $i+=2;
                                $ptotal = 0;
                            }
                            $page->drawText("- $mode -", 230, $h-($i*20)-210, 'UTF-8');    
                            $lastmode = $mode;
                            $i++;
                        }
                        $pl++;
                        
                        $id = $data->getData('increment_id');
                        $prix = $data->getData('base_grand_total');
                        $total += $prix;
                        $ptotal += $prix;
                        
                        if($data->getData('base_currency_code') != $curr) {
                            Mage::throwException($this->__('Monnaie invalide '.$data->getData('base_currency_code').' dans commande '.$id));
                        }
                        
                        $name = $data->getBillingAddress()->getCompany();
                        if(empty($name)) $name = $data->getBillingAddress()->getFirstname().' '.$data->getBillingAddress()->getMiddlename().' '.$data->getBillingAddress()->getLastname();
                        $limit = 190;
                        $size = $this->getTextWidth($name, $font, 12);
                        if($size > $limit) {
                            while($size > $limit - 10) {
                                $name = substr($name, 0, -1);
                                $size = $this->getTextWidth($name, $font, 12);
                            }
                            $name.="...";
                        }
                        
                        $page->drawText($id, 20, $h-($i*20)-210, 'UTF-8');
                        $page->drawText(date( "d/m/Y", strtotime($data->getCreatedAt())), 100, $h-($i*20)-210, 'UTF-8');
                        $page->drawText($name, 180, $h-($i*20)-210, 'UTF-8');                    
                        $page->drawText($currsy.' '.round($prix/($this->tva+1),2), 385, $h-($i*20)-210, 'UTF-8');
                        $page->drawText($currsy.' '.round($prix-($prix/($this->tva+1)),2), 455, $h-($i*20)-210, 'UTF-8');
                        $page->drawText($currsy.' '.round($prix,2), 525, $h-($i*20)-210, 'UTF-8');
                        
                        $i++;
                        $t++;
                        if($i==$cpp) {
                            $page->drawText(".../..", 340, $h-($i*20)-210, 'UTF-8');
                            //$page->drawText($footer, 20, 20, 'UTF-8');
                            $pdf->pages[] = $page;
                            $pn++;
                            $i = 0;                        
                        }
                    }
                    $pi++;
                }
                
                $page->drawLine(20, $h-($i*20)-200, 580, $h-($i*20)-200); // H
                if($pcount>1) {
                    $page->drawText("$lastmode:", 360 - $this->getTextWidth($lastmode, $font, 12), $h-($i*20)-220, 'UTF-8');
                    $page->drawText($currsy.' '.round($ptotal/($this->tva+1),2), 385, $h-($i*20)-220, 'UTF-8');
                    $page->drawText($currsy.' '.round($ptotal-($ptotal/($this->tva+1)),2), 455, $h-($i*20)-220, 'UTF-8');
                    $page->drawText($currsy.' '.round($ptotal,2), 525, $h-($i*20)-220, 'UTF-8');
                    $i++;
                }
                $page->drawText("Total:", 340, $h-($i*20)-220, 'UTF-8');
                $page->drawText($currsy.' '.round($total/($this->tva+1),2), 385, $h-($i*20)-220, 'UTF-8');
                $page->drawText($currsy.' '.round($total-($total/($this->tva+1)),2), 455, $h-($i*20)-220, 'UTF-8');
                $page->drawText($currsy.' '.round($total,2), 525, $h-($i*20)-220, 'UTF-8');
                //$data->getOrder()->getData('status')
                //$page->drawText($footer, 20, 20, 'UTF-8');
                $pdf->pages[] = $page;
            }
            
//            $file = 'RecapFacturation_'.time().'.pdf';
            $file = 'RecapFacturation_'.$num_fact.'_a_'.($nfact-1).'.pdf';
            $this->_prepareDownloadResponse($file, $pdf->render(), 'application/pdf');
            return;
            
//            $message = $this->__('Génération du PDF réussie: <a href="'.$this->getUrl('*/*/download',array('file'=>$file)).'">Télécharger</a>');
//            Mage::getSingleton('adminhtml/session')->addSuccess($message);
            
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }
        $this->_redirect('*/*');
    }
    
    
    function drawCenteredText($page, $text, $bottom) {  
        $text_width = $this->getTextWidth($text, $page->getFont(), $page->getFontSize());
        $box_width = $page->getWidth();
        $left = ($box_width - $text_width) / 2;

        $page->drawText($text, $left, $bottom, 'UTF-8');
    }

    function getTextWidth($text, $font, $font_size) {
        $drawing_text = $text; //iconv('', 'UTF-8', $text);
        $characters    = array();
        for ($i = 0; $i < strlen($drawing_text); $i++) {
            $characters[] = $drawing_text[$i]; //(ord($drawing_text[$i++]) << 8) | ord ($drawing_text[$i]);
        }
        $glyphs        = $font->glyphNumbersForCharacters($characters);
        $widths        = $font->widthsForGlyphs($glyphs);
        $text_width   = (array_sum($widths) / $font->getUnitsPerEm()) * $font_size;
        return $text_width;
    }
    
}
