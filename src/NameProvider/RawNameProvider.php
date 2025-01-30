<?php

namespace Ellinaut\ElasticsearchConnector\NameProvider;

/**
 * @author Philipp Marien <philipp@ellinaut.dev>
 */
class RawNameProvider implements NameProviderInterface
{
    /**
     * @param string $internalName
     * @return string
     */
    public function provideExternalName(string $internalName): string
    {
        return $internalName;
    }

    /**
     * @param string $externalName
     * @return string
     */
    public function provideInternalName(string $externalName): string
    {
        return $externalName;
    }
}
