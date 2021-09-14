<?php if (!defined('ABSPATH')) exit;
global $wpdb,$postid;
$wpcf7 = WPCF7_ContactForm::get_current();
$user_email = $user_mobile = $description = $user_price = '';
if ($submission = WPCF7_Submission::get_instance())
{
	$data = $submission->get_posted_data();
    $user_name = isset($data['user_name']) ? $data['user_name'] : "";
	$user_email = isset($data['user_email']) ? $data['user_email'] : "";
	$user_mobile = isset($data['user_mobile']) ? $data['user_mobile'] : "";
	$description = isset($data['description']) ? $data['description'] : "";
	$user_price = isset($data['user_price']) ? $data['user_price'] : 0;
}
$price = get_post_meta($postid, "_cf7raypay_price", true);
if ($price == "")
{
	$price = $user_price;
}
$options = get_option('cf7raypay_options');
foreach ($options as $k => $v)
{
	$value[$k] = $v;
}

$invoice_id = round(microtime(true)*1000) ;
$redirect_url= get_site_url().'/'.$value['return'];
$sandbox = $value['sandbox'] === 1;
$payment_endpoint = 'https://api.raypay.ir/raypay/api/v1/Payment/pay';
$data = array(
    'amount' => strval($price),
    'invoiceID' => strval($invoice_id),
    'userID' => $value['raypay_user_id'],
    'redirectUrl' => $redirect_url,
    'factorNumber' => strval(time()),
    'email' => $user_email,
    'mobile' => $user_mobile,
    'comment' => $description,
    'fullName' => $user_name,
    'marketingID' => $value['raypay_marketing_id'],
    'enableSandBox' => $sandbox
);
$headers = array(
    'Content-Type' => 'application/json',
);

$args = array(
    'body' => json_encode($data),
    'headers' => $headers,
    'timeout' => 15,
);


$response =  wp_remote_post($payment_endpoint, $args);
$http_status = wp_remote_retrieve_response_code($response);
$result = wp_remote_retrieve_body($response);
$result = json_decode($result);
if (isset($result->Data) )
{
    $token = $result->Data;
    $wpdb->insert($wpdb->prefix."cf7raypay_transaction", $trans = array(
			'idform'      => $postid,
			'gateway'     => 'RayPay',
			'cost'        => $price,
			'created_at'  => time(),
			'email'       => $user_email,
			'user_mobile' => $user_mobile,
			'description' => $description,
			'status'      => 'none',
			'transid'     => $invoice_id
		), $schema = array('%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s'));

    $link='https://my.raypay.ir/ipg?token=' . $token;
    wp_redirect( $link );
}
else
{
	if (isset($result->Message)) {
		$error = $result->Message;
	} else {
		$error = 'UnExpected Error!';
	}
}
exit;

?>