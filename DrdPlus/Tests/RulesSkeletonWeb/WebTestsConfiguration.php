<?php
declare(strict_types=1);

namespace DrdPlus\Tests\RulesSkeletonWeb;

use Granam\Strict\Object\StrictObject;
use Granam\YamlReader\YamlFileReader;

class WebTestsConfiguration extends StrictObject
{
    public const HAS_TABLES = 'has_tables';
    public const SOME_EXPECTED_TABLE_IDS = 'some_expected_table_ids';
    public const HAS_TABLE_OF_CONTENTS = 'has_table_of_contents';

    public static function createFromYaml(string $yamlConfigFile)
    {
        return new static((new YamlFileReader($yamlConfigFile))->getValues());
    }

    /** @var bool */
    private $hasTables = true;
    /** @var array|string[] */
    private $someExpectedTableIds = [];
    /** @var bool */
    private $hasTableOfContents = true;

    /**
     * @param array $values
     * @throws \DrdPlus\Tests\RulesSkeletonWeb\Exceptions\InvalidLocalUrl
     * @throws \DrdPlus\Tests\RulesSkeletonWeb\Exceptions\InvalidPublicUrl
     * @throws \DrdPlus\Tests\RulesSkeletonWeb\Exceptions\PublicUrlShouldUseHttps
     */
    public function __construct(array $values)
    {
        $this->setHasTables($values);
        $this->setSomeExpectedTableIds($values, $this->hasTables());
        $this->setHasTableOfContents($values);
    }

    /**
     * @param array $values
     */
    private function setHasTables(array $values): void
    {
        $this->hasTables = (bool)($values[self::HAS_TABLES] ?? $this->hasTables);
    }

    /**
     * @param array $values
     * @param bool $hasTables
     * @throws \DrdPlus\Tests\RulesSkeletonWeb\Exceptions\InvalidSomeExpectedTableIdsTestsConfiguration
     */
    private function setSomeExpectedTableIds(array $values, bool $hasTables): void
    {
        if (!$hasTables) {
            $this->someExpectedTableIds = [];

            return;
        }
        $someExpectedTableIds = $values[self::SOME_EXPECTED_TABLE_IDS] ?? null;
        if (!\is_array($someExpectedTableIds)) {
            throw new Exceptions\InvalidSomeExpectedTableIdsTestsConfiguration(
                "Expected some '" . self::SOME_EXPECTED_TABLE_IDS . "', got "
                . ($someExpectedTableIds === null
                    ? 'nothing'
                    : \var_export($someExpectedTableIds, true)
                )
            );
        }
        $structureOk = true;
        foreach ($someExpectedTableIds as $someExpectedTableId) {
            if (!\is_string($someExpectedTableId)) {
                $structureOk = false;
                break;
            }
        }
        if (!$structureOk) {
            throw new Exceptions\InvalidSomeExpectedTableIdsTestsConfiguration(
                "Expected flat array of strings for '" . self::SOME_EXPECTED_TABLE_IDS . "', got "
                . \var_export($someExpectedTableIds, true)
            );
        }
        $this->someExpectedTableIds = $someExpectedTableIds;
    }

    private function setHasTableOfContents(array $values): void
    {
        $this->hasTableOfContents = (bool)($values[self::HAS_TABLE_OF_CONTENTS] ?? true);
    }

    public function hasTables(): bool
    {
        return $this->hasTables;
    }

    public function getSomeExpectedTableIds(): array
    {
        return $this->someExpectedTableIds;
    }

    public function hasTableOfContents(): bool
    {
        return $this->hasTableOfContents;
    }

}