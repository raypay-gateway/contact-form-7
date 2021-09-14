<?php if (!defined('ABSPATH')) exit;
/*
Plugin Name: RayPay payment gateway for Contact Form 7
Description: <a href="https://raypay.ir">RayPay</a> secure payment gateway for Contact Form 7
Author: Saminray
Author URI: https://saminray.com
Version: 1.0
Author URI: http://raypay.com/
*/

if (!function_exists('IR_CF7_relative_time'))
{
	function IR_CF7_relative_time($ptime)
	{
		date_default_timezone_set("Asia/Tehran");
		$etime = time() - $ptime;
		if ($etime < 1) {
			return '0 ثانیه';
		}
		$a = array(12 * 30 * 24 * 60 * 60 => 'سال',
			30 * 24 * 60 * 60 => 'ماه',
			24 * 60 * 60 => 'روز',
			60 * 60 => 'ساعت',
			60 => 'دقیقه',
			1 => 'ثانیه'
		);
		foreach ($a as $secs => $str) {
			$d = $etime / $secs;
			if ($d >= 1) {
				$r = round($d);
				return $r . ' ' . $str . ($r > 1 ? ' ' : '');
			}
		}
	}
}

function result_payment_raypay($atts)
{
	global $wpdb;
	$Theme_Message = get_option('cf7raypay_theme_message', '');
	$theme_error_message = get_option('cf7raypay_theme_error_message', '');
    $options = get_option('cf7yp_options');
    foreach ($options as $k => $v)
    {
        $value[$k] = $v;
    }


	if (isset($_POST))
	{
        $verify_endpoint = 'https://api.raypay.ir/raypay/api/v1/Payment/verify';
        $headers = array(
            'Content-Type' => 'application/json',
        );

        $args = array(
            'body' => json_encode($_POST),
            'headers' => $headers,
            'timeout' => 15,
        );


        $response =  wp_remote_post($verify_endpoint, $args);
        $http_status = wp_remote_retrieve_response_code($response);
        $result = wp_remote_retrieve_body($response);
        $result = json_decode($result);

		if (isset($result->Data) && $result->Data->Status == 1)
		{
            $verify_invoice_id = $result->Data->InvoiceID;
			$wpdb->update($wpdb->prefix.'cf7raypay_transaction', array('status' => 'success', 'transid' => $verify_invoice_id), array('transid' => $verify_invoice_id), array('%s', '%s'), array('%d'));
			return CreateMessage_cf7("", "", '<b style="color:'.$value['sucess_color'].';">'.'<br>'.stripslashes(str_replace('[invoice_id]', $verify_invoice_id, $Theme_Message)).'<b/>');
		}
		else
		{
            $verify_invoice_id = $result->Data->InvoiceID;
			$wpdb->update($wpdb->prefix . 'cf7raypay_transaction', array('status' => 'error'), array('transid' => $verify_invoice_id), array('%s'), array('%d'));
			return CreateMessage_cf7("", "", '<b style="color:'.$value['error_color'].';">'.'<br>'.stripslashes(str_replace('[invoice_id]', $verify_invoice_id, $theme_error_message)).'<b/>');
		}
	}
	else
	{
		$message = 'Unexpected Error';
		$wpdb->update($wpdb->prefix . 'cf7raypay_transaction', array('status' => 'cancel'), array('transid' => $_POST['respmsg']), array('%s'), array('%d'));
		return CreateMessage_cf7("", "", '<b style="color:'.$value['error_color'].';">'.$message.'<br>'.stripslashes(str_replace('[invoice_id]', $_POST['respmsg'], $theme_error_message)).'<b/>');
	}
}
add_shortcode('result_payment_raypay', 'result_payment_raypay');

if (!function_exists('CreateMessage_cf7'))
{
	function CreateMessage_cf7($title, $body, $endstr = "")
	{
		if ($endstr != "") return $endstr;
		return '<div style="border:#CCC 1px solid; width:90%;"> ' . $title . '<br />' . $body . '</div>';
	}
}

if (!function_exists('CreatePage_cf7'))
{
	function CreatePage_cf7($title, $body)
	{
		return '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><title>' . $title . '</title></head>
		<body class="vipbody"><div class="mrbox2" > <h3><span>' . $title . '</span></h3> ' . $body . ' </div></body></html>';
	}
}

$dir = plugin_dir_path(__FILE__);
register_activation_hook(__FILE__, "cf7raypay_activate");
register_deactivation_hook(__FILE__, "cf7raypay_deactivate");

