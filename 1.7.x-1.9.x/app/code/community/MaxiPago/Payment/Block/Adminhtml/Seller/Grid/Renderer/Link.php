<?php

/**
 * Bizcommerce Desenvolvimento de Plataformas Digitais Ltda - Epp
 *
 * INFORMAÇÕES SOBRE LICENÇA
 *
 * Open Software License (OSL 3.0).
 * http://opensource.org/licenses/osl-3.0.php
 *
 * DISCLAIMER
 *
 * Não edite este arquivo caso você pretenda atualizar este módulo futuramente
 * para novas versões.
 *
 * @category      maxiPago!
 * @package       MaxiPago_Payment
 *
 * @license       http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
class MaxiPago_Payment_Block_Adminhtml_Seller_Grid_Renderer_Link
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        /** @var $row MaxiPago_Payment_Model_Seller */
        $url = str_replace("/index.php/", "", $this->getBaseUrl()) . $row->getUrl();
        return "<a href='#' onclick='window.open(\"" . $url . "\")'>' . $this->__('View') . '</a>";
    }
}