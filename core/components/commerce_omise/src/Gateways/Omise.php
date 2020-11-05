<?php

namespace DigitalPenguin\Commerce_Omise\Gateways;

use Commerce;
use comOrder;
use comPaymentMethod;
use comTransaction;
use modmore\Commerce\Admin\Widgets\Form\Field;
use modmore\Commerce\Admin\Widgets\Form\PasswordField;
use modmore\Commerce\Admin\Widgets\Form\SectionField;
use modmore\Commerce\Admin\Widgets\Form\TextField;
use modmore\Commerce\Gateways\Exceptions\TransactionException;
use modmore\Commerce\Gateways\Interfaces\RedirectTransactionInterface;
use modmore\Commerce\Gateways\Interfaces\TransactionInterface;
use modmore\Commerce\Gateways\Manual\ManualTransaction;

class Omise implements \modmore\Commerce\Gateways\Interfaces\GatewayInterface {
    /** @var Commerce */
    protected $commerce;

    /** @var comPaymentMethod */
    protected $method;

    public function __construct(Commerce $commerce, comPaymentMethod $method)
    {
        $this->commerce = $commerce;
        $this->method = $method;
    }

    /**
     * Render the payment gateway for the customer; this may show issuers or a card form, for example.
     *
     * @param comOrder $order
     * @return string
     */
    public function view(comOrder $order)
    {
        // Load sandbox version if Commerce is in test mode.
        $publicKey = $this->method->getProperty('livePublicApiKey');
        if($this->commerce->isTestMode()) {
            $publicKey = $this->method->getProperty('sandboxPublicApiKey');
        }

        $jsUrl = 'https://cdn.omise.co/omise.js?v='.round(microtime(true)/100);
        return $this->commerce->view()->render('frontend/gateways/omise.twig', [
            'method'        =>  $this->method->get('id'),
            'js_url'        =>  $jsUrl,
            'public_key'    =>  trim($publicKey),
            'code_error'    =>  $this->commerce->adapter->lexicon('commerce_omise.form.security_code_error')
        ]);
    }

    /**
     * Handle the payment submit, returning an up-to-date instance of the PaymentInterface.
     *
     * @param comTransaction $transaction
     * @param array $data
     * @return TransactionInterface|RedirectTransactionInterface|ManualTransaction
     * @throws TransactionException
     */
    public function submit(comTransaction $transaction, array $data)
    {
        $this->commerce->modx->log(MODX_LOG_LEVEL_ERROR,print_r($_POST,true));
        // Validate the request
        if (!array_key_exists('omise_token', $data) || empty($data['omise_token'])) {
            throw new TransactionException('omise_token is missing.');
        }

        $value = htmlentities($data['omise_token'], ENT_QUOTES, 'UTF-8');

        $transaction->setProperty('omise_token', $value);
        $transaction->save();

        // ManualTransaction is used by the Manual payment gateway and has an always-successful response;
        // useful for testing but not quite for actual payments.
        return new \modmore\Commerce\Gateways\Manual\ManualTransaction($value);
    }

    /**
     * Handle the customer returning to the shop, typically only called after returning from a redirect.
     *
     * @param comTransaction $transaction
     * @param array $data
     * @return ManualTransaction
     * @throws TransactionException
     */
    public function returned(comTransaction $transaction, array $data)
    {
        // called when the customer is viewing the payment page after a submit(); we can access stuff in the transaction
        $value = $transaction->getProperty('required_value');

        return new \modmore\Commerce\Gateways\Manual\ManualTransaction($value);
    }

    /**
     * Define the configuration options for this particular gateway instance.
     *
     * @param comPaymentMethod $method
     * @return Field[]
     */
    public function getGatewayProperties(comPaymentMethod $method)
    {

        $fields = [];

        $fields[] = new SectionField($this->commerce, [
            'label' => 'Sandbox Mode Authentication',
            'description' => 'When Commerce is in test mode, the sandbox credentials are used.',
        ]);

        $fields[] = new TextField($this->commerce, [
            'name' => 'properties[sandboxPublicApiKey]',
            'label' => 'Public Key (Sandbox)',
            'description' => 'Enter your public API key.',
            'value' => $method->getProperty('sandboxPublicApiKey'),
        ]);

        $fields[] = new PasswordField($this->commerce, [
            'name' => 'properties[sandboxSecretApiKey]',
            'label' => 'Secret Key (Sandbox)',
            'description' => 'Enter your secret API key.',
            'value' => $method->getProperty('sandboxSecretApiKey'),
        ]);

        $fields[] = new SectionField($this->commerce, [
            'label' => 'Live Mode Authentication',
            'description' => 'When Commerce is in live mode, the live credentials are used.',
        ]);

        $fields[] = new TextField($this->commerce, [
            'name' => 'properties[livePublicApiKey]',
            'label' => 'Public Key (Live)',
            'description' => 'Enter your public API key.',
            'value' => $method->getProperty('livePublicApiKey'),
        ]);

        $fields[] = new PasswordField($this->commerce, [
            'name' => 'properties[liveSecretApiKey]',
            'label' => 'Secret Key (Live)',
            'description' => 'Enter your secret API key.',
            'value' => $method->getProperty('liveSecretApiKey'),
        ]);

        return $fields;
    }
}