<?php

namespace Ellinaut\ElasticsearchConnector\NameProvider;

/**
 * @author Philipp Marien <philipp@ellinaut.dev>
 */
class PrefixedNameProvider implements NameProviderInterface
{
    /**
     * @var string
     */
    private $prefix;

    /**
     * PrefixedIndexNameProvider constructor.
     * @param string $prefix
     */
    public function __construct(string $prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * @param string $internalName
     * @return string
     */
    public function provideExternalName(string $internalName): string
    {
        return $this->prefix . $internalName;
    }

    /**
     * @param string $externalName
     * @return string
     */
    public function provideInternalName(string $externalName): string
    {
        return substr($externalName, strlen($this->prefix) - 1);
    }
}
