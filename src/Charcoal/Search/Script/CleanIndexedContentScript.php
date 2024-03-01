<?php

namespace Charcoal\Search\Script;

use Charcoal\App\Script\AbstractScript as CharcoalScript;
use Charcoal\Loader\CollectionLoaderAwareTrait;
use Charcoal\Model\ModelFactoryTrait;
use Charcoal\Search\Mixin\CrawlerAwareTrait;
use Charcoal\Search\Mixin\IndexerAwareTrait;
use Charcoal\Search\Object\IndexContent;
use Charcoal\Search\Service\CrawlerService;
use Charcoal\Sitemap\Mixin\SitemapBuilderAwareTrait;
use Pimple\Container;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 */
class CleanIndexedContentScript extends CharcoalScript
{
    Use IndexerAwareTrait;

    /**
     * @param Container $container The DI container.
     * @return void
     */
    public function setDependencies(Container $container)
    {
        $this->setIndexer($container['search/indexer']);

        parent::setDependencies($container);
    }

    /**
     * Valid arguments:
     * - file : path/to/csv.csv
     *
     * @return array
     */
    public function defaultArguments()
    {
        $arguments = [
        ];
        return $arguments;
    }

    /**
     * Gets a psr7 request and response and returns a response.
     *
     * Called from `__invoke()` as the first thing.
     *
     * @param RequestInterface  $request  A PSR-7 compatible Request instance.
     * @param ResponseInterface $response A PSR-7 compatible Response instance.
     * @return ResponseInterface
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        unset($request);
        $this->getIndexer()->cleanupOutdatedContentIndexes();

        return $response;
    }
}
