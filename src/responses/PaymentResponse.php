<?php

namespace wearechilli\payfast\responses;

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
        $variables = [];
        $hiddenFields = '';

        // Gather all post hidden data inputs.
        foreach ($this->getRedirectData() as $key => $value) {
            $hiddenFields .= sprintf('<input type="hidden" name="%1$s" value="%2$s" />', htmlentities($key, ENT_QUOTES, 'UTF-8', false), htmlentities($value, ENT_QUOTES, 'UTF-8', false)) . "\n";
        }

        $variables['inputs'] = $hiddenFields;

        // Set the action url to the responses redirect url
        $variables['actionUrl'] = $this->getRedirectUrl();

        // Set Craft to the site template mode
        $templatesService = Craft::$app->getView();
        $oldTemplateMode = $templatesService->getTemplateMode();
        $templatesService->setTemplateMode($templatesService::TEMPLATE_MODE_CP);

        $template = $templatesService->renderTemplate('commerce-payfast/redirect', $variables);

        // Restore the original template mode
        $templatesService->setTemplateMode($oldTemplateMode);

        // Send the template back to the user.
        ob_start();
        echo $template;
        Craft::$app->end();
    }
}
