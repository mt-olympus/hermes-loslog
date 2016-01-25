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

        $e->getApplication()->getEventManager()->attach(\Zend\Mvc\MvcEvent::EVENT_DISPATCH, function (MvcEvent $e) use ($serviceLocator) {
            if (!$e->getRequest() instanceof Request) {
                return;
            }
            $config = $serviceLocator->get('Config');
            $serviceName = $config['hermes']['service_name'] ?? '';
            $data = [
                'service' => $serviceName,
                'server' => $_SERVER['SERVER_ADDR'],
                'source_ip' => $_SERVER['REMOTE_ADDR'],
            ];
            if (!$e->getRequest()->isGet()) {
                $post = json_decode($e->getRequest()->getContent(), true, 100);
                unset($post['password']);
                $data['data'] = $post;
            }
            \LosLog\Log\RequestLogger::save($e->getRequest(), $data);
        }, 100);

        $hermes = $serviceLocator->get('hermes');

        $em = $hermes->getEventManager();
        $em->attach('request.post', function (Client $hermes) {
            exit;
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
                __NAMESPACE__ => __DIR__ . '/',
            )),
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }
}
