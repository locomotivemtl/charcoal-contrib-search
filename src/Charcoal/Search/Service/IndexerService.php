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

    protected $noIndexClass;
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
            $res = $this->getCrawler()->get($model->url($locale));
            if (!$res) {
                continue;
            }
            $this->indexContent($res, [
                'url'     => $model->url($locale),
                'lang'    => $locale,
                'objType' => $model::objType(),
                'objId'   => $model->id()
            ]);
        }
    }

    /**
     * @param $res
     * @param $object ['url' => 'https://...', 'lang' => 'fr', 'objType' => '', 'objId' => '']
     */
    public function indexContent(ResponseInterface $res, $object)
    {
        $url = ltrim($object['url'], '/');

        if (!$url) {
            $url = '/';
        }

        $body = $res->getBody();

        // Getting meta tags
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true); // Prevents DOMDocument to assume HTML4
        $doc->loadHTML($body);
        libxml_use_internal_errors(false);
        $xpath     = new \DOMXPath($doc);
        $nodes     = $xpath->query('//head/meta');
        $titleNode = $doc->getElementsByTagName('title')[0];

        $title = '';
        if ($titleNode) {
            $title = $titleNode->textContent;
        }

        $description = '';
        foreach ($nodes as $node) {
            if ($node->getAttribute('name') === 'description') {
                $description = $node->getAttribute('content');
                break;
            }
        }

        // Do not search in script tags.
        foreach ($xpath->query('//script') as $e) {
            $e->parentNode->removeChild($e);
        }

        foreach ($xpath->query(sprintf('//*[contains(attribute::class, "%s")]', $this->noIndexClass())) as $e) {
            $e->parentNode->removeChild($e);
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

        $content = preg_replace('/\s+/', ' ', $main->textContent);

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
                    '%table' => $model->source()->table()
                ]
            )
        );

        $model->source()->dbQuery(
            strtr(
                'ALTER TABLE `%table` ADD FULLTEXT (`title`)',
                [
                    '%table' => $model->source()->table()
                ]
            )
        );

        $model->source()->dbQuery(
            strtr(
                'ALTER TABLE `%table` ADD FULLTEXT (`description`)',
                [
                    '%table' => $model->source()->table()
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
            '%id'    => $model->id()
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
        foreach ($values as $content)
        {
            $model = $this->modelFactory()->create($content['objectType'])->load($content['objectId']);
            if (!$model->id() || !($model instanceof RoutableInterface) || !$model->isActiveRoute()) {
                $content->delete();
            }
        }
    }
}
