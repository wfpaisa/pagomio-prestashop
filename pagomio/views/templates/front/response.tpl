<link rel="stylesheet" href="{$css_dir}global.css" type="text/css" media="all">
<link href="{$css|escape:'htmlall':'UTF-8'}payu.css" rel="stylesheet" type="text/css">
{if $valid}
	<center>
		<table class="table-response">
			<tr align="center">
				<th colspan="2"><h1 class="md-h1">{l s='Datos de la transacción' mod='pagomio'}</h1></th>
			</tr>
			<tr align="left">
				<td>{l s='Estado de la transacción' mod='pagomio'}</td>
				<td>{$estadoTx|escape:'htmlall':'UTF-8'}</td>
			</tr>
			<tr align="left">
				<td>{l s='Número de Transacción' mod='pagomio'}</td>
				<td>{$transactionId|escape:'htmlall':'UTF-8'}</td>
			</tr>
			<tr align="left">
				<td>{l s='Referencia de la transacción' mod='pagomio'}</td>
				<td>{$referenceCode|escape:'htmlall':'UTF-8'}</td>
			</tr>
			<tr align="left">
				<td>{l s='Total' mod='pagomio'}</td>
				<td>${$value|escape:'htmlall':'UTF-8'}</td>
			</tr>
			<tr align="left">
				<td>{l s='Medio de pago' mod='pagomio'}</td>
				<td>{$lapPaymentMethod|escape:'htmlall':'UTF-8'}</td>
			</tr>
		</table>
		<p/>
		<h1>{$messageApproved|escape:'htmlall':'UTF-8'}</h1>
	</center>
{else}
	<h1><center>{l s='The request is incorrect! There is an error in the digital signature.' mod='pagomio'}</center></h1>
{/if}
