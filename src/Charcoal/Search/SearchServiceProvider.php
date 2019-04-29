<?php

namespace Charcoal\Search;

// local dependencies.
use Charcoal\Search\Service\CrawlerService;
use Charcoal\Search\Service\SearchService;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

// from pimple

/**
 * Search Service Provider
 */
class SearchServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container Pimple DI container.
     * @return void
     */
    public function register(Container $container)
    {
        /**
         * @return SearchConfig
         */
        $container['search/config'] = function () {
            return new SearchConfig();
        };

        /**
         * @return SearchConfig
         */
        $container['search'] = function ($container) {
            return new SearchService([
                'model/factory' => $container['model/factory'],
                'model/collection/loader' => $container['model/collection/loader']
            ]);
        };

        /**
         * @return CrawlerService
         */
        $container['search/crawler'] = function ($container) {
            return new CrawlerService([
                'charcoal/sitemap/builder' => $container['charcoal/sitemap/builder']
            ]);
        };
    }
}
