<?php

namespace Charcoal\Search\Service;

use Charcoal\Loader\CollectionLoaderAwareTrait;
use Charcoal\Model\ModelFactoryTrait;
use Charcoal\Search\Object\IndexContent;

class SearchService
{
    use ModelFactoryTrait;
    use CollectionLoaderAwareTrait;

    /**
     * @var string
     */
    protected $keyword;

    /**
     * @var string
     */
    protected $lang;

    /**
     * SearchService constructor.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->setModelFactory($data['model/factory']);
        $this->setCollectionLoader($data['model/collection/loader']);
    }

    /**
     * @return bool
     */
    public function tableExists()
    {
        return $this->modelFactory()->get(IndexContent::class)->source()->tableExists();
    }

    /**
     * @return \ArrayAccess|\Charcoal\Model\ModelInterface[]
     */
    public function search()
    {
        $proto = $this->modelFactory()->create(IndexContent::class);

        $q = 'SELECT *,
            MATCH(content) AGAINST(:keyword IN NATURAL LANGUAGE  MODE) as rank
            FROM
            `%table`
            WHERE
            MATCH(content) AGAINST(:keyword IN NATURAL LANGUAGE  MODE)
            AND lang = :lang
            ORDER BY rank
        ';
        $q = strtr($q, [
            '%table' => $proto->source()->table()
        ]);

        $binds = [
            'lang' => $this->lang(),
            'keyword' => $this->keyword()
        ];

        $list = $this->collectionLoader()->setModel($proto)->loadFromQuery([$q, $binds]);

        return $list;
    }

    /**
     * @return string
     */
    public function keyword()
    {
        return $this->keyword;
    }

    /**
     * @param string $keyword
     * @return SearchService
     */
    public function setKeyword($keyword)
    {
        $this->keyword = $keyword;
        return $this;
    }

    /**
     * @return string
     */
    public function lang()
    {
        return $this->lang;
    }

    /**
     * @param string $lang
     * @return SearchService
     */
    public function setLang($lang)
    {
        $this->lang = $lang;
        return $this;
    }
}
