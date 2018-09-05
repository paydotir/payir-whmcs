<?php

require_once(__DIR__ . '/../../../init.php');
require_once(__DIR__ . '/../../../includes/gatewayfunctions.php');
require_once(__DIR__ . '/../../../includes/invoicefunctions.php');

$gatewayParams = getGatewayVariables('payir');

if ($gatewayParams['type'] == FALSE) {

	die('Module Not Activated');
}

$success = FALSE;

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

if (isset($_POST['status']) && isset($_POST['transId']) && isset($_POST['factorNumber']) && isset($_GET['secure'])) {

	$flag = FALSE;

	$status       = @mysql_real_escape_string($_POST['status']);
	$transId      = @mysql_real_escape_string($_POST['transId']);
	$factorNumber = @mysql_real_escape_string($_POST['factorNumber']);
	$message      = @mysql_real_escape_string($_POST['message']);
	$secure       = @mysql_real_escape_string($_GET['secure']);

	$query = @mysql_query("select * from tblinvoices where id = '$factorNumber' AND status = 'Paid'");

	if (@mysql_num_rows($query) == 1) {
		
		$flag = TRUE;
	}

	if (isset($status) && $status == 1) {

		if ($flag) {

			$errorMessage = 'تایید تراکنش در گذشته با موفقیت انجام شده است';

			logTransaction($gatewayParams['name'], array(

				'Code'    => 'Double Spending',
				'Message' => $errorMessage

			), 'Failure');

		} else {

			$apiKey    = $gatewayParams['apiKey'];
			$invoiceId = checkCbInvoiceID($factorNumber, $gatewayParams['name']);

			$params = array (

				'api'     => $apiKey,
				'transId' => $transId
			);

			$result = common('https://pay.ir/payment/verify', $params);

			if ($result && isset($result->status) && $result->status == 1) {

				$cardNumber = isset($_POST['cardNumber']) ? $_POST['cardNumber'] : 'Null';

				$amount = $result->amount;

				$hash = md5($factorNumber . $amount . $apiKey);

				if ($secure == $hash) {

					$success = TRUE;
					$message = 'تراکنش با موفقیت انجام شد';

					if ($gatewayParams['currencyType'] == 'Toman') {

						$amount = round($amount / 10);
					}

					addInvoicePayment($factorNumber, $transId, $amount, 0, 'payir');

					logTransaction($gatewayParams['name'], array(

						'Message'     => $message,
						'Transaction' => $transId,
						'Invoice'     => $factorNumber,
						'Amount'      => $amount,
						'Card Number' => $cardNumber

					), 'Success');

				} else {

					$errorMessage = 'رقم تراكنش با رقم پرداخت شده مطابقت ندارد';

					logTransaction($gatewayParams['name'], array(

						'Code'        => 'Invalid Amount',
						'Message'     => $errorMessage,
						'Transaction' => $transId,
						'Invoice'     => $factorNumber,
						'Amount'      => $amount,
						'Card Number' => $cardNumber

					), 'Failure');
				}

			} else {

				$errorMessage = 'در ارتباط با وب سرویس Pay.ir خطایی رخ داده است';

				$errorCode    = isset($result->errorCode) ? $result->errorCode : 'Verify';
				$errorMessage = isset($result->errorMessage) ? $result->errorMessage : $errorMessage;

				logTransaction($gatewayParams['name'], array(

					'Code'        => $errorCode,
					'Message'     => $errorMessage,
					'Transaction' => $transId,
					'Invoice'     => $factorNumber

				), 'Failure');
			}
		}

	} else {

		if ($message) {

			logTransaction($gatewayParams['name'], array(

				'Code'        => 'Invalid Payment',
				'Message'     => $message,
				'Transaction' => $transId,
				'Invoice'     => $factorNumber

			), 'Failure');

		} else {

			$errorMessage = 'تراكنش با خطا مواجه شد و یا توسط پرداخت کننده کنسل شده است';

			logTransaction($gatewayParams['name'], array(

				'Code'        => 'Invalid Payment',
				'Message'     => $errorMessage,
				'Transaction' => $transId, 
				'Invoice'     => $factorNumber

			), 'Failure');
		}
	}

} else {

	$errorMessage = 'اطلاعات ارسال شده مربوط به تایید تراکنش ناقص و یا غیر معتبر است';

	logTransaction($gatewayParams['name'], array(

		'Code'    => 'Invalid Data',
		'Message' => $errorMessage

	), 'Failure');
}

if (isset($factorNumber) && $factorNumber) {

	header('Location: ' . $gatewayParams['systemurl'] . '/viewinvoice.php?id=' . $factorNumber);
	exit;

} else {

	header('Location: ' . $gatewayParams['systemurl'] . '/clientarea.php?action=invoices');
	exit;
}
