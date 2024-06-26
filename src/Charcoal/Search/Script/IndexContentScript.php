<?php

namespace Charcoal\Search\Script;

use Charcoal\App\Script\AbstractScript as CharcoalScript;
use Charcoal\Loader\CollectionLoaderAwareTrait;
use Charcoal\Model\ModelFactoryTrait;
use Charcoal\Search\Mixin\CrawlerAwareTrait;
use Charcoal\Search\Mixin\IndexerAwareTrait;
use Charcoal\Search\Object\IndexContent;
use Charcoal\Sitemap\Mixin\SitemapBuilderAwareTrait;
use Pimple\Container;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 */
class IndexContentScript extends CharcoalScript
{
    use ModelFactoryTrait;
    use CollectionLoaderAwareTrait;
    use SitemapBuilderAwareTrait;
    use CrawlerAwareTrait;
    use IndexerAwareTrait;

    /**
     * @var Builder
     */
    protected $sitemap;

    /**
     * @var string
     */
    protected $noIndexClass;

    /**
     * @var string
     */
    protected $indexElementId;


    /**
     * @param Container $container The DI container.
     * @return void
     */
    public function setDependencies(Container $container)
    {
        $this->setModelFactory($container['model/factory']);
        $this->setCollectionLoader($container['model/collection/loader']);
        $this->setSitemapBuilder($container['charcoal/sitemap/builder']);
        $this->setCrawler($container['search/crawler']);
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
            'config' => [
                'prefix'       => 'c',
                'longPrefix'   => 'config',
                'description'  => 'The sitemap builder key.',
                'defaultValue' => 'xml',
            ],

            'base_url' => [
                'prefix'      => 'u',
                'longPrefix'  => 'url',
                'description' => 'Base URL',
                'required'    => true,
            ],

            'no_index_class' => [
                'prefix'       => 'n',
                'longPrefix'   => 'no_index_class',
                'description'  => 'Class that help excluding content from the search index. ' .
                    'Used mostly on navigations, header and footer.',
                'defaultValue' => 'php-no_index',
            ],

            'index_element_id' => [
                'prefix'      => 'i',
                'longPrefix'  => 'index_element_id',
                'description' => 'Id of the main element to be indexed. (Defaults to entire body)',
            ],
        ];

        $arguments = array_merge(parent::defaultArguments(), $arguments);
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
        $this->getIndexer()->checkIndexContentTableExistance(); // Make sure the table exists with the proper values
        $this->getIndexer()->deleteAllIndexes();

        $baseUrl    = $this->climate()->arguments->get('base_url');
        $sitemapKey = $this->climate()->arguments->get('config');

        $this->getIndexer()->setIndexElementId($this->climate()->arguments->get('index_element_id'));
        $this->getIndexer()->setNoIndexClass($this->climate()->arguments->get('no_index_class'));

        $this->getCrawler()->setBaseUrl($baseUrl);

        $this->getCrawler()->crawl(
            $sitemapKey,
            [$this, 'indexContent'],
            [$this, 'errorIndexing']
        );

        return $response;
    }

    /**
     * @param $res
     * @param $object
     * @return void
     */
    public function errorIndexing($res, $object)
    {
        $out = strtr(
            'Could not index [<white>%objType</white>:<white>%objId</white>] with URL: <white>%url</white> with status: <white>%status</white>',
            [
                '%objId'   => ($object['data']['id'] ?? null),
                '%objType' => ($object['data']['objType'] ?? null),
                '%url'     => ($object['url'] ?? null),
                '%status'  => $res->getStatusCode()
            ]
        );

        $this->climate()->red()->out($out);
    }

    /**
     * @param $res
     * @param $object
     * @return void
     */
    public function failedIndexing($object, $errorMsg)
    {
        $out = strtr(
            'Could not index [<white>%objType</white>:<white>%objId</white>] with URL: <white>%url</white> with status: <white>%status</white>',
            [
                '%objId'   => ($object['data']['id'] ?? null),
                '%objType' => ($object['data']['objType'] ?? null),
                '%url'     => ($object['url'] ?? null),
                '%status'  => 'No status'
            ]
        );

        $this->climate()->red()->out($out);
        $this->climate()->red()->out($errorMsg);
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $res
     * @param array<string, mixed>                $object
     */
    public function indexContent($res, $object)
    {
        if (
            !isset($object['url'], $object['data']) ||
            !isset($object['data']['id'], $object['data']['objType'])
        ) {
            $this->errorIndexing($object, 'Invalid object format');
            return;
        }

        $url = ltrim($object['url'], '/');
        if (!$url) {
            $url = '/';
        }

        $data = [
            'url'     => $url,
            'lang'    => ($object['lang'] ?? null),
            'objId'   => $object['data']['id'],
            'objType' => $object['data']['objType'],
        ];

        try {
            $this->getIndexer()->indexContent($res, $data);
            $this->climate()->green()->out(strtr(
                'Indexing [<white>%objType</white>:<white>%objId</white>] with URL <white>%url</white>',
                [
                    '%objId'   => $data['objId'],
                    '%objType' => $data['objType'],
                    '%url'     => $data['url'],
                ]
            ));
        } catch (\Exception $e) {
            $this->failedIndexing($object, $e->getMessage());
        }
    }
}
