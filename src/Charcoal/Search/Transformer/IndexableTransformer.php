<?php

namespace Charcoal\Search\Transformer;

use Charcoal\Model\ModelInterface;

class IndexableTransformer
{
    /**
     * @param ModelInterface $model
     * @return array
     */
    public function __invoke(ModelInterface $model)
    {
        return [
            'id'      => $model['id'],
            'objType' => $model::objType(),
            'url'     => (string)$model->url(),
            'title'   => (string)$model['title'],
        ];
    }
}
