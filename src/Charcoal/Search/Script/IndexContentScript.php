<?php

namespace Charcoal\Search\Script;

use Charcoal\App\Script\AbstractScript as CharcoalScript;
use Charcoal\Loader\CollectionLoaderAwareTrait;
use Charcoal\Model\ModelFactoryTrait;
use Charcoal\Search\Object\IndexContent;
use Charcoal\Search\Service\CrawlerService;
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

    /**
     * @var Builder
     */
    protected $sitemap;

    /**
     * @var CrawlerService
     */
    protected $crawler;

    /**
     * @var string
     */
    protected $noIndexClass;

    /**
     * @var string
     */
    protected $indexElementId;

    /**
     * @param  Container $container The DI container.
     * @return void
     */
    public function setDependencies(Container $container)
    {
        $this->setModelFactory($container['model/factory']);
        $this->setCollectionLoader($container['model/collection/loader']);
        $this->setSitemapBuilder($container['charcoal/sitemap/builder']);
        $this->setCrawler($container['search/crawler']);

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
            'config'   => [
                'prefix'       => 'c',
                'longPrefix'   => 'config',
                'description'  => 'The sitemap builder key.',
                'defaultValue' => 'xml'
            ],
            'base_url' => [
                'prefix'      => 'u',
                'longPrefix'  => 'url',
                'description' => 'Base URL',
                'required'    => true
            ],
            'no_index_class' => [
                'prefix'      => 'n',
                'longPrefix'  => 'no_index_class',
                'description' => 'Class that help excluding content from the search index. Used mostly on navigations, header and footer.',
                'defaultValue' => 'php_no-index'
            ],
            'index_element_id' => [
                'prefix'      => 'i',
                'longPrefix'  => 'index_element_id',
                'description' => 'Id of the main element to be indexed. (Defaults to entire body)'
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

        $proto = $this->modelFactory()->create(IndexContent::class);

        if (!$proto->source()->tableExists()) {
            $this->createTable($proto);
            $proto->source()->dbQuery(
                strtr('ALTER TABLE `%table` ADD FULLTEXT (`content`)', [
                    '%table' => $proto->source()->table()
                ])
            );
        }

        $proto->source()->dbQuery(strtr(
            'DELETE FROM `%table`',
            [
                '%table' => $proto->source()->table()
            ]
        ));


        $baseUrl    = $this->climate()->arguments->get('base_url');
        $sitemapKey = $this->climate()->arguments->get('config');

        $this->setIndexElementId($this->climate()->arguments->get('index_element_id'));
        $this->setNoIndexClass($this->climate()->arguments->get('no_index_class'));

        $this->crawler()->setBaseUrl($baseUrl);

        $sitemap = $this->crawler()->crawl(
            $sitemapKey,
            [$this, 'indexContent'],
            [$this, 'errorIndexing']
        );

        return $response;
    }

    /**
     * @param $object
     */
    public function errorIndexing($object)
    {
        $this->climate()->red()->out(
            strtr(
                'Error crawling object <white>%type</white> - <white>%id</white> with URL <white>%url</white>',
                [
                    '%url'  => $object['url'],
                    '%id'   => $object['data']['id'],
                    '%type' => $object['data']['objType']
                ]
            )
        );
    }

    /**
     * @param $res
     * @param $object
     */
    public function indexContent($res, $object)
    {
        $url = ltrim($object['url'], '/');

        $this->climate()->green()->out(strtr(
            'Indexing page <white>%url</white> from object <white>%objectType</white> - <white>%objectId</white>',
            [
                '%url'        => $url,
                '%objectType' => $object['data']['objType'],
                '%objectId'   => $object['data']['id']
            ]
        ));

        $body = $res->getBody();

        // Get metas
        $doc = new \DOMDocument();
        // Prevents DOMDocument to assume HTML4
        libxml_use_internal_errors(true);
        $doc->loadHTML($body);
        libxml_use_internal_errors(false);
        $xpath = new \DOMXPath($doc);
        $nodes = $xpath->query('//head/meta');

        $description = '';
        foreach ($nodes as $node) {
            if ($node->getAttribute('name') === 'description') {
                $description = $node->getAttribute('content');
                break;
            }
        }

        // Do not search in script tags.
        foreach($xpath->query('//script') as $e ) {
            $e->parentNode->removeChild($e);
        }

        foreach($xpath->query(sprintf('//*[contains(attribute::class, "%s")]', $this->noIndexClass())) as $e ) {
            $e->parentNode->removeChild($e);
        }
        $doc->saveHTML($doc->documentElement);

        // Default behavior is to take the entire body
        if ($this->indexElementId()) {
            $main = $doc->getElementById($this->indexElementId());
        } else {
            $main = $doc->getElementsByTagName('body')[0];
        }

        $index = $this->modelFactory()->create(IndexContent::class);
        $content = preg_replace('/\s+/', ' ', $main->textContent);

        //
        $index->load($url);
        $index->setLang($object['lang']);
        $index->setObjectType($object['data']['objType']);
        $index->setObjectId($object['data']['id']);
        $index->setContent($content);

        $index->setDescription($description);

        if (!$index->id()) {
            $index->setSlug($url);
            $index->save();
        }
    }

    /**
     * Clean content from unnecessary content
     *
     * @param $content
     * @return null|string|string[]
     */
    private function cleanHtml($content)
    {
        $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $content);
        $html = preg_replace('#<style[^>]*?>.*?</style>#is', '', $html);
        $html = strip_tags($html);
        $html = preg_replace('!\s+!', ' ', $html);

        return $html;
    }

    /**
     * Creates table and adds index
     *
     * @param $proto
     */
    protected function createTable($proto)
    {
        $proto->source()->createTable();

        $q = strtr('ALTER TABLE `%table` ADD FULLTEXT(content)',
            [
                '%table' => $proto->source()->table()
            ]);

        $proto->source()->dbQuery($q);
    }

    /**
     * @return CrawlerService
     */
    public function crawler()
    {
        return $this->crawler;
    }

    /**
     * @param CrawlerService $crawler
     * @return IndexContentScript
     */
    public function setCrawler($crawler)
    {
        $this->crawler = $crawler;
        return $this;
    }

    /**
     * @return string
     */
    public function noIndexClass()
    {
        return $this->noIndexClass;
    }

    /**
     * @param string $noIndexClass
     * @return IndexContentScript
     */
    public function setNoIndexClass($noIndexClass)
    {
        $this->noIndexClass = $noIndexClass;
        return $this;
    }

    /**
     * @return string
     */
    public function indexElementId()
    {
        return $this->indexElementId;
    }

    /**
     * @param string $indexElementId
     * @return IndexContentScript
     */
    public function setIndexElementId($indexElementId)
    {
        $this->indexElementId = $indexElementId;
        return $this;
    }

}
