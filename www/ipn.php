<?php
	// Require the functions to connect to database and fetch config values
	require 'config.php';
	require 'engine/database/connect.php';
	function VerifyPaypalIPN(array $IPN = null){
		if(empty($IPN)){
			$IPN = $_POST;
		}
		if(empty($IPN['verify_sign'])){
			mysql_insert("INSERT INTO `znote_paypal` (`txn_id`, `email`, `accid`, `price`, `points`) VALUES ('0', 'ERROR: Verify sign is null', '0', '0', '0')");
			return null;
		}
		$IPN['cmd'] = '_notify-validate';
		$PaypalHost = (empty($IPN['test_ipn']) ? 'www' : 'www.sandbox').'.paypal.com';
		mysql_insert("INSERT INTO `znote_paypal` (`txn_id`, `email`, `accid`, `price`, `points`) VALUES ('0', 'NOTICE: About to contact = $PaypalHost', '0', '0', '0')");
		$cURL = curl_init();
		curl_setopt($cURL, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($cURL, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($cURL, CURLOPT_URL, "https://{$PaypalHost}/cgi-bin/webscr");
		curl_setopt($cURL, CURLOPT_ENCODING, 'gzip');
		curl_setopt($cURL, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($cURL, CURLOPT_POST, true); // POST back
		curl_setopt($cURL, CURLOPT_POSTFIELDS, $IPN); // the $IPN
		curl_setopt($cURL, CURLOPT_HEADER, false);
		curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($cURL, CURLOPT_FORBID_REUSE, true);
		curl_setopt($cURL, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($cURL, CURLOPT_TIMEOUT, 60);
		curl_setopt($cURL, CURLINFO_HEADER_OUT, true);
		curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
			'Connection: close',
			'Expect: ',
		));
		$Response = curl_exec($cURL);
		$Status = (int)curl_getinfo($cURL, CURLINFO_HTTP_CODE);
		curl_close($cURL);
		if(empty($Response)){
			mysql_insert("INSERT INTO `znote_paypal` (`txn_id`, `email`, `accid`, `price`, `points`) VALUES ('0', 'ERROR: Response is empty.', '0', '0', '0')");
			return null;
		}
		if(!preg_match('~^(VERIFIED|INVALID)$~i', $Response = trim($Response))){
			mysql_insert("INSERT INTO `znote_paypal` (`txn_id`, `email`, `accid`, `price`, `points`) VALUES ('0', 'ERROR: Preg match returned false. $Response', '0', '0', '0')");
			return null;
		}
		if(!$Status){
			mysql_insert("INSERT INTO `znote_paypal` (`txn_id`, `email`, `accid`, `price`, `points`) VALUES ('0', 'ERROR: Status is false = $Response = $Status', '0', '0', '0')");
			return null;
		}
		if(intval($Status / 100) != 2){
			mysql_insert("INSERT INTO `znote_paypal` (`txn_id`, `email`, `accid`, `price`, `points`) VALUES ('0', 'ERROR: Status is invalid. = $Status', '0', '0', '0')");
			return false;
		}
		mysql_insert("INSERT INTO `znote_paypal` (`txn_id`, `email`, `accid`, `price`, `points`) VALUES ('0', 'Looks good: $Status $Response', '0', '0', '0')");
		return !strcasecmp($Response, 'VERIFIED');
	}
	// Fetch paypal configurations
	$paypal = $config['paypal'];
	$prices = $config['paypal_prices'];
	// Send an empty HTTP 200 OK response to acknowledge receipt of the notification 
	header('HTTP/1.1 200 OK'); 
	// Build the required acknowledgement message out of the notification just received
	$req = 'cmd=_notify-validate';
	foreach ($_POST as $key => $value) {
		$value = urlencode(stripslashes($value));
		$req  .= "&$key=$value";
	}
	$postdata = $req;
	// Assign payment notification values to local variables
	$item_name        = $_POST['item_name'];
	$item_number      = $_POST['item_number'];
	$payment_status   = $_POST['payment_status'];
	$payment_amount   = $_POST['mc_gross'];
	$payment_currency = $_POST['mc_currency'];
	$txn_id           = $_POST['txn_id'];
	$receiver_email   = $_POST['receiver_email'];
	$payer_email      = $_POST['payer_email'];
	$custom           = (int)$_POST['custom'];
	$connectedIp = $_SERVER['REMOTE_ADDR'];
	mysql_insert("INSERT INTO `znote_paypal` (`txn_id`, `email`, `accid`, `price`, `points`) VALUES ('$txn_id', 'Connection from IP: $connectedIp', '0', '0', '0')");
	$status = VerifyPaypalIPN();
	if ($status) {
		// Check that the payment_status is Completed
		if ($payment_status == 'Completed') {
			// Check that txn_id has not been previously processed
			$txn_id_check = mysql_select_single("SELECT `txn_id` FROM `znote_paypal` WHERE `txn_id`='$txn_id'");
			if ($txn_id_check !== false) {
				// Check that receiver_email is your Primary PayPal email
				if ($receiver_email == $paypal['email']) {
					$status = true;
					$paidMoney = 0;
					$paidPoints = 0;
					foreach ($prices as $priceValue => $pointsValue) {
						if ($priceValue == $payment_amount) {
							$paidMoney = $priceValue;
							$paidPoints = $pointsValue;
						}
					}
					if ($paidMoney == 0) $status = false; // Wrong ammount of money
					if ($payment_currency != $paypal['currency']) $status = false; // Wrong currency
					// Verify that the user havent messed around with POST data
					if ($status) {
						// transaction log
						mysql_insert("INSERT INTO `znote_paypal` (`txn_id`, `email`, `accid`, `price`, `points`) VALUES ('$txn_id', '$payer_email', '$custom', '".$paidMoney."', '".$paidPoints."')");
						// Process payment
						$data = mysql_select_single("SELECT `points` AS `old_points` FROM `znote_accounts` WHERE `account_id`='$custom';");
						// Give points to user
						$new_points = $data['old_points'] + $paidPoints;
						mysql_update("UPDATE `znote_accounts` SET `points`='$new_points' WHERE `account_id`='$custom'");
					}
				}  else {
					$pmail = $paypal['email'];
					mysql_insert("INSERT INTO `znote_paypal` (`txn_id`, `email`, `accid`, `price`, `points`) VALUES ('$txn_id', 'ERROR: Wrong mail. Received: $receiver_email, configured: $pmail', '0', '0', '0')");
				}
			}
		}
	} else {
		// Something is wrong
		mysql_insert("INSERT INTO `znote_paypal` (`txn_id`, `email`, `accid`, `price`, `points`) VALUES ('$txn_id', 'ERROR: Invalid data. $status : $postdata', '0', '0', '0')");
	}
?>