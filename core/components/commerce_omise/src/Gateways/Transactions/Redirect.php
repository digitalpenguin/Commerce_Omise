<?php
namespace DigitalPenguin\Commerce_Omise\Gateways\Transactions;

use modmore\Commerce\Gateways\Interfaces\RedirectTransactionInterface;
use modmore\Commerce\Gateways\Interfaces\TransactionInterface;

class Redirect implements TransactionInterface, RedirectTransactionInterface {

    private $data;
    private $order;

    public function __construct($order,$data) {
        $this->data = $data;
        $this->order = $order;
    }

    /**
     * Indicate if the transaction requires the customer to be redirected off-site.
     *
     * @return bool
     */
    public function isRedirect() {
        return true;
    }

    /**
     * @return string Either GET or POST
     */
    public function getRedirectMethod() {
        return 'GET';
    }

    /**
     * Return the fully qualified URL to redirect the customer to.
     *
     * @return string
     */
    public function getRedirectUrl() {
        return $this->data['authorize_uri'];
    }

    /**
     * Return the redirect data as a key => value array, when the redirectMethod is POST.
     *
     * @return array
     */
    public function getRedirectData() {
        return $this->data;
    }

    /**
     * Indicate if the transaction was paid
     *
     * @return bool
     */
    public function isPaid() {
        return false;
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
        return true;
    }

    /**
     * Indicate if the payment has failed.
     *
     * @return bool
     * @see TransactionInterface::getExtraInformation()
     */
    public function isFailed() {
        return false;
    }

    /**
     * Indicate if the payment was cancelled by the user (or possibly merchant); which is a separate scenario
     * from a payment that failed.
     *
     * @return bool
     */
    public function isCancelled() {
        return false;
    }

    /**
     * If an error happened, return the error message.
     *
     * @return string
     */
    public function getErrorMessage() {
        return '';
    }

    /**
     * Return the (payment providers') reference for this order. Treated as a string.
     *
     * @return string
     */
    public function getPaymentReference() {
        return '';
    }

    /**
     * Return a key => value array of transaction information that should be made available to merchant users
     * in the dashboard.
     *
     * @return array
     */
    public function getExtraInformation() {
        return [];
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