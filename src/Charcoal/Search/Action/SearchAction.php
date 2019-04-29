<?php

namespace Charcoal\Search\Action;

use Charcoal\App\Action\AbstractAction;
use Charcoal\Translator\TranslatorAwareTrait;
use Pimple\Container;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class SearchAction extends AbstractAction
{
    use TranslatorAwareTrait;

    /**
     * @var SearchService
     */
    protected $search;

    /**
     * @var array
     */
    protected $output;

    /**
     * @param Container $container Pimple\Container.
     * @return void
     */
    public function setDependencies(Container $container)
    {
        $this->search = $container['search'];
        $this->setTranslator($container['translator']);
        parent::setDependencies($container);
    }

    /**
     * @param RequestInterface  $request  A PSR-7 compatible Request instance.
     * @param ResponseInterface $response A PSR-7 compatible Response instance.
     * @return ResponseInterface
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParams();
        $keyword = $params['keyword'];

        $this->search->setKeyword($keyword);
        $this->search->setLang($this->translator()->getLocale());

        if (!$this->search->tableExists()) {
            return $response->withStatus(404);
        }

        $list = $this->search->search();

        $out = [];
        foreach ($list as $l) {
            $out[] = [
              'objType' => $l->objectType(),
              'objId' => $l->objectId(),
              'slug' => $l->slug()
            ];
        }

        $this->setOutput($out);
        return $response;
    }

    /**
     * @return array
     */
    public function output()
    {
        return $this->output;
    }

    /**
     * @param array $output
     * @return SearchAction
     */
    public function setOutput($output)
    {
        $this->output = $output;
        return $this;
    }

    /**
     * @return array
     */
    public function results()
    {
        return [
            'results' => $this->output()
        ];
    }
}