function cf7raypay_activate()
{
	global $wpdb;
	$table_name = $wpdb->prefix . "cf7raypay_transaction";
	if ($wpdb->get_var("show tables like '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
			id mediumint(11) NOT NULL AUTO_INCREMENT,
			idform bigint(11) DEFAULT '0' NOT NULL,
			transid VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			gateway VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			cost bigint(11) DEFAULT '0' NOT NULL,
			created_at bigint(11) DEFAULT '0' NOT NULL,
			email VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci  NULL,
			description VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			user_mobile VARCHAR(11) CHARACTER SET utf8 COLLATE utf8_persian_ci  NULL,
			status VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			PRIMARY KEY id (id)
		);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	function wp_config_put($slash = '')
	{
		$config = file_get_contents(ABSPATH . "wp-config.php");
		$config = preg_replace("/^([\r\n\t ]*)(\<\?)(php)?/i", "<?php define('WPCF7_LOAD_JS', false);", $config);
		file_put_contents(ABSPATH . $slash . "wp-config.php", $config);
	}

	if (file_exists(ABSPATH . "wp-config.php") && is_writable(ABSPATH . "wp-config.php")) {
		wp_config_put();
	} else if (file_exists(dirname(ABSPATH) . "/wp-config.php") && is_writable(dirname(ABSPATH) . "/wp-config.php")) {
		wp_config_put('/');
	} else {
		?>
		<div class="error">
			<p><?php _e('wp-config.php is not writable, please make wp-config.php writable - set it to 0777 temporarily, then set back to its original setting after this plugin has been activated.', 'raypay_payment_for_cf7'); ?></p>
		</div>
		<?php
		exit;
	}
	
	$cf7raypay_options = array(
		'raypay_user_id' => '',
		'raypay_marketing_id' => '',
        'sandbox' => '1',
		'return' => '',
		'error_color'=>'#f44336',
		'sucess_color' => '#8BC34A',
	);
	add_option("cf7raypay_options", $cf7raypay_options);
}

function cf7raypay_deactivate()
{
	function wp_config_delete($slash = '')
	{
		$config = file_get_contents(ABSPATH . "wp-config.php");
		$config = preg_replace("/( ?)(define)( ?)(\()( ?)(['\"])WPCF7_LOAD_JS(['\"])( ?)(,)( ?)(0|1|true|false)( ?)(\))( ?);/i", "", $config);
		file_put_contents(ABSPATH . $slash . "wp-config.php", $config);
	}

	if (file_exists(ABSPATH . "wp-config.php") && is_writable(ABSPATH . "wp-config.php")) {
		wp_config_delete();
	} else if (file_exists(dirname(ABSPATH) . "/wp-config.php") && is_writable(dirname(ABSPATH) . "/wp-config.php")) {
		wp_config_delete('/');
	} else if (file_exists(ABSPATH . "wp-config.php") && !is_writable(ABSPATH . "wp-config.php")) {
		?>
		<div class="error">
			<p><?php _e('wp-config.php is not writable, please make wp-config.php writable - set it to 0777 temporarily, then set back to its original setting after this plugin has been deactivated.', 'raypay_payment_for_cf7'); ?></p>
		</div>
		<button onclick="goBack()">Go Back and try again</button>
		<script>
			function goBack() {
				window.history.back();
			}
		</script>
		<?php
		exit;
	} else if (file_exists(dirname(ABSPATH) . "/wp-config.php") && !is_writable(dirname(ABSPATH) . "/wp-config.php")) {
		?>
		<div class="error">
			<p><?php _e('wp-config.php is not writable, please make wp-config.php writable - set it to 0777 temporarily, then set back to its original setting after this plugin has been deactivated.', 'raypay_payment_for_cf7'); ?></p>
		</div>
		<button onclick="goBack()">Go Back and try again</button>
		<script>
			function goBack() {
				window.history.back();
			}
		</script>
		<?php
		exit;
	} else {
		?>
		<div class="error">
			<p><?php _e('wp-config.php is not writable, please make wp-config.php writable - set it to 0777 temporarily, then set back to its original setting after this plugin has been deactivated.', 'raypay_payment_for_cf7'); ?></p>
		</div>
		<button onclick="goBack()">Go Back and try again</button>
		<script>
			function goBack() {
				window.history.back();
			}
		</script>
		<?php
		exit;
	}
	delete_option("cf7raypay_options");
	delete_option("cf7raypay_my_plugin_notice_shown");

}

function cf7raypay_my_plugin_admin_notices()
{
	if (!get_option('cf7raypay_my_plugin_notice_shown'))
	{
		echo "<div class='updated'><p><a href='admin.php?page=cf7raypay_admin_table'>برای تنظیم اطلاعات درگاه  کلیک کنید</a>.</p></div>";
		update_option("cf7raypay_my_plugin_notice_shown", "true");
	}
}
add_action('admin_notices', 'cf7raypay_my_plugin_admin_notices');

include_once(ABSPATH . 'wp-admin/includes/plugin.php');
if (is_plugin_active('contact-form-7/wp-contact-form-7.php'))
{
	function cf7raypay_admin_menu()
	{
		$addnew = add_submenu_page('wpcf7', __('تنظیمات رای پی', 'raypay_payment_for_cf7'), __('تنظیمات رای پی', 'raypay_payment_for_cf7'), 'wpcf7_edit_contact_forms', 'cf7raypay_admin_table', 'cf7raypay_admin_table');
		$addnew = add_submenu_page('wpcf7', __('لیست تراکنش ها', 'raypay_payment_for_cf7'), __('لیست تراکنش ها', 'raypay_payment_for_cf7'), 'wpcf7_edit_contact_forms', 'cf7raypay_admin_list_trans', 'cf7raypay_admin_list_trans');
	}
	add_action('admin_menu', 'cf7raypay_admin_menu', 20);

	function cf7raypay_after_send_mail($cf7)
	{
		global $wpdb;
		global $postid;
		$postid = $cf7->id();
		$enable = get_post_meta($postid, "_cf7raypay_enable", true);
		$email = get_post_meta($postid, "_cf7raypay_email", true);
		if ($enable == "1") {
			if ($email == "2") {
				include_once ('redirect.php');
			}
		}
	}
	add_action('wpcf7_mail_sent', 'cf7raypay_after_send_mail');

	function cf7raypay_editor_panels($panels)
	{
		$new_page = array(
			'PricePay' => array(
				'title' => __('اطلاعات پرداخت', 'raypay_payment_for_cf7'),
				'callback' => 'cf7raypay_admin_after_additional_settings'
			)
		);
		$panels = array_merge($panels, $new_page);
		return $panels;
	}
	add_filter('wpcf7_editor_panels', 'cf7raypay_editor_panels');

	function cf7raypay_admin_after_additional_settings($cf7)
	{
		$post_id = sanitize_text_field($_GET['post']);
		$enable = get_post_meta($post_id, "_cf7raypay_enable", true);
		$price = get_post_meta($post_id, "_cf7raypay_price", true);
		$email = get_post_meta($post_id, "_cf7raypay_email", true);
		$user_mobile = get_post_meta($post_id, "_cf7raypay_mobile", true);
		$description = get_post_meta($post_id, "_cf7raypay_description", true);

		if ($enable == "1") {
			$checked = "CHECKED";
		} else {
			$checked = "";
		}

		if ($email == "1") {
			$before = "SELECTED";
			$after = "";
		} elseif ($email == "2") {
			$after = "SELECTED";
			$before = "";
		} else {
			$before = "";
			$after = "";
		}

		$admin_table_output = "";
		$admin_table_output .= "<form>";
		$admin_table_output .= "<div id='additional_settings-sortables' class='meta-box-sortables ui-sortable'><div id='additionalsettingsdiv' class='postbox'>";
		$admin_table_output .= "<div class='handlediv' title='Click to toggle'><br></div><h3 class='hndle ui-sortable-handle'> <span>اطلاعات پرداخت برای فرم</span></h3>";
		$admin_table_output .= "<div class='inside'>";
		$admin_table_output .= "<div class='mail-field'>";
		$admin_table_output .= "<input name='enable' id='cf71' value='1' type='checkbox' $checked>";
		$admin_table_output .= "<label for='cf71'>فعال سازی امکان پرداخت آنلاین</label>";
		$admin_table_output .= "</div>";
		$admin_table_output .= "<table>";
		$admin_table_output .= "<tr><td>مبلغ: </td><td><input type='text' name='price' style='text-align:left;direction:ltr;' value='$price'></td><td></td></tr>";
		$admin_table_output .= "</table>";
		$admin_table_output .= "<br> برای اتصال به درگاه پرداخت میتوانید از نام فیلدهای زیر استفاده نمایید ";
		$admin_table_output .= "<br />
		<span style='color:#F00;'>
		user_email نام فیلد دریافت ایمیل کاربر بایستی user_email انتخاب شود.
		<br />
		description نام فیلد  توضیحات پرداخت بایستی description انتخاب شود.
		<br />
		user_mobile نام فیلد  موبایل بایستی user_mobile انتخاب شود.
		<br />
        user_name نام فیلد  نام کاربر بایستی user_name انتخاب شود.
	    <br />
		user_price اگر کادر مبلغ در بالا خالی باشد می توانید به کاربر اجازه دهید مبلغ را خودش انتخاب نماید . کادر متنی با نام user_price ایجاد نمایید
		<br/>
		مانند [text* user_price]
		</span>	";
		$admin_table_output .= "<input type='hidden' name='email' value='2'>";
		$admin_table_output .= "<input type='hidden' name='post' value='$post_id'>";
		$admin_table_output .= "</td></tr></table></form>";
		$admin_table_output .= "</div>";
		$admin_table_output .= "</div>";
		$admin_table_output .= "</div>";
		echo $admin_table_output;

	}
	add_action('wpcf7_admin_after_additional_settings', 'cf7raypay_admin_after_additional_settings');

	function cf7raypay_save_contact_form($cf7)
	{
		$post_id = sanitize_text_field($_POST['post']);
		if (!empty($_POST['enable'])) {
			$enable = sanitize_text_field($_POST['enable']);
			update_post_meta($post_id, "_cf7raypay_enable", $enable);
		} else {
			update_post_meta($post_id, "_cf7raypay_enable", 0);
		}
		/*$name = sanitize_text_field($_POST['name']);
		update_post_meta($post_id, "_cf7raypay_name", $name);
		*/
		$price = sanitize_text_field($_POST['price']);
		update_post_meta($post_id, "_cf7raypay_price", $price);
		/*$id = sanitize_text_field($_POST['id']);
		update_post_meta($post_id, "_cf7raypay_id", $id);
		*/
		$email = sanitize_text_field($_POST['email']);
		update_post_meta($post_id, "_cf7raypay_email", $email);
	}
	add_action('wpcf7_save_contact_form', 'cf7raypay_save_contact_form');

	function cf7raypay_admin_list_trans()
	{
		if (!current_user_can("manage_options")) {
			wp_die(__("You do not have sufficient permissions to access this page."));
		}
		global $wpdb;
		$pagenum = isset($_GET['pagenum']) ? absint($_GET['pagenum']) : 1;
		$limit = 6;
		$offset = ($pagenum - 1) * $limit;
		$table_name = $wpdb->prefix . "cf7raypay_transaction";
		$transactions = $wpdb->get_results("SELECT * FROM $table_name where (status NOT like 'none' or 1) ORDER BY $table_name.id DESC LIMIT $offset, $limit", ARRAY_A);
		$total = $wpdb->get_var("SELECT COUNT($table_name.id) FROM $table_name where (status NOT like 'none' or 1) ");
		$num_of_pages = ceil($total / $limit);
		$cntx = 0;
		echo '<div class="wrap">
		<h2>تراکنش فرم ها</h2>
		<table class="widefat post fixed" cellspacing="0">
			<thead>
				<tr>
					<th scope="col" id="name" width="15%" class="manage-column" style="">نام فرم</th>
					<th scope="col" id="name" width="" class="manage-column" style="">تاريخ</th>
					<th scope="col" id="name" width="" class="manage-column" style="">ایمیل</th>
					<th scope="col" id="name" width="15%" class="manage-column" style="">مبلغ</th>
					<th scope="col" id="name" width="15%" class="manage-column" style="">کد تراکنش</th>
					<th scope="col" id="name" width="13%" class="manage-column" style="">وضعیت</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th scope="col" id="name" width="15%" class="manage-column" style="">نام فرم</th>
					<th scope="col" id="name" width="" class="manage-column" style="">تاريخ</th>
					<th scope="col" id="name" width="" class="manage-column" style="">ایمیل</th>
					<th scope="col" id="name" width="15%" class="manage-column" style="">مبلغ</th>
					<th scope="col" id="name" width="15%" class="manage-column" style="">کد تراکنش</th>
					<th scope="col" id="name" width="13%" class="manage-column" style="">وضعیت</th>
				</tr>
			</tfoot>
			<tbody>';
		if (count($transactions) == 0) {
			echo '<tr class="alternate author-self status-publish iedit" valign="top">
					<td class="" colspan="6">هيج تراکنشی وجود ندارد.</td>
				</tr>';
		} else {
			foreach ($transactions as $transaction) {
				echo '<tr class="alternate author-self status-publish iedit" valign="top">
					<td class="">' . get_the_title($transaction['idform']) . '</td>';
				echo '<td class="">' . strftime("%a, %B %e, %Y %r", $transaction['created_at']);
				echo '<br />(';
				echo IR_CF7_relative_time($transaction["created_at"]);
				echo ' قبل)</td>';
				echo '<td class="">' . $transaction['email'] . '</td>';
				echo '<td class="">' . $transaction['cost'] . '</td>';
				echo '<td class="">' . $transaction['transid'] . '</td>';
				echo '<td class="">';
				if ($transaction['status'] == "success") {
					echo '<b style="color:#0C9F55">موفقیت آمیز</b>';
				} else {
					echo '<b style="color:#f00">انجام نشده</b>';
				}
				echo '</td></tr>';
			}
		}
		echo '</tbody></table><br>';
		$page_links = paginate_links(array(
			'base' => add_query_arg('pagenum', '%#%'),
			'format' => '',
			'prev_text' => __('&laquo;', 'aag'),
			'next_text' => __('&raquo;', 'aag'),
			'total' => $num_of_pages,
			'current' => $pagenum
		));
		if ($page_links) {
			echo '<center><div class="tablenav"><div class="tablenav-pages"  style="float:none; margin: 1em 0">' . $page_links . '</div></div></center>';
		}
		echo '<br><hr></div>';
	}

	function cf7raypay_admin_table()
	{
		global $wpdb;
        $options = get_option('cf7raypay_options');
		if (!current_user_can("manage_options")) wp_die(__("You do not have sufficient permissions to access this page."));
		echo '<form method="post" action=' . $_SERVER["REQUEST_URI"] . ' enctype="multipart/form-data">';
		if (isset($_POST['update'])) {
			$options['raypay_user_id'] = sanitize_text_field($_POST['raypay_user_id']);
			$options['raypay_marketing_id'] = sanitize_text_field($_POST['raypay_marketing_id']);
            $options['sandbox']         = ( $_POST['sandbox'] === 'yes' ) ? 1 : 0;
			$options['return'] = sanitize_text_field($_POST['return']);
			$options['sucess_color'] = sanitize_text_field($_POST['sucess_color']);
			$options['error_color'] = sanitize_text_field($_POST['error_color']);
			update_option("cf7raypay_options", $options);
			update_option('cf7raypay_theme_message', wp_filter_post_kses($_POST['theme_message']));
			update_option('cf7raypay_theme_error_message', wp_filter_post_kses($_POST['theme_error_message']));
			echo "<br /><div class='updated'><p><strong>";
			_e("Settings Updated.");
			echo "</strong></p></div>";
		}
		$options = get_option('cf7raypay_options');
		foreach ($options as $k => $v) {
			$value[$k] = $v;
		}
        $checked         = ($options['sandbox'] == 1) ? 'checked' : '';
    	$theme_message = get_option('cf7raypay_theme_message', '');
		$theme_error_message = get_option('cf7raypay_theme_error_message', '');
		echo "<div class='wrap'><h2>Contact Form 7 - Gateway Settings</h2></div><br /><table width='90%'><tr><td>";
		echo '<div style="background-color:#333333;padding:8px;color:#eee;font-size:12pt;font-weight:bold;">
		&nbsp; پرداخت آنلاین برای فرم های Contact Form 7
		</div><div style="background-color:#fff;border: 1px solid #E5E5E5;padding:5px;"><br />
		<span style="color:#2392EC;">با استفاده از این قسمت میتوانید اطلاعات مربوط به درگاه  خود را تکمیل نمایید 
		<br>
		در بخش ایجاد فرم جدید می توانید براساس نام فیلد های زیر فرم را برای اتصال به درگاه پرداخت آماده کنید
		<br>
		user_email : برای دریافت ایمیل کاربر   
		<br>
		description : برای در یافت توضیحات خرید استفاده شود و الزامی شود  
		<br>
		user_mobile : برای دریافت موبایل کاربر   
		<br>
		user_name : برای دریافت نام کاربر   
		<br>
		user_price : جهت دریافت مبلغ از کاربر
		<br>
		برای نمونه : [text user_price]
		<br>
		برای مهم واجباری کردن* قرار دهید : [text* user_price]
		</span>
		<br/><br/><br/>
		<span style="color:#F68620;">
		لینک بازگشت از تراکنش بایستی به یکی از برگه های سایت باشد 
		<br>
		در این برگه بایستی از شورت کد زیر استفاده شود
		<br>
		[result_payment_raypay]   
		<br>
		<br/><br/><br/>
		حتما برررسی نمایید کد زیر در فایل wp-config.php وجود داشته باشد. که اگر نبود خودتان اضافه نمایید.
		<br>
		<pre style="direction: ltr;">define("WPCF7_LOAD_JS",false);</pre>
		<br/>
		</div><br /><br />
		<div style="background-color:#333333;padding:8px;color:#eee;font-size:12pt;font-weight:bold;">
		&nbsp; اطلاعات درگاه پرداخت
		</div>
		<div style="background-color:#fff;border: 1px solid #E5E5E5;padding:20px;">
		<hr>	
		<table>
		<tr>
			<td>شناسه کاربری:</td><td><input type="text" style="width:450px;text-align:left;direction:ltr;" name="raypay_user_id" value="' . $value['raypay_user_id'] . '">الزامی</td>
		</tr>
		<tr>
			<td>شناسه کسب و کار:</td><td><input type="text" style="width:450px;text-align:left;direction:ltr;" name="raypay_marketing_id" value="' . $value['raypay_marketing_id'] . '">الزامی</td>
		</tr>
		 <tr>
            <td>
                فعالسازی SandBox
            </td>
            <td>
               <input type="checkbox" name="sandbox" id="sandbox" value="yes"   ' . $checked . '   />
            </td>
        </tr>
		</table> 
		<hr>
		<table> 
		<tr>
			<td>لینک بازگشت از تراکنش :</td>
			<td><input type="text" name="return" style="width:450px;text-align:left;direction:ltr;" value="' . $value['return'] . '">
			الزامی
			<br />
			فقط  عنوان  برگه را قرار دهید مانند  payment
			<br />
			حتما باید یک برگه ایجادکنید
			و کد [result_payment_raypay]  را در ان قرار دهید 
			<br /><br />
			</td>
			<td></td>
		</tr>
		<tr>
			<td>قالب تراکنش موفق :</td>
			<td>
			<textarea name="theme_message" style="width:450px;text-align:left;direction:ltr;">' . $theme_message . '</textarea>
			<br/>
			متنی که میخواهید در هنگام موفقیت آمیز بودن تراکنش نشان دهید
			<br/>
			<b>از شورتکد [invoice_id] برای نمایش شناسه ارجاع بانکی رای پی در قالب های نمایشی استفاده کنید</b>
			</td>
			<td></td>
		</tr>
		<tr><td></td></tr>
		<tr>
			<td>قالب تراکنش ناموفق :</td>
			<td>
			<textarea name="theme_error_message" style="width:450px;text-align:left;direction:ltr;">' . $theme_error_message . '</textarea>
			<br/>
			متنی که میخواهید در هنگام موفقیت آمیز نبودن تراکنش نشان دهید
			<br/>
			<b>از شورتکد [invoice_id] برای نمایش شناسه ارجاع بانکی رای پی در قالب های نمایشی استفاده کنید</b>
			</td>
			<td></td>
		</tr>
		<tr>
		<td>رنگ متن موفقیت آمیز بودن تراکنش :  </td>
			<td>
			<input type="text" name="sucess_color" style="width:150px;text-align:left;direction:ltr;color:'.$value['sucess_color'].'" value="' . $value['sucess_color'] . '">
			مانند :	#8BC34A	یا نام رنگ   green
		</td>
		</tr>
		<tr>
		<td>رنگ متن موفقیت آمیز نبودن تراکنش :  </td>
			<td>
			<input type="text" name="error_color" style="width:150px;text-align:left;direction:ltr;color:'.$value['error_color'].'" value="' . $value['error_color'] . '">
			مانند : #f44336 یا نام رنگ  red
			</td>
		</tr>
		<tr><td></td></tr>
		<tr>
		<td colspan="3">
		<input type="submit" name="btn2" class="button-primary" style="font-size: 17px;line-height: 28px;height: 32px;float: right;" value="ذخیره تنظیمات">
		</td>
		</tr>
		</table>
		</div>
		<br /><br /><br />		
		<input type="hidden" name="update">
		</form>		
		</td></tr></table>';
	}
}
else
{
	function cf7raypay_my_admin_notice()
	{
		echo '<div class="error"><p>' . _e('<b> افزونه درگاه بانکی برای افزونه Contact Form 7 :</b> Contact Form 7 باید فعال باشد ', 'raypay_payment_for_cf7') . '</p></div>';
	}
	add_action('admin_notices', 'cf7raypay_my_admin_notice');
}


?>