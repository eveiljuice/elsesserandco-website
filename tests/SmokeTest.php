<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testCatalogParseFiltersDefaults(): void
    {
        require_once __DIR__ . '/../includes/properties/catalog_query.php';
        $f = catalogParseFilters([]);
        $this->assertSame('sale', $f['category']);
        $this->assertSame(1, $f['page']);
    }

    public function testConfigHelperLoads(): void
    {
        $this->assertTrue(class_exists('Config'));
    }

    public function testCsrfJsonFileExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../includes/auth/csrf_json.php');
    }
}
