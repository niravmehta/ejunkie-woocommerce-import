<?php
/**
 * E-Junkie to WooCommerce importer
 **/

set_time_limit(-1);
global $wpdb, $woocommerce;
$term_info = term_exists('completed', 'shop_order_status');
$term_id= $term_info['term_id'];
$tt_id = $term_info['term_taxonomy_id'];

echo "<h1>Copying E-Junkie data to WooCommerce</h1><hr/>";

// Prepare country / state list for later use
$_wc_countries = new WC_Countries();
$countries = $_wc_countries->countries;
$countries = array_flip($countries);
$states = $_wc_countries->states;

// Get the current list of users and their emails - to avoid duplicate issues
$wp_users = $wpdb->get_results('SELECT lower(user_email) as user_email, ID FROM `'.$wpdb->prefix.'users`', OBJECT_K);

// Find max user ID. We will increment it in our code such that we can do a bulk insert
$max_user_id_obj = $wpdb->get_row('SELECT 5 + MAX(ID) as max_user_id FROM `'.$wpdb->prefix.'users`');
$max_user_id = absint($max_user_id_obj->max_user_id);

// Import user accounts
$insert_every = 50;
$skip_insert = false;
$_users_records = array();
$_users_meta_records = array();
$email_to_wc_users = array();
$product_skus = array();

$_wp_users_query_start = 'INSERT INTO `'.$wpdb->prefix.'users` (ID, user_login, user_pass, user_nicename, user_email, user_registered, display_name) VALUES ';
$_wp_users_meta_query_start = 'INSERT INTO `'.$wpdb->prefix.'usermeta` (user_id, meta_key, meta_value) VALUES ';
$_wp_users_rows = $_wp_users_meta_rows = array();
$wp_users_meta = array();

// Only loading "Completed" orders...
$ej_users = $wpdb->get_results('SELECT Payment_Date, Processed_by_Ej, Transaction_ID, Payment_Processor, Ej_Internal_Txn_ID, Payment_Status, First_Name, Last_Name, lower(rtrim(ltrim(Payer_Email))) as Payer_Email, Billing_Info, Payer_Phone, Payer_IP, Passed_Custom_Param, Discount_Codes, Invoice, Affiliate_Email, Affiliate_Name, Affiliate_ID, Affiliate_Share, Currency, Item_Name, VariationsVariants, Item_Number, SKU, Quantity, Amount, Affiliate_Share_per_item, Download_Info, KeyCode_if_any, Buyer_Country FROM `ejunkie_transactions` WHERE Payment_Status = "Completed"', ARRAY_A);

