<?php

namespace Charcoal\Search;

// local dependencies.
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
    }
}
