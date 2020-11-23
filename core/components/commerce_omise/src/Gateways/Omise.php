<?php

namespace DigitalPenguin\Commerce_Omise\Gateways;

use Commerce;
use comOrder;
use comPaymentMethod;
use comTransaction;
use DigitalPenguin\Commerce_Omise\API\OmiseClient;
use DigitalPenguin\Commerce_Omise\Gateways\Transactions\Redirect;
use DigitalPenguin\Commerce_Omise\Gateways\Transactions\Order;
use modmore\Commerce\Admin\Widgets\Form\Field;
use modmore\Commerce\Admin\Widgets\Form\PasswordField;
use modmore\Commerce\Admin\Widgets\Form\CheckboxField;
use modmore\Commerce\Admin\Widgets\Form\SectionField;
use modmore\Commerce\Admin\Widgets\Form\TextField;
use modmore\Commerce\Gateways\Exceptions\TransactionException;
use modmore\Commerce\Gateways\Helpers\GatewayHelper;
use modmore\Commerce\Gateways\Interfaces\GatewayInterface;
use modmore\Commerce\Gateways\Interfaces\TransactionInterface;

class Omise implements GatewayInterface {
    /** @var Commerce */
    protected $commerce;
    protected $adapter;

    /** @var comPaymentMethod */
    protected $method;

    public function __construct(Commerce $commerce, comPaymentMethod $method)
    {
        $this->commerce = $commerce;
        $this->method = $method;
        $this->adapter = $commerce->adapter;
    }

    /**
     * Render the payment gateway for the customer; this may show issuers or a card form, for example.
     *
     * @param comOrder $order
     * @return string
     * @throws \modmore\Commerce\Exceptions\ViewException
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
     * @return TransactionInterface
     * @throws TransactionException
     */
    public function submit(comTransaction $transaction, array $data)
    {
        //$this->commerce->modx->log(MODX_LOG_LEVEL_ERROR,print_r($_POST,true));

        // Validate the request
        if (!array_key_exists('omise_token', $data) || empty($data['omise_token'])) {
            throw new TransactionException('omise_token is missing.');
        }
        $value = htmlentities($data['omise_token'], ENT_QUOTES, 'UTF-8');

        $transaction->setProperty('omise_token', $value);
        $transaction->save();

        $order = $transaction->getOrder();

        $secretKey = $this->method->getProperty('liveSecretApiKey');
        if($this->commerce->isTestMode()) {
            $secretKey = $this->method->getProperty('sandboxSecretApiKey');
        }


        $returnUrl = GatewayHelper::getReturnUrl($transaction);
        $this->commerce->modx->log(MODX_LOG_LEVEL_ERROR,$returnUrl);

        $client = new OmiseClient(trim($secretKey),$this->commerce->isTestMode());
        $requestParams = [
            'amount'        =>  $order->get('total'),
            'currency'      =>  $order->get('currency'),
            'card'          =>  $data['omise_token'],
        ];
        if($this->method->getProperty('is3dSecure')) {
            $requestParams['return_uri'] = $returnUrl;
        }
        $response = $client->request('/charges',$requestParams,'POST');


        $data = $response->getData();
        if(!$data) throw new TransactionException('Error communicating with Omise...');

        $this->commerce->modx->log(MODX_LOG_LEVEL_ERROR,print_r($data,true));


        // Things are handled differently here if 3D Secure is enabled.
        if($this->method->getProperty('is3dSecure')) {

            // If 3D Secure is enabled, we take the authorize_uri from the response and redirect the customer there.
            //$authorizeUrl = $data['authorize_uri'];
            //$this->commerce->modx->log(MODX_LOG_LEVEL_ERROR,$authorizeUrl);
            $redirectTransaction = new Redirect($order,$data);

            // Save data to session for when returned
            $_SESSION['commerce_omise']['data'] = $data;
            //$this->commerce->modx->log(MODX_LOG_LEVEL_ERROR,print_r($_SESSION,true));

            return $redirectTransaction;

        } else {

            // If no 3D Secure, check for successful status.
            if($data['status'] === 'successful') {
                $orderTransaction = new Order($order,$data);
                $orderTransaction->setPaid(true);
                return $orderTransaction;
            } else {
                $orderTransaction = new Order($order,$data);
                $orderTransaction->setFailed(true);
                $orderTransaction->setErrorMessage($data['failure_message']);

                return $orderTransaction;
            }
        }
    }

    /**
     * Handle the customer returning to the shop, typically only called after returning from a redirect.
     *
     * @param comTransaction $transaction
     * @param array $data
     * @return Order
     */
    public function returned(comTransaction $transaction, array $data)
    {
        // This function is only returned when 3D secure is active and the customer is returned from the redirect to their bank.

        $order = $transaction->getOrder();

        $orderData = $_SESSION['commerce_omise']['data'];
        //$this->commerce->modx->log(MODX_LOG_LEVEL_ERROR,print_r($_SESSION,true));

        // We now have to make an API request to check the status of the charge after the redirect.
        $secretKey = $this->method->getProperty('liveSecretApiKey');
        if($this->commerce->isTestMode()) {
            $secretKey = $this->method->getProperty('sandboxSecretApiKey');
        }

        $chargeId = $orderData['id'];
        $this->commerce->modx->log(1,'Charge ID: '.$chargeId);

        $data = $this->getChargeData($secretKey,$chargeId);

        $this->commerce->modx->log(1,'Status: '.$data['status']);

        if($data['status'] === 'successful') {
            $orderTransaction = new Order($order,$data);
            $orderTransaction->setPaid(true);
            return $orderTransaction;
        } else {
            $orderTransaction = new Order($order,$data);
            $orderTransaction->setFailed(true);
            $orderTransaction->setErrorMessage($data['failure_message']);

            return $orderTransaction;
        }
    }


    public function getChargeData($secretKey, $chargeId) {
        // Give it a bit of time
        sleep(5);

        $client = new OmiseClient(trim($secretKey),$this->commerce->isTestMode());
        $response = $client->request('/charges/'.$chargeId,[],'GET');

        $data = $response->getData();
        if(!$data) throw new TransactionException('Error communicating with Omise...');

        return $data;
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
            'label' => 'Mode: 3D Secure',
            'description' => 'If your Omise merchant account has 3D Secure enabled, this setting is required.',
        ]);

        $fields[] = new CheckboxField($this->commerce, [
            'name' => 'properties[is3dSecure]',
            'label' => 'Enable 3D Secure',
            'value' => $method->getProperty('is3dSecure'),
        ]);

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