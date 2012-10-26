### _For a discount on 2Checkout’s monthly fees, enter promo code:  GIT2CO  during signup._

### VirtueMart -v 2.0.x with 2Checkout
----------------------------------------

### VirtueMart Settings

1. Download the 2Checkout payment module at https://github.com/craigchristenson/2CO_VirtueMart2
2. Upload the files to your joomla directory on your web server.
3. Run the vm2_tco.sql file on your Joomla database. If your database makes use of prefixes on the tables, replace ##### with your prefix. (Joomla 1.6, 1.7, or 2.5)
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

Please feel free to contact 2Checkout directly with any integration questions.
