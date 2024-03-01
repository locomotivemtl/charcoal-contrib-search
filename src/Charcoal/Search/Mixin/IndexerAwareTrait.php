<?php

namespace Charcoal\Search\Mixin;

use Charcoal\Search\Service\IndexerService;

trait IndexerAwareTrait
{
    protected $indexer;

    public function getIndexer(): IndexerService
    {
        return $this->indexer;
    }

    public function setIndexer(IndexerService $indexer)
    {
        $this->indexer = $indexer;
        return $this;
    }
}
