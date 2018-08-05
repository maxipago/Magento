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
 * to contato@maxipago.com.br so we can send you a copy immediately.
 *
 * @category   maxiPago!
 * @package    MaxiPago_Payment
 * @author        Thiago Contardi <thiago@contardi.com.br>
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MaxiPago_Payment_Block_Adminhtml_System_Config_Form_Field_Cctype
	extends Mage_Core_Block_Html_Select
{
	public function _toHtml()
	{
	    /** @var Mage_Payment_Model_Source_Cctype $ccTypes */
	    $ccTypes = Mage::getSingleton('maxipago/source_cctype');
		$options = $ccTypes->toOptionArray();
		foreach ($options as $option) {
			$this->addOption($option['value'], $option['label']);
		}

		return parent::_toHtml();
	}

	public function setInputName($value)
	{
		return $this->setName($value);
	}
}