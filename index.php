<?php

$autoloader = require __DIR__ . '/vendor/autoload.php';

$tinyFrame = new \bdk\TinyFrame(array(
    'cache' => function () {
        $kvs = new \bdk\SimpleCache\Adapters\Filesystem(__DIR__ . '/cache');
        return new \bdk\SimpleCache\SimpleCachePlus($kvs);
    },
    'config' => array(
        // 'templates' => array(
        //     'default' => __DIR__.'/template.html',
        // ),
        'controllerNamespace' => 'JiraTime\\controllers',
    ),
    'debug' => function ($c) {
        return new \bdk\Debug(array(
            /*
            'collect' => true,
            'output' => true,
            'onBootstrap' => function (\bdk\PubSub\Event $event) use ($c) {
                $debug = $event->getSubject();
                if ($debug->getCfg('collect') && $_SERVER['REMOTE_ADDR'] == '127.0.0.1') {
                    $wampPublisher = new \bdk\WampPublisher(array('realm' => 'debug'));
                    if ($wampPublisher->connected) {
                        $outputWamp = new \bdk\Debug\Route\Wamp($debug, $wampPublisher);
                        $debug->setCfg('route', $outputWamp);
                        // $debug->addPlugin($outputWamp);
                    }
                }
            }
            */
        ));
    },
    'routes' => array(
        'worklog' => array('{/action}', 'Worklog', 'defaults' => array(
            'action' => 'index',
        )),
    ),
    'jiraConfig' => array(
        'jiraLogFile' => __DIR__ . '/jira.log',
    ),
));

$tinyFrame->run();
