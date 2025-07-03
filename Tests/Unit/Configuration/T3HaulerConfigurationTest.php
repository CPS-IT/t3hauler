<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Configuration;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class T3HaulerConfigurationTest extends TestCase
{
    private T3HaulerConfiguration $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new T3HaulerConfiguration(
            ['/path/to/config'],
            ['/path/to/migrations']
        );
    }

    #[Test]
    public function getConfigurationPathsReturnsConfiguredPaths(): void
    {
        $result = $this->subject->getConfigurationPaths();

        self::assertSame(['/path/to/config'], $result);
    }

    #[Test]
    public function getMigrationPathsReturnsConfiguredPaths(): void
    {
        $result = $this->subject->getMigrationPaths();

        self::assertSame(['/path/to/migrations'], $result);
    }

    #[Test]
    public function getReturnsDefaultValueWhenConfigurationNotSet(): void
    {
        $result = $this->subject->get('nonexistent.path', 'default');

        self::assertSame('default', $result);
    }

    #[Test]
    public function getEnabledTablesReturnsDefaultWhenNotConfigured(): void
    {
        $result = $this->subject->getEnabledTables();

        self::assertSame(['pages', 'tt_content'], $result);
    }

    #[Test]
    public function getExcludedFieldsReturnsDefaultWhenNotConfigured(): void
    {
        $result = $this->subject->getExcludedFields();

        self::assertSame(['tstamp', 'crdate', 'cruser_id'], $result);
    }

    #[Test]
    public function getHashAlgorithmReturnsDefaultWhenNotConfigured(): void
    {
        $result = $this->subject->getHashAlgorithm();

        self::assertSame('sha256', $result);
    }

    #[Test]
    public function getAllReturnsEmptyArrayWhenNoConfigurationLoaded(): void
    {
        $result = $this->subject->getAll();

        self::assertSame([], $result);
    }

    #[Test]
    public function getStorageConfigReturnsEmptyArrayWhenNotConfigured(): void
    {
        $result = $this->subject->getStorageConfig();

        self::assertSame([], $result);
    }

    #[Test]
    public function getIntegrityConfigReturnsEmptyArrayWhenNotConfigured(): void
    {
        $result = $this->subject->getIntegrityConfig();

        self::assertSame([], $result);
    }

    #[Test]
    public function getDetectionConfigReturnsEmptyArrayWhenNotConfigured(): void
    {
        $result = $this->subject->getDetectionConfig();

        self::assertSame([], $result);
    }

    #[Test]
    public function getSiteConfigReturnsEmptyArrayWhenNotConfigured(): void
    {
        $result = $this->subject->getSiteConfig();

        self::assertSame([], $result);
    }
}
