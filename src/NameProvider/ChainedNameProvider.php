<?php

namespace Ellinaut\ElasticsearchConnector\NameProvider;

/**
 * @author Philipp Marien <philipp@ellinaut.dev>
 */
class ChainedNameProvider implements NameProviderInterface
{
    /**
     * @var NameProviderInterface[]
     */
    private $indexNameProviderChain;

    /**
     * ChainedIndexNameProvider constructor.
     * @param NameProviderInterface[] $indexNameProviderChain
     */
    public function __construct(array $indexNameProviderChain)
    {
        $this->indexNameProviderChain = $indexNameProviderChain;
    }

    /**
     * @param string $internalName
     * @return string
     */
    public function provideExternalName(string $internalName): string
    {
        $externalName = $internalName;
        foreach ($this->indexNameProviderChain as $indexNameProvider) {
            $externalName = $indexNameProvider->provideExternalName($externalName);
        }

        return $externalName;
    }

    /**
     * @param string $externalName
     * @return string
     */
    public function provideInternalName(string $externalName): string
    {
        $internalName = $externalName;
        foreach ($this->indexNameProviderChain as $indexNameProvider) {
            $internalName = $indexNameProvider->provideExternalName($internalName);
        }

        return $internalName;
    }
}
