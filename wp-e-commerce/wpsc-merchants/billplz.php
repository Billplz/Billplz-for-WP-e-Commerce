<?php
/**
 * Billplz Wordpress e-Commerce Plugin
 *
 * @package Payment Method
 * @author Wan Zulkarnain <sales@wanzul-hosting.com>
 * @version 3.0.0
 *
 */
$nzshpcrt_gateways[$num] = array(
	'name' => 'Billplz Malaysia Online Payment Gateway',
	'display_name' => 'Billplz Malaysia Online Payment Gateway',
	'internalname' => 'billplz',
	'function' => 'gateway_billplz',
	'form' => 'form_billplz',
	'submit_function' => 'submit_billplz'
);
/**
 * Initialize the order if Billplz payment method was selected
 * 
 * @global object $wpdb
 * @global object $wp_object_cache
 * @param type $seperator
 * @param int $sessionid
 * @return void
 */
function gateway_billplz($seperator, $sessionid)
{
	global $wpdb, $wp_object_cache;
	$ob_cache            = $wp_object_cache->cache;
	$cur                 = $ob_cache['options']['alloptions'];
	$cur_type            = $cur['currency_type'];
	//$cur_sql = "SELECT * FROM `".WPSC_TABLE_CURRENCY_LIST."` WHERE `id`= ".$cur_type." LIMIT 1";
	//$cur_res = $wpdb->get_results($cur_sql,ARRAY_A) ;
	//$cur_code = $cur_res[0]['code'];	
	//$cur_code = (strnatcasecmp($cur_code,"myr")=="0")? "rm" : strtolower($cur_code);
	$purchase_log_sql    = "SELECT * FROM `" . WPSC_TABLE_PURCHASE_LOGS . "` WHERE `sessionid`= " . $sessionid . " LIMIT 1";
	$purchase_log        = $wpdb->get_results($purchase_log_sql, ARRAY_A);
	$cart_sql            = "SELECT * FROM `" . WPSC_TABLE_CART_CONTENTS . "` WHERE `purchaseid`='" . $purchase_log[0]['id'] . "'";
	$cart                = $wpdb->get_results($cart_sql, ARRAY_A);
	$data['merchant_id'] = get_option('billplz_api_key');
	$data['verify_key']  = get_option('billplz_collection_id');
	$data['host']        = get_option('billplz_mode') == 'production' ? 'https://www.billplz.com/api/v3/bills/' : 'https://billplz-staging.herokuapp.com/api/v3/bills/';
	$data['returnurl']   = get_option('transact_url');
	$data['callbackurl'] = get_option('transact_url');
	//User details
	if ($_POST['collected_data'][get_option('billplz_form_first_name')] != '') {
		$data['f_name'] = $_POST['collected_data'][get_option('billplz_form_first_name')];
	}
	if ($_POST['collected_data'][get_option('billplz_form_last_name')] != "") {
		$data['s_name'] = $_POST['collected_data'][get_option('billplz_form_last_name')];
	}
	if ($_POST['collected_data'][get_option('billplz_form_address')] != '') {
		$data['street'] = str_replace("\n", ', ', $_POST['collected_data'][get_option('billplz_form_address')]);
	}
	if ($_POST['collected_data'][get_option('billplz_form_city')] != '') {
		$data['city'] = $_POST['collected_data'][get_option('billplz_form_city')];
	}
	if (preg_match("/^[a-zA-Z]{2}$/", $_SESSION['selected_country'])) {
		$data['country'] = $_SESSION['selected_country'];
	}
	//Get user email
	$email_data = $wpdb->get_results("SELECT `id`,`type` FROM `" . WPSC_TABLE_CHECKOUT_FORMS . "` WHERE `type` IN ('email') AND `active` = '1'", ARRAY_A);
	foreach ((array) $email_data as $email) {
		$data['email'] = $_POST['collected_data'][$email['id']];
	}
	if (($_POST['collected_data'][get_option('email_form_field')] != null) && ($data['email'] == null)) {
		$data['email'] = $_POST['collected_data'][get_option('email_form_field')];
	}
	//collect item(s) in cart information
	$prod_sql  = "SELECT * FROM `" . WPSC_TABLE_CART_CONTENTS . "` WHERE `purchaseid`='" . $cart[0]['purchaseid'] . "'";
	$prod_res  = $wpdb->get_results($prod_sql, ARRAY_A);
	$prod_size = sizeof($prod_res);
	for ($i = 0; $i < $prod_size; $i++) {
		$p_name[] = $prod_res[$i]['name'] . " x " . $prod_res[$i]['quantity'];
	}
	if ($p_name) {
		$p_desc = implode("\n", $p_name);
	}
	$ship_sql  = "SELECT form_id,value FROM `" . WPSC_TABLE_SUBMITED_FORM_DATA . "` WHERE `log_id`='" . $cart[0]['purchaseid'] . "' ";
	$ship_res  = $wpdb->get_results($ship_sql, ARRAY_A);
	$size_ship = sizeof($ship_res);
	for ($k = 0; $k < $size_ship; $k++) {
		$form_id = $ship_res[$k]['form_id'];
		switch ($form_id) {
			// ------------------- billing information -------------------
			//Billing first name
			case "2":
				$b_name = $ship_res[$k]['value'];
				break;
			//Billing last name
			case "3":
				$b_name .= " " . $ship_res[$k]['value'];
				break;
			//Billing contact
			case "18":
				$b_fon = $ship_res[$k]['value'];
				break;
			//Billing address
			case "4":
				$b_address = $ship_res[$k]['value'];
				break;
			//Billing city
			case "5":
				$b_city = $ship_res[$k]['value'];
				break;
			//Billing state
			case "6":
				$b_state = $ship_res[$k]['value'];
				break;
			//Billing country
			case "7":
				$b_county = $ship_res[$k]['value'];
				break;
			//Billing postcode
			case "8":
				$b_postcode = $ship_res[$k]['value'];
				break;
			// -------------------  shipping information ------------------- 
			//
			case "11":
				$s_name                          = (strlen(preg_replace('/\s+/', '', $ship_res[$k]['value'])) != 0) ? $ship_res[$k]['value'] : $ship_res[0]['value'];
				$_SESSION['shippingSameBilling'] = 1;
				break;
			case "12":
				$s_name2 = (strlen(preg_replace('/\s+/', '', $ship_res[$k]['value'])) != 0) ? $ship_res[$k]['value'] : $ship_res[1]['value'];
				break;
			case "13":
				$s_address = (strlen(preg_replace('/\s+/', '', $ship_res[$k]['value'])) != 0) ? $ship_res[$k]['value'] : $ship_res[2]['value'];
				break;
			case "14":
				$s_address2 = (strlen(preg_replace('/\s+/', '', $ship_res[$k]['value'])) != 0) ? $ship_res[$k]['value'] : $ship_res[3]['value'];
				break;
			case "15":
				$s_address3 = (strlen(preg_replace('/\s+/', '', $ship_res[$k]['value'])) != 0) ? $ship_res[$k]['value'] : $ship_res[4]['value'];
				break;
			case "16":
				$s_address4 = (strlen(preg_replace('/\s+/', '', $ship_res[$k]['value'])) != 0) ? $ship_res[$k]['value'] : $ship_res[5]['value'];
				break;
			default:
				echo "";
		}
	}
	//Construct information about buying    
	//$desc .= "------------------------\nProduct(s) Information\n------------------------\n";
	//$desc .= $p_desc . "\n";
	//$desc .= "------------------------\nShipping Information\n------------------------\n";
	//$desc .= $s_name . ' ' . $s_name2;
	//$desc .= "\n" . $s_address . "\n" . $s_address2 . "\n" . $s_address3 . "\n" . $s_address4;
	//$data['product_price'] = $total_price; //This data cannot be used in Billplz system
	//$data['amount'] = $purchase_log[0]['totalprice'];
	//$data['orderid'] = $purchase_log[0]['id'];	
	//$data['bill_mobile'] = $b_fon;			
	//$data['bill_name'] = $b_name;			
	//$data['bill_email'] = $data['email'];		
	//$data['bill_desc'] = $desc;
	//$data['currency'] = $cur_code;			
	//$data['country'] = "MY";				
	//$data['returnurl'] = $data['returnurl'];		
	//$data['vcode'] = md5($data['amount'] . $data['merchant_id'] . $data['orderid'] . $data['verify_key']); //Generate verfication code
	//number intelligence
	$custTel  = $b_fon;
	$custTel2 = substr($b_fon, 0, 1);
	if ($custTel2 == '+') {
		$custTel3 = substr($b_fon, 1, 1);
		if ($custTel3 != '6')
			$custTel = "+6" . $b_fon;
	} else if ($custTel2 == '6') {
	} else {
		if ($custTel != '')
			$custTel = "+6" . $b_fon;
	}
	//number intelligence
	//CURL
	$billplz_data = array(
		'amount' => $purchase_log[0]['totalprice'] * 100,
		'name' => $b_name,
		'email' => $data['email'],
		'collection_id' => $data['verify_key'],
		'mobile' => $custTel,
		'reference_1_label' => "ID",
		'reference_1' => $purchase_log[0]['id'],
		'deliver' => false,
		'description' => substr($p_desc, 0, 199),
		'redirect_url' => get_option('transact_url') . '?val=' . $purchase_log[0]['id'],
		'callback_url' => get_option('transact_url') . '?val=' . $purchase_log[0]['id']
	);
	for ($i = 0; $i < 2; $i++) {
		$process = curl_init($data['host']);
		curl_setopt($process, CURLOPT_HEADER, 0);
		curl_setopt($process, CURLOPT_USERPWD, $data['merchant_id'] . ":");
		curl_setopt($process, CURLOPT_TIMEOUT, 30);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($process, CURLOPT_POSTFIELDS, http_build_query($billplz_data));
		$return = curl_exec($process);
		curl_close($process);
		$arr = json_decode($return, true);
		if (isset($arr['url'])) {
			$billplz_url = $arr['url'];
			break;
		} else if ($i == 0) {
			$billplz_url = "#";
			unset($billplz_data['mobile']);
		}
	}
	//CURL
	//Create Form to post to Billplz Online Payment Gateway
	//$output= "<center><form method='get' action='$billplz_url'>\n";
	//$plugins_url = plugins_url();    
	//$output .= "<br><br>";
	//$output .= "<input type='image' src='$plugins_url/wp-e-commerce/images/billplz_logo.gif' name='submit'></form>";
	//$output .= "<br><input type='image' src='$plugins_url/wp-e-commerce/images/connect_billplz.gif' width='44' length='44'>";
	//$output .= "</center>";
	//flush all the form to the browser view
	//echo($output);
	header('Location: ' . $billplz_url);
	//if(get_option('billplz_debug') == 0) {
	//Auto submit javascript
	//    echo "<script language='javascript'type='text/javascript'>setTimeout(\"document.getElementById('billplz_form').submit()\",1500);</script>";
	//}
	exit();
}
/**
 * Received status about the order
 * 
 * @global object $wpdb
 */
