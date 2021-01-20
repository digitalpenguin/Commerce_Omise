<?php

namespace DigitalPenguin\Commerce_Omise\Gateways;

use Commerce;
use comOrder;
use comPaymentMethod;
use comTransaction;
use DigitalPenguin\Commerce_Omise\API\OmiseClient;
use modmore\Commerce\Admin\Widgets\Form\Field;
use modmore\Commerce\Admin\Widgets\Form\PasswordField;
use modmore\Commerce\Admin\Widgets\Form\TextField;
use modmore\Commerce\Gateways\Exceptions\TransactionException;
use modmore\Commerce\Gateways\Interfaces\GatewayInterface;
use modmore\Commerce\Gateways\Interfaces\TransactionInterface;
use modmore\Commerce\Gateways\Interfaces\SharedWebhookGatewayInterface;
use modmore\Commerce\Gateways\Interfaces\WebhookGatewayInterface;
use modmore\Commerce\Gateways\Interfaces\WebhookTransactionInterface;

class PromptPay implements GatewayInterface, WebhookGatewayInterface, SharedWebhookGatewayInterface {
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

        return $this->commerce->view()->render('frontend/gateways/promptpay.twig', [
            'method'        =>  $this->method->get('id'),
            'currency'      =>  $order->get('currency'),
            'amount'        =>  $order->get('total'),
            'public_key'    =>  trim($publicKey),
            'code_error'    =>  $this->commerce->adapter->lexicon('commerce_omise.form.security_code_error')
        ]);
    }

    /**
     * Used for gateways that use a single pre-defined webhook endpoint (which does not include the ?transaction=ID
     * parameter in the per-transaction URL) to identify the transaction from other information.
     *
     * This method should *not* submit/act on the transaction by itself; it should only return the appropriate
     * comTransaction record that the webhook belongs to.
     *
     * If no transaction can be found, the method should return false.
     *
     * For an example {@see StripeCard::identifyWebhookTransaction}
     *
     * @return \comTransaction|false
     */
    public function identifyWebhookTransaction()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if(empty($data)) return false;

        $this->commerce->modx->log(MODX_LOG_LEVEL_INFO,print_r($data,true));

        // Sanitize charge id and key strings
        $chargeId = filter_var($data['data']['id'],FILTER_SANITIZE_STRING);
        $status = filter_var($data['key'],FILTER_SANITIZE_STRING);
        if(!$chargeId || !$status) return false;

        $c = $this->adapter->newQuery('comTransaction');
        $c->where([
            'method'    =>  $this->method->get('id')
        ]);
        $transactions = $this->adapter->getIterator('comTransaction', $c);

        if(empty($transactions)) return false;

        foreach ($transactions as $transaction) {
            if ($transaction instanceof \comTransaction) {
                $transactionChargeId = $transaction->getProperty('charge_id');
                if($chargeId === $transactionChargeId) {
                    return $transaction;
                }
            }
        }
        return false;
    }

    /**
     * Handle an incoming webhook. Webhook URLs, and fetching the transaction in the webhook, happen transparently.
     *
     * $data contains unfiltered information from $_REQUEST.
     *
     * @param \comTransaction $transaction
     * @param array $data
     * @return WebhookTransactionInterface
     * @throws TransactionException
     */
    public function webhook(\comTransaction $transaction, array $data) {

        // Verify payment has been made.
        $secretKey = $this->method->getProperty('liveSecretApiKey');
        if($this->commerce->isTestMode()) {
            $secretKey = $this->method->getProperty('sandboxSecretApiKey');
        }

        $chargeId = $transaction->getProperty('charge_id');

        $client = new OmiseClient(trim($secretKey),$this->commerce->isTestMode());
        $requestParams = [];
        $response = $client->request('/charges/'.$chargeId,$requestParams,'GET');

        $responseData = $response->getData();
        if(!$responseData) throw new TransactionException('Error communicating with Omise when attempting to verify payment...');
        $this->commerce->modx->log(MODX_LOG_LEVEL_DEBUG,print_r($responseData,true));

        // Debugging ONLY
        //$responseData['status'] = 'successful';

        $data['charge'] = $responseData;
        $promptPayTransaction = new \DigitalPenguin\Commerce_Omise\Gateways\Transactions\PromptPay\PromptPay($transaction->getOrder(),$data);

        // For debugging, set MODX to log level 4
        $this->commerce->modx->log(MODX_LOG_LEVEL_DEBUG,$responseData['status']);

        $successful = false;
        switch($responseData['status']) {

            case 'successful':
                $successful = true;
                break;
            case 'expired':
                $promptPayTransaction->setFailed(true);
                $promptPayTransaction->setErrorMessage('expired');
                break;
            case 'failed_processing':
                $promptPayTransaction->setFailed(true);
                $promptPayTransaction->setErrorMessage('failed_processing');
                break;
            case 'insufficient_balance':
                $promptPayTransaction->setFailed(true);
                $promptPayTransaction->setErrorMessage('insufficient_balance');
                break;
            case 'payment_cancelled':
                $promptPayTransaction->setCancelled(true);
                $promptPayTransaction->setErrorMessage('payment_cancelled');
                break;
            default:
                // default is pending
        }

        if($successful) {
            $promptPayTransaction->setAwaitingConfirmation(true);
            $promptPayTransaction->setPaid(true);
        }

        return $promptPayTransaction;
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
        $this->commerce->modx->log(MODX_LOG_LEVEL_INFO,print_r($_POST,true));

        // Validate the request
        if (!array_key_exists('omise_promptpay_token', $data) || empty($data['omise_promptpay_token'])) {
            throw new TransactionException('omise_promptpay_token is missing.');
        }
        $value = htmlentities($data['omise_promptpay_token'], ENT_QUOTES, 'UTF-8');

        $transaction->setProperty('omise_promptpay_token', $value);
        $transaction->save();

        $order = $transaction->getOrder();

        /*
         * Check if currency has subunits.
         * If a currency has subunits, it needs to be formatted correctly when sending to the API
         */
        $currency = $order->getCurrency();
        if($currency->get('subunits') > 0) {
            // Convert from cents if currency has subunits
            $total = round($order->get('total') / 100, $currency->get('subunits'));
            // Ensure using decimal point if currency has subunits.
            $total = str_replace(',', '.', (string)$total);
        }

        $secretKey = $this->method->getProperty('liveSecretApiKey');
        if($this->commerce->isTestMode()) {
            $secretKey = $this->method->getProperty('sandboxSecretApiKey');
        }

        $client = new OmiseClient(trim($secretKey),$this->commerce->isTestMode());
        $requestParams = [
            'amount'        =>  $order->get('total'),
            'currency'      =>  $order->get('currency'),
            'source'        =>  $data['omise_promptpay_token'],
        ];
        $response = $client->request('/charges',$requestParams,'POST');


        $responseData = $response->getData();
        if(!$responseData) throw new TransactionException('Error communicating with Omise...');

        $qrUri = $responseData['source']['scannable_code']['image']['download_uri'];
        $transaction->setProperty('omise_promptpay_qrcode',$qrUri);

        // Set Charge id in transaction
        $transaction->setProperty('charge_id',$responseData['id']);

        $this->commerce->modx->log(MODX_LOG_LEVEL_INFO,print_r($responseData,true));


        $promptPayTransaction = new \DigitalPenguin\Commerce_Omise\Gateways\Transactions\PromptPay\PromptPay($order,$data);
        return $promptPayTransaction;
    }

    /**
     * Handle the customer returning to the shop, typically only called after returning from a redirect.
     *
     * @param comTransaction $transaction
     * @param array $data
     * @return \DigitalPenguin\Commerce_Omise\Gateways\Transactions\PromptPay\PromptPay
     */
    public function returned(comTransaction $transaction, array $data)
    {

        return new \DigitalPenguin\Commerce_Omise\Gateways\Transactions\PromptPay\PromptPay($transaction->getOrder(),$data);

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