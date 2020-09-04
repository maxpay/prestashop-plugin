Maxpay payment module for Prestashop 1.7 or higher.

INSTALLATION:
Module is installed via Module manager or copying the Module folder to the Prestashop modules folder.

GENERAL SETUP:
1. Login/register to https://my.maxpay.com
2. Create Payment page and get Public/Private keys (test keys are also available here).
3. Use these keys to configure Maxpay payment module.
4. On module configuration page you can enable Test mode if neccessary.
5. All the other settings are done in Maxpay control panel (https://my.maxpay.com). 
There you can specify success/decline/callback urls for test/prod mode.

Recommendations are:

Success url: {{YOUR_SITE_URL}}/index.php?fc=module&module=maxpay&controller=paymentreturn
Decline url: {{YOUR_SITE_URL}}/index.php?fc=module&module=maxpay&controller=paymentreturn
Callback url: {{YOUR_SITE_URL}}/index.php?fc=module&module=maxpay&controller=postback

{{YOUR_SITE_URL}} - your site url (e.g. https://maxpay.com)

USAGE
Payment processing is done by redirecting the Customer to Maxpay payment page. 
All the payment settings are done in Maxpay Control Panel (https://my.maxpay.com).

REFUND FUNCTIONALITY
To refund an order you just need to change the status of the order to Refund. 
This event will trigger Maxpay to handle money refund on this order, if payment has been done using Maxpay payment method.
Partial refund is also available using native Prestashop partial refund functinoality.