// Fake record at the end to trigger db insertion once all records are done
$ej_users[] = array('id' => 'EOF');
foreach($ej_users as $user)
{	 
	if(sizeof($_wp_users_rows) >= $insert_every || $user['id'] == 'EOF')
	{
		//$wpdb->hide_errors();
		$affected = $wpdb->query($_wp_users_query_start.' '.implode(', ', $_wp_users_rows));
		echo "{$affected} Users inserted.<br/>";
		$affected = $wpdb->query($_wp_users_meta_query_start.' '.implode(', ', $_wp_users_meta_rows));
		echo "{$affected} User meta values inserted.<br/>";
		$_wp_users_rows = $_wp_users_meta_rows = array();
	}
	
	if ($user['id'] == 'EOF')
	{
		continue;
	}
	
	$user_meta = array();
	// Check existing user account if present
	if (isset($wp_users[ $user['Payer_Email'] ]))
	{
		$skip_insert = true;
		$user_id = $wp_users[ $user['Payer_Email'] ]->ID;
	}
	else
	{
		$skip_insert = false;
		$user_id = ++$max_user_id;
	}
	
	// user_login, user_pass, user_nicename, user_email, user_url, user_registered, user_activation_key, user_status, display_name
	$user_login = $user['Payer_Email'];
	$user_pass = wp_hash_password(substr($user['Payer_Email'], 0, 5)); // Passwords are first 5 characters of email
	$display_name = $user_nicename = trim(implode(' ', array_filter( array($user['First_Name'], $user['Last_Name'])) ));
	$user_email = $user['Payer_Email'];
	$user_registered = $user['Payment_Date'];
	
	if (!$skip_insert)
	{
		$_wp_users_rows[] = "( $user_id, '{$wpdb->escape($user_login)}', '{$wpdb->escape($user_pass)}', '{$wpdb->escape($user_nicename)}', '{$wpdb->escape($user_email)}', '{$wpdb->escape($user_registered)}', '{$wpdb->escape($display_name)}' )";
	}
	
	$user_meta['first_name'] = $user['First_Name'];
	$user_meta['last_name'] = $user['Last_Name'];
	$user_meta['nickname'] = $user['First_Name'];
	$user_meta['description'] = ' ';
	$user_meta['rich_editing'] = 'true';
	$user_meta['comment_shortcuts'] = 'false';
	$user_meta['admin_color'] = 'fresh';
	$user_meta['use_ssl'] = '0';
	$user_meta['show_admin_bar_front'] = 'false';
	$user_meta['wp_capabilities'] = 'a:1:{s:8:"customer";s:1:"1";}';
	$user_meta['wp_user_level'] = '0';
	$user_meta['dismissed_wp_pointers'] = 'wp330_toolbar,wp330_media_uploader,wp330_saving_widgets';
	$user_meta['billing_first_name'] = $user['First_Name'];
	$user_meta['billing_last_name'] = $user['Last_Name'];
	$user_meta['billing_company'] = $user['Billing_Info'];
	$user_meta['billing_address_1'] = '';
	$user_meta['billing_address_2'] = '';
	$user_meta['billing_city'] = '';
	$user_meta['billing_postcode'] = '';
	$user_meta['billing_country'] = ( $countries[ $user['Buyer_Country'] ] != '' ) ? $countries[ $user['Buyer_Country'] ] : $user['Buyer_Country'];
	$billing_state = '';
	/*
	if ($countries[ $user['Buyer_Country'] ] != '' && is_array($states[ $countries[ $user['Buyer_Country'] ] ]))
	{
		$matched_state = array_search($user['Buyer_State'], $states[ $countries[ $user['Buyer_Country'] ] ]);
		if ($matched_state !== false) 
		{
			$billing_state = $matched_state;
		}
	}
	*/
	$user_meta['billing_state'] = $billing_state;
	$user_meta['billing_email'] = $user_email;
	$user_meta['billing_phone'] = $user['Payer_Phone'];
	$user_meta['email_opted_out'] = 'false';
	
	if (!$skip_insert)
	{
		foreach ($user_meta as $key => $value)
		{
			$_wp_users_meta_rows[] = "( $user_id,  '{$wpdb->escape($key)}', '{$wpdb->escape($value)}')";	
		}
	}
	
	// Populate in the map for later use
	$email_to_wc_users[ $user_email ] = $user_id;
	//$email_to_wc_users[ strtolower( $user_meta['billing_email'] ) ] = $user_id;
	$wp_users_meta[$user_id] = $user_meta;
	
	// Populate SKUs
	if (isset($user['SKU']) && !array_key_exists($user['SKU'], $product_skus)) {
		$product_skus[$user['SKU']] = 1;
	} else if (isset($user['Item_Number']) && !array_key_exists($user['Item_Number'], $product_skus)) {
		$product_skus[$user['Item_Number']] = 1;
	}
}

// Map products
$product_skus = array_filter(array_unique(array_keys($product_skus)));
$product_ids = array();
$products_map = array();
if (count($product_skus) > 0) {
	$product_ids = $wpdb->get_results('SELECT post_id as ID, meta_value as SKU FROM `'.$wpdb->prefix.'postmeta` where meta_key="_sku" AND meta_value IN ("'.implode('", "', $product_skus).'")', OBJECT_K);
}
foreach ($product_ids as $row) {
	$product = new WC_Product( $row->ID );
	$variation = null;
	$item_meta = null;
	
	// handle variations
	if ( ( $parent_id = $product->get_parent() ) ) {
		$variation = new WC_Product_Variation( $product->id, $product->get_parent() );
		$item_meta = new WC_Order_Item_Meta();
		foreach ( $variation->get_variation_attributes() as $key => $value ) {
			$item_meta->add( esc_attr( str_replace( 'attribute_', '', $key ) ), $value );
		}
		$product = new WC_Product( $parent_id );
	}

	$products_map[$row->SKU] = array('product' => $product, 'variation' => $variation, 'item_meta' => $item_meta);
}			
echo "<hr/><h2>Loaded Products</h2>";

