<?php

namespace wearechilli\payfast;

use Craft;
use craft\commerce\services\Gateways;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\App;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use wearechilli\payfast\gateways\Gateway;
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
