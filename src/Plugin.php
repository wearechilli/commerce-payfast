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
}
