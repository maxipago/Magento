<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to atendimento@saffira.com.br so we can send you a copy immediately.
 *
 * @category   Saffira / maxiPago
 * @package    MaxiPago_CheckoutApi
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MaxiPago_CheckoutApi_Model_Source_Redecardcc
{
    public function toOptionArray()
    {
        return array(
            array('value' => 'VI', 'label'=>"Visa"),
            array('value' => 'MC', 'label'=>"Mastercard"),
            array('value' => 'DC', 'label'=>"Diners Club"),
        	array('value' => 'DI', 'label'=>"Discovery"),
            array('value' => 'HC', 'label'=>"Hipercard"),
        );
    }
}