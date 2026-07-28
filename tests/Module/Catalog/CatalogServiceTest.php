<?php

namespace App\Tests\Module\Catalog;

use App\Module\Catalog\Entity\ProductCategory;
use App\Module\Catalog\Service\CatalogService;
use PHPUnit\Framework\TestCase;

class CatalogServiceTest extends TestCase
{
    private CatalogService $service;

    protected function setUp(): void
    {
        $this->service = new CatalogService();
    }

    public function testAssertCategoryDepthAllowsTopLevel(): void
    {
        // A category with no parent (top-level) is always valid
        $this->expectNotToPerformAssertions();
        $this->service->assertCategoryDepth(null);
    }

    public function testAssertCategoryDepthAllowsFirstLevel(): void
    {
        // A category whose parent has no parent (depth 1) is valid
        $parent = new ProductCategory();
        // parent has no parent → depth 0, child is depth 1 → OK
        $this->expectNotToPerformAssertions();
        $this->service->assertCategoryDepth($parent);
    }

    public function testAssertCategoryDepthRejectsSecondLevel(): void
    {
        // A category whose parent already has a parent would be depth 2 → rejected
        $grandparent = new ProductCategory();
        $parent = new ProductCategory();
        $parent->setParent($grandparent);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Categories support a maximum of 2 levels');
        $this->service->assertCategoryDepth($parent);
    }
}
