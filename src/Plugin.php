<?php

namespace stenvdb\payfast;

use Craft;
use craft\commerce\services\Gateways;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\App;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use stenvdb\payfast\gateways\Gateway;
use yii\base\Event;


/**
 * Plugin represents the Payfast integration plugin.
 *
 * @author Sten Van den Bergh <hello@stenvdb.be>
 * @since  1.0
 */
class Plugin extends \craft\base\Plugin
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $client = new Client([
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
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();
            Craft::dd($responseBodyAsString);
        }

        Event::on(
            Gateways::class,
            Gateways::EVENT_REGISTER_GATEWAY_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = Gateway::class;
            }
        );
    }

    protected function generateSignature($data)
    {
        $passPhrase = App::parseEnv('$PAYFAST_PASSPHRASE');
        // Craft:dd($passPhrase);
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
}
