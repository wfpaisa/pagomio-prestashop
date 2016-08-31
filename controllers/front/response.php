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

require_once dirname(__FILE__).'/../../pagomio-sdk-php/pagomio.php';
require_once dirname(__FILE__).'/../../pagomio-sdk-php/Requests/library/Requests.php';
class PagomioResponseModuleFrontController extends ModuleFrontController
{
	public function initContent()
    {
		Requests::register_autoloader();
		$pagomioClient = new Pagomio\Pagomio(Configuration::get('PAGOMIO_CLIENT_ID'),Configuration::get('PAGOMIO_SECRET_ID'),Configuration::get('PAGOMIO_TEST')=='false'?false:true);
		if(isset($_GET['reference']))
		{
			$response = $pagomioClient->getRequestPayment($_GET['reference']);
		}
		else {
			$response = $pagomioClient->getRequestPayment();
		}

		parent::initContent();

        $this->context = Context::getContext();
     
		$pagomio = new Pagomio();

		$value = number_format($response->total_amount, 1, '.', '');

		$messageApproved = '';
		$status = Configuration::get('PAGOMIO_OS_PENDING');
		if ($response->status == 3 )
		{
			$estado_tx = $pagomio->l('Transacción fallida');
			$status = Configuration::get('PAGOMIO_OS_FAILED');
		}
		else if ($response->status == 1)
		{

			$estado_tx = $pagomio->l('Transacción pendiente');
			$status = Configuration::get('PAGOMIO_OS_PENDING');
		}
		else if ($response->status == 2)
		{
			$estado_tx = $pagomio->l('Transacción Aprobada');
			$messageApproved = $pagomio->l('Gracias por tu compra!');
			$status = Configuration::get('PS_OS_PAYMENT');
		}
		else
		{
			if (isset($_REQUEST['message']))
				$estado_tx = $_REQUEST['message'];
			else
				$estado_tx = $_REQUEST['mensaje'];
		}


		$cart = new Cart((int)$response->reference);

		if (!($cart->orderExists()))
		{
			$customer = new Customer((int)$cart->id_customer);
			$this->context->customer = $customer;
			$pagomio->validateOrder((int)$cart->id, $status, (float)$cart->getordertotal(true), 'Pagomio', null, array(), (int)$cart->id_currency, false, $customer->secure_key);
		}

		$this->context->smarty->assign(
			array(
				'estadoTx' => $estado_tx,
				'transactionId' => $response->transaction_id,
				'referenceCode' => $response->reference,
				'cus' => $response->cus,
				'value' => $value,
				'lapPaymentMethod' => $response->franchise,
				'messageApproved' => $messageApproved,
				'valid' => true,
				'css' => '../modules/pagomio/css/'
			)
		);

        $this->setTemplate('response.tpl');
    }
}
?>