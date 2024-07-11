<?php

namespace wearechilli\payfast\responses;

use Craft;
use craft\commerce\base\RequestResponseInterface;

class CompletePaymentResponse implements RequestResponseInterface
{

    public function __construct($data)
    {
        $this->data = $data;
    }


    public function isSuccessful(): bool
    {
        return true;
    }

    public function isProcessing(): bool
    {
        return false;
    }

    public function isRedirect(): bool
    {
        return true;
    }

    public function getRedirectMethod(): string
    {
        return 'GET';
    }

    public function getRedirectData(): array
    {
        $data = json_decode($this->data->response,true);
        return $data;
    }

    public function getRedirectUrl(): string
    {   
        return Craft::$app->sites->currentSite->baseUrl . 'checkout/order?number=' . $this->data->order->number;
    }

    public function getTransactionReference(): string
    {
        
        return json_decode($this->data->response,true)['m_payment_id'];
    }

    public function getCode(): string
    {
        return '';
    }

    public function getData()
    {
        return $this->data;
    }

    public function getMessage(): string
    {
        return '';
    }

    public function redirect()
    {
        
    }
}
