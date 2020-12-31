Omise Payment Gateway Integration for Commerce on the MODX CMS.
=
Development by Murray Wood at Digital Penguin. Thanks to Inside Creative for sponsoring the development of this module.

Requirements
-
Commerce_Omise requires at least MODX 2.6.5 and PHP 7.1 or higher. Commerce by modmore should be at least version 1.1.4. You also need to have an Omise account which provides public and secret API keys.

Installation
-
Install via the MODX package manager. The package name is Commerce_Omise.

Setup
-
Once installed, navigate to Commerce in the MODX manager. Select the Configuration tab and then click on Modules. Find Commerce_Omise in the module list and click on it to make a window pop up where you can enable the module for Test Mode, Live Mode, or both. Note that while Commerce is set to test mode, Commerce_Omise will automatically use the sandbox API. Setting Commerce to Live Mode will use Omise's live API.

Now the module is enabled, you can click on the Payment Methods tab on the left. Then click Add a Payment Method. Select Omise from the Gateway dropdown box and then give it a name e.g. Omise. Next, click on the availability tab and enable it for test or live modes and then click save.

After saving, you'll see an Omise tab appears at the top of the window. Here you can enter your Omise API credentials: sandbox secret API key, sandbox public API key, live secret API key and live public API key.

Congratulations! Omise should now appear as a payment method a customer can use during checkout.

Payment Method 1: Credit Cards
==

Customising the Payment Form
-
Omise provides very basic HTML form elements giving you, the developer, a lot of freedom with styling. 
i.e. https://www.omise.co/collecting-card-information

For this reason, the payment form sits in a chunk called `omise_form_tpl`. It is recommended you duplicate this chunk, give it a unique name and make any changes you want in the duplicated chunk. 
Commerce_Omise provides a system setting called `form_chunk_name` which you update so it contains the name of your new duplicated chunk.

Default chunk:
```
<div id="omise-token-errors"></div>
<input type="hidden" id="omise-token" name="omise_token">

<div>
    <label>[[%commerce_omise.form.name]]</label>
    <input type="text" id="omise-holder-name">
</div>
<div>
    <label>[[%commerce_omise.form.number]]</label>
    <input type="text" id="omise-number">
</div>
<div>
    <label>[[%commerce_omise.form.date]]</label>
    <div>
        <input type="text" id="omise-expiration-month" size="4"> /
        <input type="text" id="omise-expiration-year" size="8">
    </div>
</div>
<div>
    <label>[[%commerce_omise.form.security_code]]</label>
    <input type="text" id="omise-security-code" size="8">
</div>
```

When editing the duplicated chunk be sure not to change the `id` or `name` attributes on any of the elements or Omise won't authenticate if it's missing data.

3D Secure Mode
-
Omise say their fraud analysts will activate 3D secure on accounts where they deem it necessary.
https://www.omise.co/how-to-implement-3-D-Secure
Version 1.1.0 brings support for the 3D Secure mode. There is an added checkbox on the same page
you enter your API keys to enable it.
If 3D secure is active, you will not be able to test it on a local development environment. As it 
requires a redirect away to a bank verification page and then back to your Commerce webshop, it 
needs to be on a server with a Fully Qualified Domain Name (FQDN). 

Sandbox Testing
-
Here are a list of the credit card numbers you can use when testing with the Sandbox API.
https://www.omise.co/api-testing


Payment Method 2: PromptPay
==

PromptPay is an "offline" payment method which means the payment authorisation is done through the customer's PromptPay 
phone app by scanning a QR code generated by this extension. Omise then sends a notification to Commerce when completed.

![payment flow](https://cdn.omise.co/assets/screenshots/articles/2017-11-02/promptpay/payment_flow_desktop.png)

The QR code image is displayed on the "thank you page" of the checkout process. So a customer selects the Omise-PromptPay 
payment method and then clicks on the "Pay with PromptPay" button. The order will be created as a draft and
display the thank you page with the QR code image.  

As the developer, you will need to override the default `templates/frontend/checkout/pending-transaction.twig` into a custom template and 
add the following to it:
```
{% if transaction.properties.omise_promptpay_qrcode %}
    <div id="omise-promptpay-qrcode">
        <img alt="Omise PromptPay QR Code" src="{{ transaction.properties.omise_promptpay_qrcode }}">
    </div>
{% endif %}
```
This will allow the QR code to be added, but only if it is this particular payment method used.
See more about overriding template files here: https://docs.modmore.com/en/Commerce/v1/Front-end_Theming.html#page_Overriding+Template+Files

Omise Webhook for PromptPay
--

Since this is an offline payment method, Omise needs to know how to notify Commerce that a payment was authorised, so in your Omise dashboard select
*Webhooks* and then add the following:
```
https://your-domain/assets/components/commerce/notify.php?method=1
```
Note: the `?method=1` param refers to the id of this payment method. Change accordingly.


