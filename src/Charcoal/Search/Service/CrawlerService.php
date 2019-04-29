<?php

namespace Charcoal\Search\Service;

use Charcoal\Sitemap\Mixin\SitemapBuilderAwareTrait;
use Charcoal\Source\ModelAwareTrait;
use GuzzleHttp\Client;

class CrawlerService
{
    use SitemapBuilderAwareTrait;
    use ModelAwareTrait;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * CrawlerService constructor.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->setSitemapBuilder($data['charcoal/sitemap/builder']);
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
     * @return CrawlerService
     */
    public function setBaseUrl($baseUrl)
    {
        $baseUrl       = rtrim($baseUrl, '/') . '/';
        $this->baseUrl = $baseUrl;
        return $this;
    }

    /**
     * Callable called on each objects
     *
     * @param string   $configIdent
     * @param callable $callable
     */
    public function crawl($configIdent, $callable)
    {
        $sitemap = $this->sitemapBuilder->build($configIdent);

        foreach ($sitemap as $map) {
            foreach ($map as $object) {
                $url = $object['url'];
                $res = $this->get($url);
                if ($res) {
                    if (is_callable($callable)) {
                        call_user_func($callable, $res, $object);
                    }
                }
            }
        }

    }

    /**
     * @param $url
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function get($url)
    {
        $url     = ltrim($url, '/');
        $client  = new Client();
        $baseUrl = $this->baseUrl();
        try {
            $res = $client->request('GET', $baseUrl . $url);
        } catch (\Exception $e) {
            return false;
        }

        if ($res->getStatusCode() !== 200) {
            return false;
        }

        return $res;
    }
}
