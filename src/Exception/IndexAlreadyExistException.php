<?php

namespace Ellinaut\ElasticsearchConnector\Exception;

/**
 * @author Philipp Marien <philipp@ellinaut.dev>
 */
class IndexAlreadyExistException extends \RuntimeException
{
    /**
     * IndexAlreadyExistException constructor.
     * @param string $indexName
     */
    public function __construct(string $indexName)
    {
        parent::__construct('The index "' . $indexName . '" does already exist.', 220120210003);
    }
}
