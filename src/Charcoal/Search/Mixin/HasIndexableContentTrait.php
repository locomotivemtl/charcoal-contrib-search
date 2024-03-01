<?php

namespace Charcoal\Search\Mixin;

use Charcoal\Search\Service\IndexerService;

/**
 * Trait HasIndexableContentTrait
 *
 * This trait provides methods for deleting and updating indexed content.
 * It requires the use of the IndexerAwareTrait.
 */
trait HasIndexableContentTrait
{
    use IndexerAwareTrait;

    public function deleteIndexedContent()
    {
        $this->getIndexer()->removeIndexedContentFromModel($this);
    }

    public function updateIndexedContent()
    {
        $this->getIndexer()->indexContentFromModel($this);
    }
}
