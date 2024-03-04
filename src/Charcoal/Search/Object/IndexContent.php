<?php

namespace Charcoal\Search\Object;

use Charcoal\Object\Content;

class IndexContent extends Content
{

    /**
     * @var string
     */
    protected $objectType;

    /**
     * @var mixed
     */
    protected $objectId;

    /**
     * Web page URL / Slug
     *
     * @var string
     */
    protected $slug;


    /**
     * Web page content
     *
     * @var string
     */
    protected $content;

    /**
     * Document title
     *
     * @var string
     */
    protected $title;

    /**
     * Description meta
     *
     * @var string
     */
    protected $description;

    /**
     * Web page lang
     *
     * @var string
     */
    protected $lang;

    /**
     * @param array|null $data Dependencies.
     */
    public function __construct(array $data = null)
    {
        parent::__construct($data);

        $defaultData = $this->metadata()->defaultData();
        if ($defaultData) {
            $this->setData($defaultData);
        }
    }

    /**
     * @return string
     */
    public function objectType()
    {
        return $this->objectType;
    }

    /**
     * @param string $objectType
     * @return IndexContent
     */
    public function setObjectType($objectType)
    {
        $this->objectType = $objectType;
        return $this;
    }

    /**
     * @return mixed
     */
    public function objectId()
    {
        return $this->objectId;
    }

    /**
     * @param mixed $objectId
     * @return IndexContent
     */
    public function setObjectId($objectId)
    {
        $this->objectId = $objectId;
        return $this;
    }


    /**
     * @return string
     */
    public function url()
    {
        return $this->url;
    }

    /**
     * @param string $url
     * @return IndexContent
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string
     */
    public function content()
    {
        return $this->content;
    }

    /**
     * @param string $content
     * @return IndexContent
     */
    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return string
     */
    public function title()
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return IndexContent
     */
    public function setTitle(?string $title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function description()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return IndexContent
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function slug()
    {
        return $this->slug;
    }

    /**
     * @param string $slug
     * @return IndexContent
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;
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
     * @return IndexContent
     */
    public function setLang($lang)
    {
        $this->lang = $lang;
        return $this;
    }
}
