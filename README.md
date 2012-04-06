### _For a full waiver of 2Checkout's $49 signup fee, enter promo code:  GIT2CO  during signup._

### VirtueMart -v 2.0.x with 2Checkout
----------------------------------------

### VirtueMart Settings

1. Download the 2Checkout payment module at https://github.com/craigchristenson/2CO_VirtueMart2
2. Upload the files to your joomla directory on your web server.
3. Run the vm2_tco.sql file on your Joomla database. If your database makes use of prefixes on the tables, replace ##### with your prefix. (Joomla 1.6, 1.7, or 2.5)
    `INSERT INTO `#####_extensions` (`name`,`type`, `element`, `folder`, `client_id`, `enabled`, `access`, `protected`, `manifest_cache`, `params`, `custom_data`, `system_data`, `checked_out`, `checked_out_time`, `ordering`, `state`) VALUES ('VM - Payment, 2Checkout' , 'plugin', 'tco','vmpayment',0,1,1,0, '{\”legacy\”:true,\”name\”:\”VMPAYMENT_TCO\”,\”type\”:\”plugin\”,\”creationDate\”:\”January 2012\”,\”author\”:\”Craig Christenson\”,\”copyright\”:\”Copyright (C) 2012 Craig Christenson. All rights reserved.\”,\”authorEmail\”:\”\”,\”authorUrl\”:\”http:\\/\\/www.2checkout.com\”,\”version\”:\”1.00”,\”description\”:\”<a href=\\\”http:\\/\\/2checkout.com\\\” target=\\\”_blank\\\”>2Checkout<\\/a> is a popular\\n\\tpayment provider and available in Many Countries. \\n \”,\”group\”:\”\”}', '', '', '', 0, '0000-00-00 00:00:00', 0, 0);`
4. Under **Shop** -> **Payment methods**, click **New**.
5. Enter **Payment Name**.
6. Select **Yes** for **Published**.
7. Enter **Payment Description**. _Example: Credit Card (Visa, MasterCard, American Express, Discover, JCB, Diners Club, PIN Debit) and PayPal_
8. Click **Save**.
9. Click **Configuration**.
10. Enter your **2Checkout Seller ID**. _(2Checkout Account Number)_ 
11. Enter your **2Checkout Secret Word**. _(Must be the same value entered on your 2Checkout Site Management page.)_
12. For demo sales set **Sandbox** to **Yes**. For live sales keep **Sandbox** at **No**.
13. Click **Save**.

### 2Checkout Settings

1. Sign in to your 2Checkout account. 
2. Click the **Account** tab and **Site Management** subcategory. 
3. Under **Direct Return** select **Header Redirect**.
4. Enter your **Secret Word**._(Must be the same value entered in your VirtueMart admin.)_
5. Click **Save Changes**. 