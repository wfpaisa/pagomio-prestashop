<?php
/**
* 2016 PAGOMIO
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PAGOMIO  <duvan.monsalve@pagomio.com>
*  @copyright 2016 Pagomío
*  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*/

if (!defined('_PS_VERSION_'))
	exit;

class Pagomio extends PaymentModule {

private $_postErrors = array();

public function __construct()
{
	$this->name = 'pagomio';
	$this->tab = 'payments_gateways';
	$this->version = '1.0.0';
	$this->author = 'Pagomío';
	$this->need_instance = 0;
	$this->currencies = true;
	$this->currencies_mode = 'checkbox';
	$this->module_key = 'db3f6962a8352af9adc95f05b521c37f';
	parent::__construct();

	$this->displayName = $this->l('Pagomío');
	$this->description = $this->l('Payment gateway for Pagomío');

	$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
	/* Backward compatibility */
	if (_PS_VERSION_ < '1.5')
		require(_PS_MODULE_DIR_.$this->name.'/backward_compatibility/backward.php');

}

public function install()
{
	$this->_createStates();
	if (!parent::install()
		|| !$this->registerHook('payment')
		|| !$this->registerHook('paymentReturn'))
		return false;
	return true;
}

public function uninstall()
{
	if (!parent::uninstall()
		|| !Configuration::deleteByName('PAGOMIO_CLIENT_ID')
		|| !Configuration::deleteByName('PAGOMIO_SECRET_ID')
		|| !Configuration::deleteByName('PAGOMIO_TEST')
		|| !Configuration::deleteByName('PAGOMIO_OS_PENDING')
		|| !Configuration::deleteByName('PAGOMIO_OS_FAILED')
		|| !Configuration::deleteByName('PAGOMIO_OS_REJECTED'))
		return false;
	return true;
}

public function getContent()
{
	$html = '';

	if (isset($_POST) && isset($_POST['submitPagomio']))
	{
		$this->_postValidation();
		if (!count($this->_postErrors))
		{
			$this->_saveConfiguration();
			$html .= $this->displayConfirmation($this->l('Settings updated'));
		}
		else
			foreach ($this->_postErrors as $err)
				$html .= $this->displayError($err);
	}
	return $html.$this->_displayAdminTpl();
}

private function _displayAdminTpl()
{
	$this->context->smarty->assign(array(
		'tab' => array(
			'intro' => array(
				'title' => $this->l('How to configure'),
				'content' => $this->_displayHelpTpl(),
				'icon' => '../modules/pagomio/img/info-icon.gif',
				'tab' => 'conf',
				'selected' => (Tools::isSubmit('submitPagomio') ? false : true),
				'style' => 'config_pagomio'
			),
			'credential' => array(
				'title' => $this->l('Credentials'),
				'content' => $this->_displayCredentialTpl(),
				'icon' => '../modules/pagomio/img/credential.png',
				'tab' => 'crendeciales',
				'selected' => (Tools::isSubmit('submitPagomio') ? true : false),
				'style' => 'credentials_payu'
			),
		),
		'tracking' => 'http://www.prestashop.com/modules/pagosonline.png?url_site='.Tools::safeOutput($_SERVER['SERVER_NAME']).'&id_lang='.
		(int)$this->context->cookie->id_lang,
		'img' => '../modules/pagomio/img/',
		'css' => '../modules/pagomio/css/',
		'lang' => ($this->context->language->iso_code != 'en' || $this->context->language->iso_code != 'es' ? 'en' : $this->context->language->iso_code)
	));

	return $this->display(__FILE__, 'views/templates/admin/admin.tpl');
}

private function _displayHelpTpl()
{
	return $this->display(__FILE__, 'views/templates/admin/help.tpl');
}

private function _displayCredentialTpl()
{
	$this->context->smarty->assign(array(
		'formCredential' => './index.php?tab=AdminModules&configure=pagomio&token='.Tools::getAdminTokenLite('AdminModules').
		'&tab_module='.$this->tab.'&module_name=pagomio',
		'credentialTitle' => $this->l('Log in'),
		'credentialInputVar' => array(
			'client_id' => array(
				'name' => 'client_id',
				'required' => true,
				'value' => (Tools::getValue('client_id') ? Tools::safeOutput(Tools::getValue('client_id')) :
				Tools::safeOutput(Configuration::get('PAGOMIO_CLIENT_ID'))),
				'type' => 'text',
				'label' => $this->l('Client Id'),
				'desc' => $this->l('Lo obtienes en "pagomio.com”').'<br>',
			),
			'secret_id' => array(
				'name' => 'secret_id',
				'required' => true,
				'value' => (Tools::getValue('secret_id') ? Tools::safeOutput(Tools::getValue('secret_id')) :
				Tools::safeOutput(Configuration::get('PAGOMIO_SECRET_ID'))),
				'type' => 'text',
				'label' => $this->l('Secret Id'),
				'desc' => $this->l('Lo obtienes en “pagomio.com”'),
			),
			'test' => array(
				'name' => 'test',
				'required' => false,
				'value' => (Tools::getValue('test') ? Tools::safeOutput(Tools::getValue('test')) : Tools::safeOutput(Configuration::get('PAGOMIO_TEST'))),
				'type' => 'radio',
				'values' => array('true', 'false'),
				'label' => $this->l('Modo Pruebas'),
				'desc' => $this->l(''),
			))));
	return $this->display(__FILE__, 'views/templates/admin/credential.tpl');
}