function nzshpcrt_billplz_callback()
{
	global $wpdb;
	if (isset($_GET['billplz']) || isset($_POST['id'])) {
		$tranID  = isset($_GET['billplz']) ? implode($_GET['billplz']) : $_POST['id'];
		$host    = get_option('billplz_mode') == 'production' ? 'https://www.billplz.com/api/v3/bills/' : 'https://billplz-staging.herokuapp.com/api/v3/bills/';
		$process = curl_init($host . $tranID);
		curl_setopt($process, CURLOPT_HEADER, 0);
		curl_setopt($process, CURLOPT_USERPWD, get_option('billplz_api_key') . ":");
		curl_setopt($process, CURLOPT_TIMEOUT, 30);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		$return = curl_exec($process);
		curl_close($process);
		$arra     = json_decode($return, true);
		$booleanC = isset($_GET['val']) ? true : false;
		if ($_GET['val'] == $arra['reference_1'] && $booleanC) {
			$data               = $wpdb->get_row("SELECT * FROM `" . WPSC_TABLE_PURCHASE_LOGS . "` WHERE `id` = " . $arra['reference_1'] . "");
			$ship_res           = billplz_inline_classes_object_function::query_data($wpdb, $arra['reference_1']);
			$_POST['sessionid'] = $sessionid = $data->sessionid;
			$transid            = $tranID;
			$retStatus          = $arra['paid'];
			$url                = get_option('transact_url') . "?sessionid=" . $sessionid;
			if ($retStatus) {
				$data   = array(
					'processed' => 3,
					'transactid' => $transid,
					'date' => time()
				);
				$where  = array(
					'sessionid' => $sessionid
				);
				$format = array(
					'%d',
					'%s',
					'%s'
				);
				$wpdb->update(WPSC_TABLE_PURCHASE_LOGS, $data, $where, $format);
				transaction_results($sessionid, false, $transid);
				$bodyContent = "
                Billplz Plugin Auto-Sender\n\n
                Be inform that we have capture for payment : \n
                Order ID : " . $arra['reference_1'] . "\n
                Approval status : " . $arra['state'] . "\n
                Amount : " . 'RM' . ($arra['amount'] / 100) . "\n\n
                --------------------------------------------------------------\n
                Buyer Name : " . $ship_res[0]['value'] . ' ' . $ship_res[1]['value'] . "\n
                Buyer Phone : " . $ship_res[15]['value'] . "\n
                Buyer Email : " . $ship_res[7]['value'] . "\n
                Buyer Address : " . $ship_res[2]['value'] . ', ' . $ship_res[6]['value'] . ', ' . $ship_res[3]['value'] . ', ' . $ship_res[4]['value'] . "\n
                Shipping Name : " . $ship_res[8]['value'] . ' ' . $ship_res[9]['value'] . "\n
                Shipping Address : " . $ship_res[10]['value'] . ', ' . $ship_res[14]['value'] . ', ' . $ship_res[11]['value'] . ', ' . $ship_res[12]['value'] . "\n                
            ";
				wp_mail(get_option('admin_email'), 'Accepted Payment Notification | Billplz', $bodyContent);
			} else if (!$retStatus) {
				return false;
				/*$data = array(
				'processed'  => 2,
				'transactid' => $transid,
				'date'       => time()
				);
				$where = array( 'sessionid' => $sessionid );
				$format = array( '%d', '%s', '%s' );
				$wpdb->update( WPSC_TABLE_PURCHASE_LOGS, $data, $where, $format );
				transaction_results($sessionid, false, $transid);*/
			} else {
				echo '<h1>There was an error during processing the information</h1>';
				echo '<p>Hacking attempt!!</p>';
				exit();
			}
			echo '<script>window.location.href = "' . $url . '"</script>';
		}
		//Either merchant missconfigure the merchantID or vcode
	}
}
//function nzshpcrt_billplz_results() {
//    if($arra['reference_1'] !='' && $_GET['val'] == '') {
//        $_GET['val'] = $arra['reference_1'];
//    }
//}
function submit_billplz()
{
	if ($_POST['billplz_api_key'] != null) {
		update_option('billplz_api_key', $_POST['billplz_api_key']);
	}
	if ($_POST['billplz_collection_id'] != null) {
		update_option('billplz_collection_id', $_POST['billplz_collection_id']);
	}
	if ($_POST['billplz_mode'] != null) {
		update_option('billplz_mode', $_POST['billplz_mode']);
	}
	if ($_POST['billplz_url'] != null) {
		update_option('billplz_url', $_POST['billplz_url']);
	}
	if ($_POST['billplz_debug'] != null) {
		update_option('billplz_debug', $_POST['billplz_debug']);
	}
	foreach ((array) $_POST['billplz_form'] as $form => $value) {
		update_option(('billplz_form_' . $form), $value);
	}
	return true;
}
function form_billplz()
{
	$select_currency[get_option('billplz_curcode')] = "selected='true'";
	$billplz_debug                                  = get_option('billplz_debug');
	$billplz_debug1                                 = "";
	$billplz_debug2                                 = "";
	$serverType1                                    = '';
	$serverType2                                    = '';
	if (get_option('billplz_mode') == 'staging') {
		$serverType1 = "checked='checked'";
	} elseif (get_option('billplz_mode') == 'production') {
		$serverType2 = "checked='checked'";
	}
	$output = "
            <tr>
              <td>API Key</td>
              <td><input type='text' size='40' value='" . get_option('billplz_api_key') . "' name='billplz_api_key' /></td>
            </tr>

            <tr>
              <td>Collection ID</td>
              <td><input type='text' size='40' value='" . get_option('billplz_collection_id') . "' name='billplz_collection_id' /></td>
            </tr>
			<tr>
              <td>Mode</td>
              <td>
				<input $serverType1 type='radio' name='billplz_mode' value='staging' id='billplz_mode_staging' /> <label for='billplz_mode_staging'>" . __('Staging (For testing)', 'wp-e-commerce') . "</label> &nbsp;
				<input $serverType2 type='radio' name='billplz_mode' value='production' id='billplz_mode_production' /> <label for='billplz_mode_production'>" . __('Production', 'wp-e-commerce') . "</label>
			  </td>
			</tr>
            <tr>
              <td>Return URL</td>
              <td><input type='text' size='40' value='" . get_option('transact_url') . "' name='billplz_return_url' readonly/></td>
            </tr>
            <tr>
              <td>Callback URL</td>
              <td><input type='text' size='40' value='" . get_option('transact_url') . "' name='billplz_callback_url' readonly/></td>
            </tr>

    ";
	return $output;
}
add_action('init', 'nzshpcrt_billplz_callback');
/**
 * Add billplz class to prevent name conflict
 * 
 */
class billplz_inline_classes_object_function
{
	/**
	 * Get the order cart details
	 * 
	 * @param object $wpdb
	 */
	static function query_data($wpdb, $orderId)
	{
		$cart_sql = "SELECT * FROM `" . WPSC_TABLE_CART_CONTENTS . "` WHERE `purchaseid`='" . $orderId . "'";
		$cart     = $wpdb->get_results($cart_sql, ARRAY_A);
		$ship_sql = "SELECT form_id,value FROM `" . WPSC_TABLE_SUBMITED_FORM_DATA . "` WHERE `log_id`='" . $cart[0]['purchaseid'] . "' ";
		return $wpdb->get_results($ship_sql, ARRAY_A);
	}
}
?>