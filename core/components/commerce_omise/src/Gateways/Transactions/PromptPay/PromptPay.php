<?php
namespace DigitalPenguin\Commerce_Omise\Gateways\Transactions\PromptPay;

use modmore\Commerce\Gateways\Interfaces\TransactionInterface;
use modmore\Commerce\Gateways\Interfaces\WebhookTransactionInterface;

class PromptPay implements TransactionInterface, WebhookTransactionInterface {

    private $data;
    private $order;
    private $failed = false;
    private $paid = false;
    private $cancelled = false;
    private $awaitingConfirmation = true;
    private $errorMessage = '';
    private $paymentReference = '';
    private $extra = [];

    public function __construct($order,$data) {
        $this->data = $data;
        $this->order = $order;
        $this->extra['charge_data'] = $data['charge'];
    }

    /**
     * Return the response that the webhook needs. Whether that indicates success or failure depends on the
     * transaction and the required logic, and typically relies on the webhook being handled or not.
     *
     * @return string
     */
    public function getWebhookResponse() {
        return 'OK';
    }

    /**
     * Return the integer response code (e.g. 200, 404) to use in the response to the webhook.
     *
     * @return int
     */
    public function getWebhookResponseCode() {
        return 200;
    }


    /**
     * Indicate if the transaction was paid
     *
     * @return bool
     */
    public function isPaid() {
        return $this->paid;
    }

    public function setPaid($isPaid) {
        $this->paid = $isPaid;
    }

    /**
     * Indicate if a transaction is waiting for confirmation/cancellation/failure. This is the case when a payment
     * is handled off-site, offline, or asynchronously in another why.
     *
     * When a transaction is marked as awaiting confirmation, a special page is shown when the customer returns
     * to the checkout.
     *
     * If the payment is a redirect (@see WebhookTransactionInterface), the payment pending page will offer the
     * customer to return to the redirectUrl.
     *
     * @return bool
     */
    public function isAwaitingConfirmation() {
        return $this->awaitingConfirmation;
    }

    public function setAwaitingConfirmation($isAwaitingConfirmation) {
        $this->awaitingConfirmation = $isAwaitingConfirmation;
    }

    /**
     * Indicate if the payment has failed.
     *
     * @return bool
     * @see TransactionInterface::getExtraInformation()
     */
    public function isFailed() {
        return $this->failed;
    }

    public function setFailed($isFailed) {
        $this->failed = $isFailed;
    }

    /**
     * Indicate if the payment was cancelled by the user (or possibly merchant); which is a separate scenario
     * from a payment that failed.
     *
     * @return bool
     */
    public function isCancelled() {
        return $this->cancelled;
    }

    public function setCancelled($isCancelled) {
        $this->cancelled = $isCancelled;
    }

    /**
     * If an error happened, return the error message.
     *
     * @return string
     */
    public function getErrorMessage() {
        return $this->errorMessage;
    }

    public function setErrorMessage($errorMessage) {
        $this->errorMessage = $errorMessage;
    }

    /**
     * Return the (payment providers') reference for this order. Treated as a string.
     *
     * @return string
     */
    public function getPaymentReference() {
        return $this->paymentReference;
    }

    public function setPaymentReference($paymentReference) {
        $this->paymentReference = $paymentReference;
    }

    /**
     * Return a key => value array of transaction information that should be made available to merchant users
     * in the dashboard.
     *
     * @return array
     */
    public function getExtraInformation() {
        return $this->extra;
    }

    /**
     * Return an array of all (raw) transaction data, for debugging purposes.
     *
     * @return array
     */
    public function getData() {
        return $this->data;
    }

}