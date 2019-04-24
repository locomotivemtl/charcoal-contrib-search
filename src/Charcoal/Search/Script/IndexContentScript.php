<?php

namespace Charcoal\Search\Script;

use Charcoal\Loader\CollectionLoaderAwareTrait;
use Charcoal\Model\ModelFactoryTrait;
use Charcoal\Search\Object\IndexContent;
use Charcoal\Sitemap\Mixin\SitemapBuilderAwareTrait;
use GuzzleHttp\Client;
use Pimple\Container;

// From 'charcoal-app'
use Charcoal\App\Script\AbstractScript as CharcoalScript;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 */
class IndexContentScript extends CharcoalScript
{
    use ModelFactoryTrait;
    use CollectionLoaderAwareTrait;
    use SitemapBuilderAwareTrait;

    /**
     * @var Builder
     */
    protected $sitemap;

    /**
     * @var mixed
     */
    protected $baseUrl;

    /**
     * @param  Container $container The DI container.
     * @return void
     */
    public function setDependencies(Container $container)
    {
        $this->setModelFactory($container['model/factory']);
        $this->setCollectionLoader($container['model/collection/loader']);
        $this->setSitemapBuilder($container['charcoal/sitemap/builder']);
        $this->baseUrl = $container['base-url'];

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
                'prefix' => 'c',
                'longPrefix'  => 'config',
                'description' => 'The sitemap builder key.',
                'defaultValue' => 'xml'
            ]
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
        unset($request);

        $sitemap   = $this->sitemapBuilder->build('xml');

        foreach ($sitemap as $map) {
            foreach ($map as $object) {
                $url = $object['url'];

                $client = new Client();
                $res = $client->request('GET', $url);

                if ($res->getStatusCode() === 200) {
                    $index = $this->modelFactory()->create(IndexContent::class);
                    $index->setLang($object['lang']);
                    $index->setSlug($url);
                    $index->setObjectType($object['data']['objType']);
                    $index->setObjectId($object['data']['objId']);
                    $index->setContent($res->getBody());
                }
            }
        }

        return $response;
    }
}
