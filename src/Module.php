<?php

namespace Hermes\LosLog;

use Zend\Http\PhpEnvironment\Request;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\Mvc\MvcEvent;
use Hermes\Api\Client;

/**
 * @codeCoverageIgnore
 */
class Module implements AutoloaderProviderInterface
{
    public function onBootstrap(MvcEvent $e)
    {
        $serviceLocator = $e->getApplication()->getServiceManager();

        $hermes = $serviceLocator->get('hermes');

        $em = $hermes->getEventManager();
        $em->attach('request.post', function (Client $hermes) {
            $data = [];
            $request = $hermes->getZendClient()->getRequest();

            if (!$request->isGet()) {
                $post = json_decode($request->getContent(), true, 100);
                unset($post['password']);
                $data['data'] = $post;
            }
            \LosLog\Log\RequestLogger::save($request, $data);
        }, 100);
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array('namespaces' => array(
                __NAMESPACE__ => __DIR__ . '/src/',
            )),
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }
}
