<?php

namespace Charcoal\Search\Service;

use Charcoal\Search\Object\IndexContent;
use Charcoal\Sitemap\Mixin\SitemapBuilderAwareTrait;
use GuzzleHttp\Client;

class IndexerService
{
    use SitemapBuilderAwareTrait;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * IndexerService constructor.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->setSitemapBuilder($container['charcoal/sitemap/builder']);
    }

    /**
     * @return string
     */
    public function baseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * @param string $baseUrl
     * @return IndexerService
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        return $this;
    }

    /**
     * Key param is the key to the sitemap you want to index
     *
     * @param $sitemapKey
     * @param $callable
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function index($sitemapKey, $callable)
    {
        $sitemap = $this->sitemapBuilder()->build($sitemapKey);

        // Make sure there's a slash at the end of the given URL
        $baseUrl = rtrim($this->baseUrl(), '/') . '/';

        $proto = $this->modelFactory()->create(IndexContent::class);
        $proto->source()->dbQuery(strtr(
            'DELETE FROM `%table`',
            [
                '%table' => $proto->source()->table()
            ]
        ));

        foreach ($sitemap as $map) {
            foreach ($map as $object) {
                $url = ltrim($object['url'], '/');

                $this->climate()->green()->out(strtr(
                    'Indexing page <white>%url</white> from object <white>%objectType</white> - ' +
                    '<white>%objectId</white>',
                    [
                        '%url'        => $url,
                        '%objectType' => $object['data']['objType'],
                        '%objectId'   => $object['data']['id']
                    ]
                ));

                $client = new Client();
                $res    = $client->request('GET', $baseUrl . $url);

                if ($res->getStatusCode() === 200) {
                    $index = $this->modelFactory()->create(IndexContent::class);

                    $content = $this->cleanHtml($res->getBody());

                    $index->load($url);

                    $index->setLang($object['lang']);
                    $index->setObjectType($object['data']['objType']);
                    $index->setObjectId($object['data']['id']);
                    $index->setContent($content);

                    if (!$index->id()) {
                        $index->setSlug($url);
                        $index->save();
                    }
                }
            }
        }
    }
}
