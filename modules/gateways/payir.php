<?php

if (!defined('WHMCS')) {

	die('This file cannot be accessed directly');
}

function payir_MetaData()
{
	return array(

		'DisplayName' => 'Pay.ir Payment Gateway Module',
		'APIVersion' => '1.1',
		'DisableLocalCredtCardInput' => TRUE,
		'TokenisedStorage' => FALSE
	);
}

function payir_config()
{
	return array(

		'FriendlyName' => array(

			'Type'  => 'System',
			'Value' => 'درگاه پرداخت و کیف پول الکترونیک Pay.ir'
		),
		'apiKey' => array(

			'FriendlyName' => 'کلید API',
			'Type'         => 'text',
            'Size'         => '35',
			'Default'      => NULL,
			'Description'  => NULL
        ),
		'currencyType' => array(

			'FriendlyName' => 'واحد پول',
			'Type'         => 'dropdown',
			'Options'      => array('Rial' => 'ریال', 'Toman' => 'تومان'),
			'Description'  => NULL
		)
	);
}

function payir_link($params)
{
	$apiKey       = $params['apiKey'];
	$currencyType = $params['currencyType'];

	$invoiceId   = $params['invoiceid'];
	$description = $params['description'];
	$amount      = $params['amount'];
	$currency    = $params['currency'];

	$firstName = $params['clientdetails']['firstname'];
	$lastName  = $params['clientdetails']['lastname'];
	$email     = $params['clientdetails']['email'];
	$address1  = $params['clientdetails']['address1'];
	$address2  = $params['clientdetails']['address2'];
	$city      = $params['clientdetails']['city'];
	$state     = $params['clientdetails']['state'];
	$postCode  = $params['clientdetails']['postcode'];
	$country   = $params['clientdetails']['country'];
	$phone     = $params['clientdetails']['phonenumber'];

	$companyName       = $params['companyname'];
	$systemUrl         = $params['systemurl'];
	$returnUrl         = $params['returnurl'];
	$langPayNow        = $params['langpaynow'];
	$moduleDisplayName = $params['name'];
	$moduleName        = $params['paymentmethod'];
	$whmcsVersion      = $params['whmcsVersion'];

	$paymentAmount = round($amount);

	if ($currencyType == 'Toman') {

		$paymentAmount = round($paymentAmount * 10);
	}

	$url  = $systemUrl . '/modules/gateways/' . $moduleName . '/request.php';
	$hash = md5($invoiceId . $paymentAmount . $apiKey);

	$postfields = array();

	$postfields['api_key']       = $apiKey;
	$postfields['invoice_id']    = $invoiceId;
	$postfields['description']   = $description;
	$postfields['paymentAmount'] = $paymentAmount;
	$postfields['currency']      = $currency;
	$postfields['first_name']    = $firstName;
	$postfields['last_name']     = $lastName;
	$postfields['email']         = $email;
	$postfields['address1']      = $address1;
	$postfields['address2']      = $address2;
	$postfields['city']          = $city;
	$postfields['state']         = $state;
	$postfields['postcode']      = $postCode;
	$postfields['country']       = $country;
	$postfields['phone']         = $phone;
	$postfields['callback_url']  = $systemUrl . '/modules/gateways/callback/' . $moduleName . '.php?secure=' . $hash;
	$postfields['return_url']    = $returnUrl;

	$htmlOutput = '<form id="gateway" name="gateway" method="post" action="' . $url . '">';

	foreach ($postfields as $key => $value) {

		$htmlOutput .= '<input id="' . $key . '" name="' . $key . '" type="hidden" value="' . $value . '" />';
	}

	$htmlOutput .= '<input type="submit" value="' . $langPayNow . '" />';
	$htmlOutput .= '</form>';

	return $htmlOutput;
}
