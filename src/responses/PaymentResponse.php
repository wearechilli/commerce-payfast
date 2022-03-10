<?php

namespace stenvdb\payfast\responses;

use Craft;
use craft\commerce\base\RequestResponseInterface;

class PaymentResponse implements RequestResponseInterface
{
    protected $data = [];
    protected $testMode;

    public function __construct($data, $testMode)
    {
        $this->data = $data;
        $this->testMode = $testMode;
    }

    public function isSuccessful(): bool
    {
        return false;
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
        return 'POST';
    }

    public function getRedirectData(): array
    {
        return $this->data;
    }

    public function getRedirectUrl(): string
    {
        return $this->testMode ? 'https://sandbox.payfast.co.za/eng/process' : 'https://www.payfast.co.za/eng/process';
    }

    public function getTransactionReference(): string
    {
        return $this->data['m_payment_id'];
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
