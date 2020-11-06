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

Sandbox Testing
-
Here are a list of the credit card numbers you can use when testing with the Sandbox API.
https://www.omise.co/api-testing