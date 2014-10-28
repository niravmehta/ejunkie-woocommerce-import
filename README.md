A PHP script to import [e-Junkie](http://www.e-junkie.com/) transactions to [WooCommerce](https://wordpress.org/plugins/woocommerce/).

# Disclaimer

I wrote this script to import our e-Junkie transactions to WooCommerce. It was written in late 2012 and used the data structure at that time. So it may not work out of the box (or at all) currently. Don't hold me responsible for any losses or damages! This comes on a "as-is" basis!

# Overview

Here's what you may need to do to get things to work.

1. Download your e-Junkie transactions log in a .csv form.
2. Create a table in your WordPress db (where WooCommerce is installed) called ejunkie_transactions. Schema below.
3. Import e-Junkie transactions to the table using `LOAD DATA INFILE` or any other method.
4. Make sure you have products created in WooCommerce, and SKUs stored in `_sku` field, and that SKUs match!
4. Review the ej2wc.php file, make modifications as needed. (Change sitename near `// Update order GUIDs` as well)
5. Run the file.
6. Fix errors if any. Ensure everything works.
7. Submit a pull request with your fixes so everyone benefits!

# Handling Serial Keys

You may see references to serial keys in the code. This is because we used serial keys for order validation on e-Junkie. There was no similar solution on WooCommerce, so we created a new extension - [WooCommerce Serial Key](http://www.storeapps.org/product/woocommerce-serial-key/). 

If you want to use Serial Keys, you may want to use that plugin, and update the code accordingly.

# Preparations

## Expected fields in e-Junkie CSV file
Payment_Date,Processed_by_Ej,Transaction_ID,Payment_Processor,Ej_Internal_Txn_ID,Payment_Status,First_Name,Last_Name,Payer_Email,Billing_Info,Payer_Phone,Card_Last_4,Card_Type,Payer_IP,Passed_Custom_Param,Discount_Codes,Invoice,Shipping_Info,Shipping_Phone,Shipping,Tax,eBay_Auction_Buyer_ID,Affiliate_Email,Affiliate_Name,Affiliate_ID,Affiliate_Share,Currency,Item_Name,VariationsVariants,Item_Number,SKU,Quantity,Amount,Affiliate_Share_per_item,Download_Info,KeyCode_if_any,eBay_Auction_ID,Buyer_Country
  
## Table structure for ejunkie_transactions
```
CREATE TABLE IF NOT EXISTS `ejunkie_transactions` (
  `Payment_Date` datetime NOT NULL,
  `Processed_by_Ej` datetime NOT NULL,
  `Transaction_ID` varchar(40) NOT NULL,
  `Payment_Processor` varchar(40) NOT NULL,
  `Ej_Internal_Txn_ID` varchar(40) NOT NULL,
  `Payment_Status` varchar(40) NOT NULL,
  `First_Name` varchar(40) NOT NULL,
  `Last_Name` varchar(40) NOT NULL,
  `Payer_Email` varchar(40) NOT NULL,
  `Billing_Info` varchar(40) DEFAULT NULL,
  `Payer_Phone` varchar(40) DEFAULT NULL,
  `Card_Last_4` varchar(10) DEFAULT NULL,
  `Card_Type` varchar(20) DEFAULT NULL,
  `Payer_IP` varchar(20) DEFAULT NULL,
  `Passed_Custom_Param` varchar(50) DEFAULT NULL,
  `Discount_Codes` varchar(40) DEFAULT NULL,
  `Invoice` varchar(40) DEFAULT NULL,
  `Shipping_Info` varchar(40) DEFAULT NULL,
  `Shipping_Phone` varchar(40) DEFAULT NULL,
  `Shipping` double(6,2) DEFAULT NULL,
  `Tax` double(6,2) DEFAULT NULL,
  `eBay_Auction_Buyer_ID` varchar(40) DEFAULT NULL,
  `Affiliate_Email` varchar(60) DEFAULT NULL,
  `Affiliate_Name` varchar(60) DEFAULT NULL,
  `Affiliate_ID` varchar(40) DEFAULT NULL,
  `Affiliate_Share` double(6,2) DEFAULT NULL,
  `Currency` varchar(10) DEFAULT NULL,
  `Item_Name` varchar(50) NOT NULL DEFAULT '',
  `VariationsVariants` varchar(40) DEFAULT NULL,
  `Item_Number` varchar(40) DEFAULT NULL,
  `SKU` varchar(40) DEFAULT NULL,
  `Quantity` int(11) DEFAULT NULL,
  `Amount` double(9,2) DEFAULT NULL,
  `Affiliate_Share_per_item` double(6,2) DEFAULT NULL,
  `Download_Info` varchar(100) DEFAULT NULL,
  `KeyCode_if_any` varchar(100) DEFAULT NULL,
  `eBay_Auction_ID` varchar(40) DEFAULT NULL,
  `Buyer_Country` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`Transaction_ID`,`Item_Name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
```
