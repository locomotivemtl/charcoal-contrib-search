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
     * @param callable $success
     * @param callable $failure
     */
    public function crawl($configIdent, $success, $failure)
    {
        $this->sitemapBuilder->setBaseUrl($this->baseUrl());
        $sitemap = $this->sitemapBuilder->build($configIdent);

        foreach ($sitemap as $map) {
            foreach ($map as $object) {
                $url = ltrim($object['url'], '/');
                $res = $this->get($url);
                if ($res) {
                    if (is_callable($success)) {
                        call_user_func($success, $res, $object);
                    }
                } else {
                    if (is_callable($failure)) {
                        call_user_func($failure, $object);
                    }
                }
            }
        }

    }

    /**
     * @param $url
     * @return bool|mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function get($url)
    {
        $url     = ltrim($url, '/');
        $client  = new Client();

        if (null === parse_url($url, PHP_URL_SCHEME)) {
            $url = $this->baseUrl() . $url;
        }

        try {
            $res = $client->request('GET', $url);
        } catch (\Exception $e) {
            return false;
        }

        if ($res->getStatusCode() !== 200) {
            return false;
        }

        return $res;
    }
}
