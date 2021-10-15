<?php

namespace Charcoal\Search;

// from charcoal-app
use Charcoal\App\Module\AbstractModule;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Search Module
 */
class SearchModule extends AbstractModule
{
    const ADMIN_CONFIG = 'vendor/locomotivemtl/charcoal-contrib-search/config/admin.json';
    const APP_CONFIG = 'vendor/locomotivemtl/charcoal-contrib-search/config/config.json';

    /**
     * Setup the module's dependencies.
     *
     * @return AbstractModule
     */
    public function setup()
    {
        $container = $this->app()->getContainer();

        $this->setupPublicRoutes();

        $searchServiceProvider = new SearchServiceProvider();
        $container->register($searchServiceProvider);

        $searchConfig = $container['search/config'];
        $this->setConfig($searchConfig);

        return $this;
    }


    /**
     * @return void
     */
    private function setupPublicRoutes()
    {
        $config = [
            'route'      => '/search',
            'controller' => 'charcoal/search/action/search',
            'methods'    => ['GET'],
            'ident'      => 'charcoal/search/action/search'
        ];

        $container = $this->app()->getContainer();

        $this->app()->map($config['methods'], $config['route'], function (
            RequestInterface  $request,
            ResponseInterface $response,
            array             $args = []
        ) use (
            $config,
            $container
        ) {
            $routeController = $this['route/controller/action/class'];

            $route = $container['route/factory']->create($routeController, [
                'config' => $config,
                'logger' => $this['logger']
            ]);

            return $route($this, $request, $response);
        });
    }
}
