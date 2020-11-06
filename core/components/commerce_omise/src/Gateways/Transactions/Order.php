<?php

namespace DigitalPenguin\Commerce_Omise\Gateways\Transactions;

use modmore\Commerce\Gateways\Interfaces\TransactionInterface;

class Order implements TransactionInterface
{
    protected $isPaid = false;
    protected $isFailed = false;
    protected $errorMsg = '';
    protected $orderId;
    protected $orderData;
    protected $verifyData;

    public function __construct($order, $orderData)
    {
        $this->orderId = $order->get('id');
        $this->orderData = $orderData;
    }

    /**
     * Indicate if the transaction was paid
     *
     * @return bool
     */
    public function isPaid()
    {
        return $this->isPaid;
    }

    /**
     * Set isPaid state.
     *
     * @param bool $isPaid
     */
    public function setPaid(bool $isPaid) {
        $this->isPaid = $isPaid;
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
    public function isAwaitingConfirmation()
    {
        return false;
    }

    public function isRedirect()
    {
        return false;
    }

    /**
     * Indicate if the payment has failed.
     *
     * @return bool
     * @see TransactionInterface::getExtraInformation()
     */
    public function isFailed()
    {
        return $this->isFailed;
    }

    /**
     * Set isFailed state.
     *
     * @param bool $isFailed
     */
    public function setFailed(bool $isFailed) {
        $this->isFailed = $isFailed;
    }

    /**
     * Indicate if the payment was cancelled by the user (or possibly merchant); which is a separate scenario
     * from a payment that failed.
     *
     * @return bool
     */
    public function isCancelled()
    {
        return false;
    }

    /**
     * If an error happened, return the error message.
     *
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMsg;
    }

    /**
     * Sets the error to return if something goes wrong.
     *
     * @param $errorMsg
     * @return string
     */
    public function setErrorMessage($errorMsg) {
        $this->errorMsg = $errorMsg;
    }

    /**
     * Return the (payment providers') reference for this order. Treated as a string.
     *
     * @return string
     */
    public function getPaymentReference()
    {
        return $this->orderId;
    }

    /**
     * Return a key => value array of transaction information that should be made available to merchant users
     * in the dashboard.
     *
     * @return array
     */
    public function getExtraInformation()
    {
        $extra = [];

        if (array_key_exists('id', $this->orderData)) {
            $extra['commerce_omise.id'] = $this->orderData['id'];
        }
        if (array_key_exists('location', $this->orderData)) {
            $extra['commerce_omise.location'] = $this->orderData['location'];
        }
        if (array_key_exists('amount', $this->orderData)) {
            $extra['commerce_omise.amount'] = $this->orderData['amount'];
        }
        if (array_key_exists('currency', $this->orderData)) {
            $extra['commerce_omise.currency'] = $this->orderData['currency'];
        }
        if (array_key_exists('id', $this->orderData['card'])) {
            $extra['commerce_omise.card.id'] = $this->orderData['card']['id'];
        }
        if (array_key_exists('brand', $this->orderData['card'])) {
            $extra['commerce_omise.card.brand'] = $this->orderData['card']['brand'];
        }
        if (array_key_exists('fingerprint', $this->orderData['card'])) {
            $extra['commerce_omise.card.fingerprint'] = $this->orderData['card']['fingerprint'];
        }
        if (array_key_exists('last_digits', $this->orderData['card'])) {
            $extra['commerce_omise.card.last_digits'] = $this->orderData['card']['last_digits'];
        }
        if (array_key_exists('name', $this->orderData['card'])) {
            $extra['commerce_omise.card.name'] = $this->orderData['card']['name'];
        }
        if (array_key_exists('expiration_month', $this->orderData['card'])) {
            $extra['commerce_omise.card.expiration_month'] = $this->orderData['card']['expiration_month'];
        }
        if (array_key_exists('expiration_year', $this->orderData['card'])) {
            $extra['commerce_omise.card.expiration_year'] = $this->orderData['card']['expiration_year'];
        }
        if (array_key_exists('security_code_check', $this->orderData['card'])) {
            $extra['commerce_omise.card.security_code_check'] = $this->orderData['card']['security_code_check'];
        }
        if (array_key_exists('created_at', $this->orderData['card'])) {
            $extra['commerce_omise.card.created_at'] = $this->orderData['card']['created_at'];
        }

        return $extra;
    }

    /**
     * Return an array of all (raw) transaction data, for debugging purposes.
     *
     * @return array
     */
    public function getData()
    {
        return $this->orderData;
    }
}