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

include(dirname(__FILE__).'/../../../config/config.inc.php');
include(dirname(__FILE__).'/../../../init.php');
include(dirname(__FILE__).'/../pagomio.php');
require_once dirname(__FILE__).'/../pagomio-sdk-php/pagomio.php';
require_once dirname(__FILE__).'/../pagomio-sdk-php/Requests/library/Requests.php';

Requests::register_autoloader();
$pagomioClient = new Pagomio\Pagomio(Configuration::get('PAGOMIO_CLIENT_ID'),Configuration::get('PAGOMIO_SECRET_ID'),Configuration::get('PAGOMIO_TEST')=='false'?false:true);
if(isset($_GET['reference']))
{
	$response = $pagomioClient->getRequestPayment($_GET['reference']);
}
else {
	$response = $pagomioClient->getRequestPayment();
}
$pagomio = new Pagomio();

$cart = new Cart((int)$response->reference);

	$state = 'PAGOMIO_OS_FAILED';
    if ($response->status == 1 )
		$state = 'PAGOMIO_OS_PENDING';
	else if ($response->status == 2)
		$state = 'PS_OS_PAYMENT';

	if (!Validate::isLoadedObject($cart))
    $errors[] = $this->module->l('Invalid Cart ID');
	else
	{
		$currency_cart = new Currency((int)$cart->id_currency);


			if ($cart->orderExists())
			{
				$order = new Order((int)Order::getOrderByCartId($cart->id));


				$current_state = $order->current_state;
				if ($current_state != Configuration::get('PS_OS_PAYMENT'))
				{
					$history = new OrderHistory();
					$history->id_order = (int)$order->id;
					$history->changeIdOrderState((int)Configuration::get($state), $order, true);
					$history->addWithemail(true);
				}

			}
			else
			{
				$customer = new Customer((int)$cart->id_customer);

				Context::getContext()->customer = $customer;
				Context::getContext()->currency = $currency_cart;

				$pagomio->validateOrder((int)$cart->id, (int)Configuration::get($state), (float)$cart->getordertotal(true), 'Pagomio', null, array(), (int)$currency_cart->id, false, $customer->secure_key);
				$order = new Order((int)Order::getOrderByCartId($cart->id));

			}
			if ($state != 'PS_OS_PAYMENT')
			{
				foreach ($order->getProductsDetail() as $product)
					StockAvailable::updateQuantity($product['product_id'], $product['product_attribute_id'], + (int)$product['product_quantity'], $order->id_shop);
			}
	}


?>