// Process Orders, Download access and serial keys

// Find max Order ID. We will increment it in our code such that we can do a bulk insert
$max_order_id_obj = $wpdb->get_row('SELECT 10 + MAX(ID) as max_order_id FROM `'.$wpdb->prefix.'posts`');
$start_order_id = $max_order_id = absint($max_order_id_obj->max_order_id);


// Order status term / taxonomy
$term_info = term_exists('completed', 'shop_order_status');
$term_id = $term_info['term_id'];
$tt_id = $term_info['term_taxonomy_id'];

$_wp_orders_query_start = 'INSERT INTO `'.$wpdb->prefix.'posts` ';
$_wp_orders_meta_query_start = 'INSERT INTO `'.$wpdb->prefix.'postmeta` (post_id, meta_key, meta_value) VALUES ';
$_wp_downloadables_query_start = 'INSERT INTO `'.$wpdb->prefix.'woocommerce_downloadable_product_permissions` (product_id, order_id, order_key, user_email, user_id, access_granted, access_expires) VALUES ';
//$_wp_serial_keys_query_start = 'INSERT INTO `'.$wpdb->prefix.'woocommerce_serial_key` (order_id, product_id, serial_key, valid_till) VALUES ';
$_wp_orders_rows = $_wp_orders_meta_rows = $_wp_downloadables_rows = $_wp_serial_keys_rows = array();

// Load transactions and products data
$ej_transactions = $ej_users;

echo "<hr/><h2>Processing transactions</h2>";

$last_trans = $order_data = $order_meta = array();
$skip_insert = false;

