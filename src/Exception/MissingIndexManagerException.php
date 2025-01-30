<?php

namespace Ellinaut\ElasticsearchConnector\Exception;

/**
 * @author Philipp Marien <philipp@ellinaut.dev>
 */
class MissingIndexManagerException extends \RuntimeException
{
    /**
     * MissingIndexManagerException constructor.
     * @param string $internalIndexName
     */
    public function __construct(string $internalIndexName)
    {
        parent::__construct('No index manager found for index "' . $internalIndexName . '".', 220120210001);
    }
}
