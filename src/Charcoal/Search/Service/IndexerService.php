<?php

namespace Charcoal\Search\Service;

use Charcoal\Loader\CollectionLoaderAwareTrait;
use Charcoal\Model\ModelFactoryTrait;
use Charcoal\Object\RoutableInterface;
use Charcoal\Search\Mixin\CrawlerAwareTrait;
use Charcoal\Search\Object\IndexContent;
use Charcoal\Translator\TranslatorAwareTrait;
use Psr\Http\Message\ResponseInterface;

class IndexerService
{
    use CrawlerAwareTrait;
    use TranslatorAwareTrait;
    use ModelFactoryTrait;
    use CollectionLoaderAwareTrait;

    protected $siteConfig;

    /**
     * @var string
     */
    protected $baseUrl;

    protected $noIndexClass = 'php-no_index';
    protected $indexElementId;

    /**
     * IndexerService constructor.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->setCrawler($data['search/crawler']);
        $this->setTranslator($data['translator']);
        $this->setModelFactory($data['model/factory']);
        $this->setCollectionLoader($data['model/collection/loader']);
        if (isset($data['base-url']) || !empty($data['base-url'])) {
            $this->setBaseUrl($data['base-url']);
        }
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
     * @return string
     */
    public function noIndexClass()
    {
        return $this->noIndexClass;
    }

    /**
     * @param string $noIndexClass
     * @return self
     */
    public function setNoIndexClass(string $noIndexClass)
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
     * @return self
     */
    public function setIndexElementId(string $indexElementId)
    {
        $this->indexElementId = $indexElementId;
        return $this;
    }


    /**
     * Allows the creation of an entry with the content of a specific model
     *
     * @param RoutableInterface $model
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function indexContentFromModel(RoutableInterface $model)
    {
        foreach ($this->translator()->availableLocales() as $locale) {
            $url = rtrim($this->baseUrl(), '/') . '/' . ltrim($model->url($locale), '/');
            $res = $this->getCrawler()->get($url);
            if (!$res) {
                continue;
            }
            $this->indexContent($res, [
                'url'     => $model->url($locale),
                'lang'    => $locale,
                'objType' => $model::objType(),
                'objId'   => $model->id(),
            ]);
        }
    }

    /**
     * @param ?\Psr\Http\Message\ResponseInterface                             $res
     * @param array{url:string, lang:string, objId:int|string, objType:string} $object
     */
    public function indexContent(ResponseInterface $res, $object)
    {
        $url = ltrim($object['url'], '/');
        if (!$url) {
            $url = '/';
        }

        $body = (string)$res->getBody();

        $docImp  = new \DOMImplementation();
        $docType = $docImp->createDocumentType('html');

        $doc = $docImp->createDocument(null, '', $docType);
        $doc->encoding = 'UTF-8';

        libxml_use_internal_errors(true); // Prevents DOMDocument to assume HTML4
        $doc->loadHTML($body);
        libxml_use_internal_errors(false);

        $xpath = new \DOMXPath($doc);

        // Getting meta tags
        $titleNode = $doc->getElementsByTagName('title')[0];
        $title     = '';
        if ($titleNode) {
            $title = trim($titleNode->textContent);
        }

        $metaNodes   = $xpath->query('//head/meta');
        $description = '';
        foreach ($metaNodes as $metaNode) {
            if ($metaNode->getAttribute('name') === 'description') {
                $description = trim($metaNode->getAttribute('content'));
                break;
            }
        }

        // Do not search in script tags.
        foreach ($xpath->query('//script') as $node) {
            $node->parentNode->removeChild($node);
        }

        if (!empty($this->noIndexClass())) {
            foreach ($xpath->query(sprintf('//*[contains(attribute::class, "%s")]', $this->noIndexClass())) as $node) {
                $node->parentNode->removeChild($node);
            }
        }
        $doc->saveHTML($doc->documentElement);

        // Default behavior is to take the entire body
        if ($this->indexElementId()) {
            $main = $doc->getElementById($this->indexElementId());
        } else {
            $main = $doc->getElementsByTagName('body')[0];
        }

        if (!$main) {
            // Error
            // If indexElementId() -> the element ID doesnt exist on the page
            // Else, no body tag found
            return;
        }

        $model = $this->modelFactory()->create($object['objType'])->load($object['objId']);
        $index = $this->getIndexedContentFromModel($model);

        $content = preg_replace('/\s+/u', ' ', trim($main->textContent));

        // Save indexed content to DB
        $index->setLang($object['lang']);
        $index->setObjectType($object['objType']);
        $index->setObjectId($object['objId']);
        $index->setContent($content);
        $index->setTitle($title);
        $index->setDescription($description);
        $index->setSlug($url);

        if (!$index->id()) {
            $index->save();
        } else {
            $index->update();
        }
    }