foreach ($ej_transactions as $trans)
{
	if ($trans['Payer_Email'] != $last_trans['Payer_Email'] || ($trans['Payer_Email'] == $last_trans['Payer_Email'] && !empty($trans['Transaction_ID']) && $trans['Transaction_ID'] != $last_trans['Transaction_ID']))
	{
		if (sizeof($order_data) > 0)
		{
			// Prepare earlier order for db insertion
			$_wp_orders_rows[] = "('" . implode( "','", $order_data ) . "')";
			
			$order_meta['_order_items'] = serialize($order_items);
			$order_meta['_order_total'] = rtrim(rtrim(number_format($order_total, 4, '.', ''), '0'), '.');
			foreach ($order_meta as $key => $value) {
				$_wp_orders_meta_rows[] = "( $order_id,  '{$wpdb->escape($key)}', '{$wpdb->escape($value)}')";
			}
			
			// Insert into tables
			if(sizeof($_wp_orders_rows) >= $insert_every || $trans['id'] == 'EOF')
			{
				//$wpdb->hide_errors();
				$affected = $wpdb->query($_wp_orders_query_start.' ('.implode(', ', array_keys($order_data)).') VALUES '.implode(', ', $_wp_orders_rows));
				echo "<br/>{$affected} Orders inserted.";
				$affected = $wpdb->query($_wp_orders_meta_query_start.' '.implode(', ', $_wp_orders_meta_rows));
				echo "<br/>{$affected} Order meta values inserted.";
				$affected = $wpdb->query($_wp_downloadables_query_start.' '.implode(', ', $_wp_downloadables_rows));
				echo "<br/>{$affected} Order downloadables inserted.";
				//$affected = $wpdb->query($_wp_serial_keys_query_start.' '.implode(', ', $_wp_serial_keys_rows));
				//echo "<br/>{$affected} Serial keys inserted.";
				$_wp_orders_rows = $_wp_orders_meta_rows = $_wp_downloadables_rows = $_wp_serial_keys_rows = array();
			}
		}		
		if ($trans['id'] == 'EOF')
		{
			continue;
		}
		
		// Now for the new order
		// Transform user id
		
		$user_id = ( isset($email_to_wc_users[ $trans['Payer_Email'] ]) ) ? $email_to_wc_users[ $trans['Payer_Email'] ] : 0;
		// Must find a matching user_id
		if (absint($user_id) == 0)
		{
			echo "<h4>Did not find a user account for {$trans['Payer_Email']}-{$user_id}-</h4>";
			continue;
		}
		$user_meta = $wp_users_meta[ $user_id ];
		$order_id = ++$max_order_id;
		$transaction_id = ($trans['Transaction_ID'] > 0) ? $trans['Transaction_ID'] : uniqid('admin'.$trans['Transaction_ID'].'-');
		$transaction_time = strtotime( $trans['Processed_by_Ej'] );
		if ($transaction_time < 0) 	// This happens for free products / items that admin marked as paid..
		{
			$transaction_time = strtotime( $trans['Payment_Date'] );
		}
		$transaction_time += 21600; // Move forward 6 hours * 3600 - MST to GMT conversion;
		$order_date = date('Y-m-d H:i:s', $transaction_time);
		$order_key = uniqid('order_');
		$order_items = array();
		$order_total = 0;
		$order_data = array(
			'ID'			=> $order_id,
			'post_author' 	=> 1,
			'post_date' 	=> $order_date,
			'post_date_gmt'	=> $order_date,
			'post_content'	=> '',
			'post_title' 	=> 'Order &ndash; '.date('F j, Y @ h:i A', $transaction_time),
			'post_excerpt' 	=> '',
			'post_status' 	=> 'publish',
			'ping_status'	=> 'closed',
			'post_type' 	=> 'shop_order',
			'guid' 			=> '',
			'post_password'	=> $order_key,	// Protects the post just in case
			'post_name'		=> 'order-'.$transaction_id,
			'post_modified' 	=> $order_date,
			'post_modified_gmt'	=> $order_date,
		);
		
		$order_meta = array(
			'_billing_first_name'	=> $user_meta['billing_first_name'],
			'_billing_last_name'	=> $user_meta['billing_last_name'],
			'_billing_company'	=> $user_meta['billing_company'],
			'_billing_address_1'	=> $user_meta['billing_address_1'],
			'_billing_address_2'	=> $user_meta['billing_address_2'],
			'_billing_city'	=> $user_meta['billing_city'],
			'_billing_postcode'	=> $user_meta['billing_postcode'],
			'_billing_country'	=> $user_meta['billing_country'],
			'_billing_state'	=> $user_meta['billing_state'],
			'_billing_email'	=> $user_meta['billing_email'],
			'_billing_phone'	=> $user_meta['billing_phone'],
			'_shipping_method'	=> '',
			'_shipping_method_title' => '', 	 
			'_payment_method'	=> 'paypal',
			'_payment_method_title'	=> 'PayPal',
			'_order_shipping'	=> '0.0',
			'_order_discount'	=> '0.0',
			'_cart_discount'	=> '0.0',
			'_order_tax'	=> '0.0',
			'_order_shipping_tax'	=> '0.0',
			'_order_key'	=> $order_key,
			'_customer_user'	=> $user_id,
			'_order_taxes'	=> 'a:0:{}',
			'_order_currency'	=> 'USD',
			'_prices_include_tax'	=> 'no',
			'Payer PayPal address'	=> $user_meta['billing_email'],
			'Transaction ID'	=> $transaction_id,
			'Payer first name'	=> $user_meta['billing_first_name'],
			'Download Permissions Granted'	=> 1,
			'Customer IP Address' => $trans['Payer_IP'],
			'E-Junkie Internal Transaction ID' => $trans['Ej_Internal_Txn_ID'],
			'Invoice ID' => $trans['Invoice']
		);
		if (!empty($trans['Passed_Custom_Param'])) {
			$order_meta['Custom Parameter'] = $trans['Passed_Custom_Param'];
		}
		if (!empty($trans['Discount_Codes'])) {
			$order_meta['Coupons'] = $trans['Discount_Codes'];
		}
		if (!empty($trans['Affiliate_Email'])) {
			$order_meta['Affiliate'] = serialize(array('email'=>$trans['Affiliate_Email'], 'name'=>$trans['Affiliate_Name'], 'id'=>$trans['Affiliate_ID']));
		}	
	}
	
	// Cart items
	
	// Transform product id and name
	$sku = (!empty($trans['SKU'])) ? $trans['SKU'] : $trans['Item_Number'];
	if (isset($products_map[ $sku ]))
	{
		$product_info = $products_map[ $sku ];
		$product_id = $product_info['product']->id;
		$variation_id = ($product_info['variation']) ? $product_info['variation']->get_variation_id() : 0;
		$qty = (absint($trans['Quantity']) > 0) ? absint($trans['Quantity']) : 1;
		$price = $trans['Amount'];
		$order_total += $price;
		$price = $price / $qty;
		$access_start = $order_date;
		$access_end = date('Y-m-d H:i:s', $transaction_time + (365 * 86400)); // one year from order date
		$order_items[] = array(
	 		'id' 				=> $product_id,
	 		'variation_id' 		=> $variation_id,
	 		'name' 				=> $product->get_title(),
	 		'qty' 				=> $qty,
	 		'item_meta'			=> ($product_info['item_meta']) ? $product_info['item_meta']->meta : array(),
	 		'line_subtotal' 	=> rtrim(rtrim(number_format($price, 4, '.', ''), '0'), '.'),	// Line subtotal (before discounts)
	 		'line_subtotal_tax' => rtrim(rtrim(number_format(0, 4, '.', ''), '0'), '.'), // Line tax (before discounts)
	 		'line_total'		=> rtrim(rtrim(number_format($price, 4, '.', ''), '0'), '.'), 		// Line total (after discounts)
	 		'line_tax' 			=> rtrim(rtrim(number_format(0, 4, '.', ''), '0'), '.'), 		// Line Tax (after discounts)
	 		'tax_class'			=> ''								// Tax class (adjusted by filters)
	 	);
	 	
	 	// Add to downloadable_product_permissions table
	 	$download_item_id = ($variation_id > 0) ? $variation_id : $product_id;
	 	$_wp_downloadables_rows[] = "( {$download_item_id}, {$order_id}, '{$order_key}', '{$trans['Payer_Email']}', {$user_id}, '{$access_start}', '{$access_end}' )";
	 	
	 	// Detect serial key in the code
	 	$serial = '';
	 	if (!empty($trans['KeyCode_if_any']) && ($pos = strpos($trans['KeyCode_if_any'], 'Key: '))) {
 			$serial = trim(substr($trans['KeyCode_if_any'], $pos + 5));
	 	}
	 	if (!empty($serial)) {
	 		$_wp_serial_keys_rows[] = "( {$order_id}, {$download_item_id}, '{$serial}', '{$access_end}' )";
	 	}
	}
	
	$last_trans = $trans;
}
$end_order_id = $max_order_id;

