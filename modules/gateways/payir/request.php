<?php

require_once(__DIR__ . '/../../../init.php');
require_once(__DIR__ . '/../../../includes/gatewayfunctions.php');
require_once(__DIR__ . '/../../../includes/invoicefunctions.php');

$gatewayParams = getGatewayVariables('payir');

if ($gatewayParams['type'] == FALSE) {

	die('Module Not Activated');
}

$failure = FALSE;

function common($url, $params)
{
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

	$response = curl_exec($ch);
	$error    = curl_errno($ch);

	curl_close($ch);

	$output = $error ? FALSE : json_decode($response);

	return $output;
}

if(extension_loaded('curl'))
{
	$apiKey        = isset($_POST['api_key']) ? $_POST['api_key'] : NULL;
	$paymentAmount = isset($_POST['paymentAmount']) ? $_POST['paymentAmount'] : NULL;
	$callbackUrl   = isset($_POST['callback_url']) ? $_POST['callback_url'] : NULL;
	$invoiceId     = isset($_POST['invoice_id']) ? $_POST['invoice_id'] : NULL;

	$params = array(

		'api'          => $apiKey,
		'amount'       => $paymentAmount,
		'redirect'     => urlencode($callbackUrl),
		'factorNumber' => $invoiceId
	);

	$result = common('https://pay.ir/payment/send', $params);

	if ($result && isset($result->status) && $result->status == 1) {

		$gatewayUrl = 'https://pay.ir/payment/gateway/' . $result->transId;

		header('Location: ' . $gatewayUrl);
		exit;

	} else {

		$failure      = TRUE;
		$errorMessage = 'در ارتباط با وب سرویس Pay.ir خطایی رخ داده است';

		$errorCode    = isset($result->errorCode) ? $result->errorCode : 'Send';
		$errorMessage = isset($result->errorMessage) ? $result->errorMessage : $errorMessage;

		logTransaction($gatewayParams['name'], array(

			'Code'    => $errorCode,
			'Message' => $errorMessage,
			'Invoice' => $invoiceId

		), 'Failure');
	}

} else {

	$failure      = TRUE;
	$errorMessage = 'تابع cURL در سرور فعال نمی باشد';

	logTransaction($gatewayParams['name'], array(

		'Code'    => 'cURL',
		'Message' => $errorMessage

	), 'Failure');
}

if ($failure) {

	if (isset($invoiceId) && $invoiceId) {

		header('Location: ' . $gatewayParams['systemurl'] . '/viewinvoice.php?id=' . $invoiceId);
		exit;

	} else {

		header('Location: ' . $gatewayParams['systemurl'] . '/clientarea.php?action=invoices');
		exit;
	}
}
