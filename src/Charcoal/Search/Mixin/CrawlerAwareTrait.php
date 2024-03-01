<?php

namespace Charcoal\Search\Mixin;

use Charcoal\Search\Service\CrawlerService;

trait CrawlerAwareTrait
{
    protected $crawler;

    public function getCrawler()
    {
        return $this->crawler;
    }

    public function setCrawler(CrawlerService $crawler)
    {
        $this->crawler = $crawler;
        return $this;
    }
}