// Clean ups
echo "<hr/><h1>Now cleaning up...</h1>";

// Set order status to completed..
if ($start_order_id != $end_order_id) {
	$wpdb->query("REPLACE INTO `{$wpdb->prefix}term_relationships` (object_id, term_taxonomy_id) select ID, {$tt_id} from {$wpdb->prefix}posts where post_type = 'shop_order' and post_status = 'publish' AND ID BETWEEN {$start_order_id} AND {$end_order_id} and ID not in (select object_id from {$wpdb->prefix}term_relationships where term_taxonomy_id = '{$tt_id}')");
}

// Update order GUIDs
$wpdb->query('UPDATE `'.$wpdb->prefix.'posts` SET guid = concat("http://www.sitename.com/?shop-order=", post_name) WHERE post_type = "shop_order" AND guid = "" AND post_status = "publish"');

// Record product sales
$wpdb->query("delete from `'.$wpdb->prefix.'postmeta` where meta_key = 'total_sales' and post_id in (select distinct product_id from `'.$wpdb->prefix.'woocommerce_downloadable_product_permissions`)");
$wpdb->query("insert into `'.$wpdb->prefix.'postmeta` (post_id, meta_key, meta_value) select product_id as post_id, 'total_sales' as meta_key, count(order_id) as meta_value from `'.$wpdb->prefix.'woocommerce_downloadable_product_permissions` group by product_id");
echo "<hr/><h1>Operations completed...</h1>";
