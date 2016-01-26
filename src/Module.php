<?php

namespace Hermes\LosLog;

use Hermes\Api\Client;
use Zend\EventManager\Event;
use Zend\Http\PhpEnvironment\Request;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\Mvc\MvcEvent;

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
            $request = $e->getRequest();

            $config = $serviceLocator->get('Config');
            $serviceName = $config['hermes']['service_name'] ?? '';
            $data = [
                'direction' => 'in',
                'status' => 'success',
                'destination' => [
                    'service' => $serviceName,
                    'server' => $request->getUri()->getHost(),
                    'uri' => $request->getUriString(),
                ],
                'source' => [
                    'server' => $_SERVER['REMOTE_ADDR'],
                    'service' => $request->getHeader('X-Request-Name') ? $request->getHeader('X-Request-Name')->getFieldValue() : '',
                    'uri' => '',
                ],
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
        $em->attach('request.post', function (Event $e) use ($serviceLocator) {
            /* @var \Hermes\Api\Client $hermes */
            $hermes = $e->getTarget();
            $request = $hermes->getZendClient()->getRequest();

            $config = $serviceLocator->get('Config');
            $serviceName = $config['hermes']['service_name'] ?? '';

            $data = [
                'direction' => 'out',
                'status' => 'success',
                'source' => [
                    'service' => $serviceName,
                    'server' => $_SERVER['SERVER_ADDR'],
                    'uri' => $_SERVER['REQUEST_URI'],
                ],
                'destination' => [
                    'service' => $hermes->getServiceName(),
                    'server' => $request->getUri()->getHost(),
                    'uri' => $request->getUriString(),
                ],
                'http_code' => $hermes->getZendClient()->getResponse()->getStatusCode(),
            ];

            if (!$request->isGet()) {
                $post = json_decode($request->getContent(), true, 100);
                unset($post['password']);
                $data['data'] = $post;
            }
            \LosLog\Log\RequestLogger::save($request, $data);
        }, 100);

        $em->attach('request.fail', function (Event $e) use ($serviceLocator) {
            /* @var \Hermes\Api\Client $hermes */
            $hermes = $e->getTarget();
            $request = $hermes->getZendClient()->getRequest();

            $config = $serviceLocator->get('Config');
            $serviceName = $config['hermes']['service_name'] ?? '';

            $data = [
                'direction' => 'out',
                'status' => 'failed',
                'source' => [
                    'service' => $serviceName,
                    'server' => $_SERVER['SERVER_ADDR'],
                    'uri' => $_SERVER['REQUEST_URI'],
                ],
                'destination' => [
                    'service' => $hermes->getServiceName(),
                    'server' => $request->getUri()->getHost(),
                    'uri' => $request->getUriString(),
                ],
            ];

            if (!$request->isGet()) {
                $post = json_decode($request->getContent(), true, 100);
                unset($post['password']);
                $data['data'] = $post;
            }

            $exception = $e->getParams();
            $data['http_code'] = $exception->getCode();
            $data['error'] = $exception->getMessage();
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
