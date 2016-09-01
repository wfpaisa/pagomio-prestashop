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

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');
include(dirname(__FILE__).'/pagomio.php');
require_once dirname(__FILE__).'/pagomio-sdk-php/pagomio.php';
require_once dirname(__FILE__).'/pagomio-sdk-php/Requests/library/Requests.php';
Requests::register_autoloader();

$pagomio = new Pagomio();

if(!Configuration::get('PAGOMIO_CLIENT_ID') || !Configuration::get('PAGOMIO_SECRET_ID'))
{
	die('Need to configure the client_id and secrect _id');

}

$pagomioClient = new Pagomio\Pagomio(Configuration::get('PAGOMIO_CLIENT_ID'),Configuration::get('PAGOMIO_SECRET_ID'),Configuration::get('PAGOMIO_TEST')=='false'?false:true);

$cart = Context::getContext()->cart;
$customer = Context::getContext()->customer;
$currency = new Currency((int)$cart->id_currency);

if (!Validate::isLoadedObject($customer) && !Validate::isLoadedObject($currency))
{
	Logger::addLog('Issue loading customer,and/or currency data');
	die('An unrecoverable error occured while retrieving you data');
}

//Customer information - Not required
$userData = new Pagomio\UserData();
$userData->names = Tools::safeOutput($customer->firstname);
$userData->lastNames = Tools::safeOutput($customer->lastname);
//$userData->identificationType = 'CC'; # Allow: CC, TI, PT, NIT
//$userData->identification = '123456789';
$userData->email = Tools::safeOutput($customer->email);

// Payment information - Is required
$paymentData = new Pagomio\PaymentData();



$paymentData->currency = Tools::safeOutput($currency->iso_code);
$paymentData->reference = Tools::safeOutput((int)$cart->id);
$paymentData->totalAmount = Tools::safeOutput($cart->getordertotal(true));

$cart_details = $cart->getSummaryDetails(null, true);
if ($cart_details['total_tax'] != 0)
	$base = $cart_details['total_price_without_tax'] - $cart_details['total_shipping_tax_exc'];
else
	$base = 0;

$paymentData->taxAmount = Tools::safeOutput($cart_details['total_tax']);

$paymentData->devolutionBaseAmount = Tools::safeOutput($base);

$products = $cart->getProducts();
$description = '';
foreach ($products as $product)
	$description .= $product['name'].',';

$paymentData->description = $description;

if (Configuration::get('PS_SSL_ENABLED') || (!empty($_SERVER['HTTPS']) && Tools::strtolower($_SERVER['HTTPS']) != 'off'))
{
	if (method_exists('Tools', 'getShopDomainSsl'))
		$url = 'https://'.Tools::getShopDomainSsl().__PS_BASE_URI__.'/modules/'.$pagomio->name.'/';
	else
		$url = 'https://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'modules/'.$pagomio->name.'/';
}
else
	$url = 'http://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'/modules/'.$pagomio->name.'/';


	$response_url = 'http://'.htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__.'index.php?fc=module&module=pagomio&controller=response';

	$confirmation_url = 'http://'.htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__.'modules/pagomio/pages/confirmation.php';


// Url return to after payment - Is required
$enterpriseData = new Pagomio\EnterpriseData();
$enterpriseData->url_redirect = $response_url;
$enterpriseData->url_notify = $confirmation_url;
// Create the object
$aut = new Pagomio\AuthorizePayment();
$aut->enterpriseData = $enterpriseData;
$aut->paymentData = $paymentData;
$aut->userData = $userData;

//var_dump($aut);
// Generate the token
try{

	$response = $pagomioClient->getToken($aut);
}
catch(Exception $e)
{
	Logger::addLog('Issue creating a token in pagomio. Error: '.$e->getMessage());
	die('An unrecoverable error occured while creating a token payment: '.$e->getMessage());
}

// Redirect to Pagomio.com
if($response->success) {
	header("Location: " . $response->url);
}

