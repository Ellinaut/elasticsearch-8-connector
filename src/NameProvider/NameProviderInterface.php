<?php

namespace Ellinaut\ElasticsearchConnector\NameProvider;

/**
 * @author Philipp Marien <philipp@ellinaut.dev>
 */
interface NameProviderInterface
{
    /**
     * @param string $internalName
     * @return string
     */
    public function provideExternalName(string $internalName): string;

    /**
     * @param string $externalName
     * @return string
     */
    public function provideInternalName(string $externalName): string;
}
