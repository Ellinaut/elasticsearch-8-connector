<?php

namespace Ellinaut\ElasticsearchConnector\NameProvider;

/**
 * @author Philipp Marien <philipp@ellinaut.dev>
 */
class SuffixedNameProvider implements NameProviderInterface
{
    /**
     * @var string
     */
    private $suffix;

    /**
     * SuffixedIndexNameProvider constructor.
     * @param string $suffix
     */
    public function __construct(string $suffix)
    {
        $this->suffix = $suffix;
    }

    /**
     * @param string $internalName
     * @return string
     */
    public function provideExternalName(string $internalName): string
    {
        return $internalName . $this->suffix;
    }

    /**
     * @param string $externalName
     * @return string
     */
    public function provideInternalName(string $externalName): string
    {
        return substr($externalName, 0, strlen($externalName) - strlen($this->suffix));
    }
}