	public function hookPayment($params)
	{
		if (!$this->active)
			return;

		$this->context->smarty->assign(array(
			'css' => '../modules/pagomio/css/',
			'module_dir' => _PS_MODULE_DIR_.$this->name.'/'
		));

		return $this->display(__FILE__, 'views/templates/hook/pagomio_payment.tpl');
	}

	private function _postValidation()
	{
		if (!Validate::isCleanHtml(Tools::getValue('client_id'))
			|| !Validate::isGenericName(Tools::getValue('client_id')))
			$this->_postErrors[] = $this->l('You must indicate the client id');

		if (!Validate::isCleanHtml(Tools::getValue('secret_id'))
			|| !Validate::isGenericName(Tools::getValue('secret_id')))
			$this->_postErrors[] = $this->l('You must indicate the secret id');

		if (!Validate::isCleanHtml(Tools::getValue('test'))
			|| !Validate::isGenericName(Tools::getValue('test')))
			$this->_postErrors[] = $this->l('You must indicate if the transaction mode is test or not');


	}

	private function _saveConfiguration()
	{
		Configuration::updateValue('PAGOMIO_CLIENT_ID', (string)Tools::getValue('client_id'));
		Configuration::updateValue('PAGOMIO_SECRET_ID', (string)Tools::getValue('secret_id'));
		Configuration::updateValue('PAGOMIO_TEST', Tools::getValue('test'));
	}



	private function _createStates()
	{
		if (!Configuration::get('PAGOMIO_OS_PENDING'))
		{
			$order_state = new OrderState();
			$order_state->name = array();
			foreach (Language::getLanguages() as $language)
				$order_state->name[$language['id_lang']] = 'Pending';

			$order_state->send_email = false;
			$order_state->color = '#FEFF64';
			$order_state->hidden = false;
			$order_state->delivery = false;
			$order_state->logable = false;
			$order_state->invoice = false;

			if ($order_state->add())
			{
				$source = dirname(__FILE__).'/img/logo.jpg';
				$destination = dirname(__FILE__).'/../../img/os/'.(int)$order_state->id.'.gif';
				copy($source, $destination);
			}
			Configuration::updateValue('PAGOMIO_OS_PENDING', (int)$order_state->id);
		}

		if (!Configuration::get('PAGOMIO_OS_FAILED'))
		{
			$order_state = new OrderState();
			$order_state->name = array();
			foreach (Language::getLanguages() as $language)
				$order_state->name[$language['id_lang']] = 'Failed Payment';

			$order_state->send_email = false;
			$order_state->color = '#8F0621';
			$order_state->hidden = false;
			$order_state->delivery = false;
			$order_state->logable = false;
			$order_state->invoice = false;

			if ($order_state->add())
			{
				$source = dirname(__FILE__).'/img/logo.jpg';
				$destination = dirname(__FILE__).'/../../img/os/'.(int)$order_state->id.'.gif';
				copy($source, $destination);
			}
			Configuration::updateValue('PAGOMIO_OS_FAILED', (int)$order_state->id);
		}

		if (!Configuration::get('PAGOMIO_OS_REJECTED'))
		{
			$order_state = new OrderState();
			$order_state->name = array();
			foreach (Language::getLanguages() as $language)
				$order_state->name[$language['id_lang']] = 'Rejected Payment';

			$order_state->send_email = false;
			$order_state->color = '#8F0621';
			$order_state->hidden = false;
			$order_state->delivery = false;
			$order_state->logable = false;
			$order_state->invoice = false;

			if ($order_state->add())
			{
				$source = dirname(__FILE__).'/img/logo.jpg';
				$destination = dirname(__FILE__).'/../../img/os/'.(int)$order_state->id.'.gif';
				copy($source, $destination);
			}
			Configuration::updateValue('PAGOMIO_OS_REJECTED', (int)$order_state->id);
		}
	}

}
?>
