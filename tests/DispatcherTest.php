<?php

namespace Shieldfy\Test;

use PHPUnit\Framework\TestCase;

use Shieldfy\Http\Dispatcher;
use Shieldfy\Http\ApiClient;
use Shieldfy\Config;

class DispatcherTest extends TestCase
{
    protected $config;

    public $api;

    public function setup()
    {
        $this->config = new Config();
        $this->config['app_key'] = 'testKey';
        $this->config['app_secret'] = 'testSecret';
        $this->config['endpoint'] = 'https://shieldfy.io';

        $this->api = $this->getMockBuilder(ApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock();

        $this->api->method('request')
                ->will($this->returnCallback(function($event,$data){
                    return [$event,$data];
                }));
    }

    public function testTriggerEvent()
    {
        $dispatcher = new Dispatcher($this->api);
        $res = $dispatcher->trigger('session',['user'=>'someuser']);
        $this->assertEquals(['/session','{"user":"someuser"}'],$res);
    }

    public function testFlush()
    {
        $dispatcher = new Dispatcher($this->api);
        $dispatcher->setData(['user'=>'anotheruser']);
        $res = $dispatcher->flush();
        $this->assertEquals(['/activity','{"user":"anotheruser"}'],$res);
    }

}