    /**
     * Make sure the IndexContent model table exists
     * Add index accordingly
     *
     * @return void
     */
    public function checkIndexContentTableExistance()
    {
        $model = $this->modelFactory()->create(IndexContent::class);

        // Table already exists
        if ($model->source()->tableExists()) {
            return;
        }

        // Create table
        $model->source()->createTable();

        // Add fulltext index on all separate column in order to allow a weighted search
        // Normal search should only occur on `content` as it is the entire indexable textual
        // content of the page and should therefore have the title in it.
        $q = $model->source()->dbQuery(
            strtr(
                'ALTER TABLE `%table` ADD FULLTEXT(`content`)',
                [
                    '%table' => $model->source()->table(),
                ]
            )
        );

        $model->source()->dbQuery(
            strtr(
                'ALTER TABLE `%table` ADD FULLTEXT (`title`)',
                [
                    '%table' => $model->source()->table(),
                ]
            )
        );

        $model->source()->dbQuery(
            strtr(
                'ALTER TABLE `%table` ADD FULLTEXT (`description`)',
                [
                    '%table' => $model->source()->table(),
                ]
            )
        );
    }

    /**
     * @param RoutableInterface $model
     * @return void
     */
    public function removeIndexedContentFromModel(RoutableInterface $model)
    {
        $model = $this->getIndexedContentFromModel($model);
        if ($model->id()) {
            $model->delete();
        }
    }

    /**
     * Retrieves indexed content from a model.
     * IndexContent will either be an entry from the database or an empty entry
     *
     * @param mixed $model The model object from which to retrieve the indexed content.
     * @return IndexContent The indexed content object.
     */
    public function getIndexedContentFromModel($model)
    {
        $index = $this->modelFactory()->create(IndexContent::class);

        // Check if content already exists in the table
        $q = 'SELECT * FROM `%table` WHERE object_type = \'%type\' AND object_id = \'%id\'';
        $q = strtr($q, [
            '%table' => $index->source()->table(),
            '%type'  => $model->objType(),
            '%id'    => $model->id(),
        ]);

        $index->loadFromQuery($q);

        return $index;
    }

    /**
     * Cleanup outdated content indexes.
     *
     * This method removes outdated content indexes from the collection.
     * An index is considered outdated if the associated model is not found,
     * or the model is not an instance of RoutableInterface,
     * or the model's route is not active.
     *
     * @return void
     */
    public function cleanupOutdatedContentIndexes()
    {
        $values = $this->collectionLoader()->setModel(IndexContent::class)->load()->values();
        foreach ($values as $content) {
            $model = $this->modelFactory()->create($content['objectType'])->load($content['objectId']);
            if (!$model->id() || !($model instanceof RoutableInterface) || !$model->isActiveRoute()) {
                $content->delete();
            }
        }
    }

    /**
     * Deletes all indexes from the database.
     *
     * @return void
     */
    public function deleteAllIndexes()
    {
        $model = $this->modelFactory()->create(IndexContent::class);
        if (!$model->source()->tableExists()) {
            return ;
        }

        $q = strtr('DELETE FROM `%table`', [
            '%table' => $model->source()->table(),
        ]);

        $model->source()->dbQuery($q);
    }
}
