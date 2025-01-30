<?php

namespace Ellinaut\ElasticsearchConnector\Document;

/**
 * @author Philipp Marien <philipp@ellinaut.dev>
 */
interface DocumentMigratorInterface
{
    /**
     * @param array $previousSource
     * @return array
     */
    public function migrate(array $previousSource): array;
}
