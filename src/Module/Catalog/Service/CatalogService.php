<?php

namespace App\Module\Catalog\Service;

use App\Module\Catalog\Entity\ProductCategory;
use App\Module\Catalog\Repository\ProductRepository;

class CatalogService
{
    public function __construct(
        private ?ProductRepository $productRepository = null,
    ) {}

    /**
     * Throws if $proposedParent would push category depth beyond 2 levels.
     * Top-level (parent=null) = depth 0. One level under = depth 1. Max allowed.
     */
    public function assertCategoryDepth(?ProductCategory $proposedParent): void
    {
        if ($proposedParent === null) {
            return;
        }
        if ($proposedParent->getParent() !== null) {
            throw new \InvalidArgumentException('Categories support a maximum of 2 levels (parent → child). Cannot create a subcategory under a subcategory.');
        }
    }

    /**
     * Throws if another active product in the same location already uses this SKU.
     * Pass $excludeId when updating an existing product to exclude itself.
     */
    public function assertSkuUnique(string $sku, int $locationId, ?int $excludeId = null): void
    {
        if ($this->productRepository === null) {
            return;
        }
        $existing = $this->productRepository->createQueryBuilder('p')
            ->where('p.sku = :sku')
            ->andWhere('IDENTITY(p.location) = :locationId')
            ->setParameter('sku', $sku)
            ->setParameter('locationId', $locationId)
            ->getQuery()
            ->getResult();

        foreach ($existing as $product) {
            if ($excludeId === null || $product->getId() !== $excludeId) {
                throw new \InvalidArgumentException(sprintf('SKU "%s" is already in use at this location.', $sku));
            }
        }
    }
}
