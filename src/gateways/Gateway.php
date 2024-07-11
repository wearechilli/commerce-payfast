<?php

namespace wearechilli\payfast\gateways;

use Craft;
use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\helpers\App;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\web\Response as WebResponse;
use GuzzleHttp\Client;
use wearechilli\payfast\models\PaymentForm;
use wearechilli\payfast\responses\PaymentResponse;
use wearechilli\payfast\responses\CompletePaymentResponse;
use Throwable;
use yii\base\NotSupportedException;

/**
 * Gateway represents Payfast gateway
 *
 * @author Sten Van den Bergh <hello@stenvdb.be>
 * @since     1.0
 */
class Gateway extends BaseGateway {
    /**
     * @var string
     */
    public $merchantId;

    /**
     * @var string
     */
    public $merchantKey;

    /**
     * @var string
     */
    public $passphrase;

    /**
     * @var bool
     */
    public $testMode = true;

    public function getPaymentTypeOptions(): array
    {
        return [
            'purchase' => Craft::t('commerce', 'Purchase (Authorize and Capture Immediately)')
        ];
    }

    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('commerce-payfast/gatewaySettings', ['gateway' => $this]);
    }

    public static function displayName(): string
    {
        return Craft::t('commerce', 'Payfast');
    }

    public function getPaymentFormHtml(array $params)
    {
        return '';
    }

    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return '';
    }

    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        return '';
    }

    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        return '';
    }

    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        return new CompletePaymentResponse($transaction);
    }

    public function createPaymentSource(BasePaymentForm $sourceData, int $userId): PaymentSource
    {
        return '';
    }

    public function deletePaymentSource($token): bool
    {
        return '';
    }

    public function getPaymentFormModel(): BasePaymentForm
    {
        return new PaymentForm();
    }

    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        if (!$this->supportsPurchase()) {
            throw new NotSupportedException(Craft::t('commerce', 'Purchasing is not supported by this gateway'));
        }

        $order = $transaction->getOrder();
        $params = ['commerceTransactionId' => $transaction->id, 'commerceTransactionHash' => $transaction->hash];

        $data = [];

        // Merchant details
        $data['merchant_id'] = App::parseEnv($this->merchantId);
        $data['merchant_key'] = App::parseEnv($this->merchantKey);
        $data['return_url'] = UrlHelper::actionUrl('commerce/payments/complete-payment', $params);
        $data['cancel_url'] = UrlHelper::siteUrl($transaction->order->cancelUrl);
        if ($this->supportsWebhooks()) {
            $data['notify_url'] = $this->getWebhookUrl($params);
        }

        // Customer details
        $data['name_first'] = $order->billingAddress->firstName;
        $data['name_last'] = $order->billingAddress->lastName;
        $data['email_address'] = $order->email;

        // Transaction details
        $data['m_payment_id'] = $transaction->hash;
        $data['amount'] = $transaction->paymentAmount;
        $data['item_name'] = Craft::t('commerce', 'Order').' #'.$transaction->orderId;

        // Security signature
        $data['signature'] = $this->generateSignature($data);

        return new PaymentResponse($data, App::parseEnv($this->testMode));
    }

    protected function generateSignature($data)
    {
        $passPhrase = App::parseEnv($this->passphrase);
        // Create parameter string
        $pfOutput = '';
        foreach( $data as $key => $val ) {
            if($val !== '') {
                $pfOutput .= $key .'='. urlencode( trim( $val ) ) .'&';
            }
        }
        // Remove last ampersand
        $getString = substr( $pfOutput, 0, -1 );
        if( $passPhrase !== null ) {
            $getString .= '&passphrase='. urlencode( trim( $passPhrase ) );
        }
        return md5( $getString );
    }

    public function refund(Transaction $transaction): RequestResponseInterface
    {
        /*$client = new Client([
           'base_uri' => 'https://api.payfast.co.za/'
       ]);
       $data = [];
       $data['merchant-id'] = App::parseEnv('$PAYFAST_MERCHANT_ID');
       // Craft::dd(App::parseEnv('$PAYFAST_MERCHANT_ID'));
       $data['version'] = 'v1';
       $data['timestamp'] = date('Y-m-d\TH:i:s');
       $data['signature'] = $this->generateSignature($data);
       // Craft::dd($data);
       try {
           $response = $client->post('refunds/query/' . '1372965', [
               'form_params' => $data
           ]);

            // @TODO Check if response = 200, return RequestResponseInterface (see /responses)
       } catch (ClientException $e) {
           $response = $e->getResponse();
           $responseBodyAsString = $response->getBody()->getContents();
           Craft::dd($responseBodyAsString);
       }*/
    }

    public function processWebHook(): WebResponse
    {
        $response = Craft::$app->getResponse();
        $request = Craft::$app->getRequest();

        $transactionHash = $this->getTransactionHashFromWebhook();
        $transaction = Commerce::getInstance()->getTransactions()->getTransactionByHash($transactionHash);

        if (!$transaction) {
            Craft::warning('Transaction with the hash “'.$transactionHash.'“ not found.', 'commerce');
            $response->data = 'ok';

            return $response;
        }

        // Check to see if a successful purchase child transaction already exist and skip out early if they do
        $successfulPurchaseChildTransaction = TransactionRecord::find()->where([
            'parentId' => $transaction->id,
            'status' => TransactionRecord::STATUS_SUCCESS,
            'type' => TransactionRecord::TYPE_PURCHASE
        ])->count();

        if ($successfulPurchaseChildTransaction) {
            Craft::warning('Successful child transaction for “'.$transactionHash.'“ already exists.', 'commerce');
            $response->data = 'ok';

            return $response;
        }

        $rawData = $request->getBodyParams();
        $pfParamString = '';

        // Convert posted variables to a string
        foreach( $rawData as $key => $val ) {
            if( $key !== 'signature' ) {
                $pfParamString .= $key .'='. urlencode( $val ) .'&';
            } else {
                break;
            }
        }
        // Remove last ampersand
        $pfParamString = substr( $pfParamString, 0, -1 );

        $check1 = $this->pfValidSignature($rawData, $pfParamString);
        $check2 = $this->pfValidIP($request->referrer);
        $check3 = $this->pfValidPaymentData($transaction->paymentAmount, $rawData);
        $check4 = $this->pfValidServerConfirmation($pfParamString);

        if ($check1 && $check2 && $check3 && $check4) {
            // All checks have passed, the payment is successful

            $childTransaction = Commerce::getInstance()->getTransactions()->createTransaction(null, $transaction);
            $childTransaction->type = $transaction->type;

            if ($rawData['payment_status'] === 'COMPLETE') {
                $childTransaction->status = TransactionRecord::STATUS_SUCCESS;
            } else {
                $childTransaction->status = TransactionRecord::STATUS_FAILED;
            }

            $childTransaction->response = $rawData;
            Commerce::getInstance()->getTransactions()->saveTransaction($childTransaction);

            $response->data = 'ok';
            return $response;
        } else {
            return $response;
        }
    }

    public function supportsAuthorize(): bool
    {
        return false;
    }

    public function supportsCapture(): bool
    {
        return false;
    }

    public function supportsCompleteAuthorize(): bool
    {
        return false;
    }

    public function supportsCompletePurchase(): bool
    {
        return true;
    }

    public function supportsPaymentSources(): bool
    {
        return false;
    }

    public function supportsPurchase(): bool
    {
        return true;
    }

    public function supportsRefund(): bool
    {
        return false;
    }

    public function supportsPartialRefund(): bool
    {
        return true;
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }

    public function getTransactionHashFromWebhook()
    {
        return Craft::$app->getRequest()->getParam('commerceTransactionHash');
    }

    protected function pfValidSignature($pfData, $pfParamString)
    {
        $pfPassphrase = App::parseEnv($this->passphrase);

        // Calculate security signature
        if($pfPassphrase === null) {
            $tempParamString = $pfParamString;
        } else {
            $tempParamString = $pfParamString.'&passphrase='.urlencode( $pfPassphrase );
        }

        $signature = md5( $tempParamString );
        return ( $pfData['signature'] === $signature );
    }

    protected function pfValidIP($referrer) {
        // Variable initialization
        $validHosts = array(
            'www.payfast.co.za',
            'sandbox.payfast.co.za',
            'w1w.payfast.co.za',
            'w2w.payfast.co.za',
        );

        $validIps = [];

        foreach( $validHosts as $pfHostname ) {
            $ips = gethostbynamel( $pfHostname );

            if( $ips !== false )
                $validIps = array_merge( $validIps, $ips );
        }

        // Remove duplicates
        $validIps = array_unique( $validIps );
        $referrerIp = gethostbyname(parse_url($referrer)['host']);
        if( in_array( $referrerIp, $validIps, true ) ) {
            return true;
        }
        return false;
    }

    protected function pfValidPaymentData($cartTotal, $pfData)
    {
        return !(abs((float)$cartTotal - (float)$pfData['amount_gross']) > 0.01);
    }

    protected function pfValidServerConfirmation($pfParamString)
    {
        $pfHost = $this->testMode ? 'sandbox.payfast.co.za' : 'www.payfast.co.za';
        // Use cURL (if available)
        if( in_array( 'curl', get_loaded_extensions(), true ) ) {
            // Variable initialization
            $url = 'https://'. $pfHost .'/eng/query/validate';

            // Create default cURL object
            $ch = curl_init();

            // Set cURL options - Use curl_setopt for greater PHP compatibility
            // Base settings
            curl_setopt( $ch, CURLOPT_USERAGENT, NULL );  // Set user agent
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );      // Return output as string rather than outputting it
            curl_setopt( $ch, CURLOPT_HEADER, false );             // Don't include header in output
            curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );

            // Standard settings
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_POST, true );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $pfParamString );

            // Execute cURL
            $response = curl_exec( $ch );
            curl_close( $ch );
            if ($response === 'VALID') {
                return true;
            }
        }
        return false;
    }
}
