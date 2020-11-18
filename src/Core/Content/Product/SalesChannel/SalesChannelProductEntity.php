<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CalculatedListingPrice;

class SalesChannelProductEntity extends ProductEntity
{
    /**
     * @deprecated tag:v6.4.0 - Will be removed
     */
    public const VISIBILITY_FILTERED = 'product-visibility';

    /**
     * @var CalculatedListingPrice
     */
    protected $calculatedListingPrice;

    /**
     * @var PriceCollection
     */
    protected $calculatedPrices;

    /**
     * @var CalculatedPrice
     */
    protected $calculatedPrice;

    /**
     * @var PropertyGroupCollection|null
     */
    protected $sortedProperties;

    /**
     * @var bool
     */
    protected $isNew = false;

    /**
     * @var int
     */
    protected $calculatedMaxPurchase;

    /**
     * @deprecated tag:v6.4.0 - Only used for backward compatibility
     *
     * @var PropertyGroupCollection|null
     */
    protected $configurator;

    /**
     * @var CategoryEntity|null
     */
    protected $seoCategory;

    public function getCalculatedListingPrice(): CalculatedListingPrice
    {
        return $this->calculatedListingPrice;
    }

    public function setCalculatedListingPrice(CalculatedListingPrice $calculatedListingPrice): void
    {
        $this->calculatedListingPrice = $calculatedListingPrice;
    }

    public function setCalculatedPrices(PriceCollection $prices): void
    {
        $this->calculatedPrices = $prices;
    }

    public function getCalculatedPrices(): PriceCollection
    {
        return $this->calculatedPrices;
    }

    public function getCalculatedPrice(): CalculatedPrice
    {
        return $this->calculatedPrice;
    }

    public function setCalculatedPrice(CalculatedPrice $calculatedPrice): void
    {
        $this->calculatedPrice = $calculatedPrice;
    }

    public function getSortedProperties(): ?PropertyGroupCollection
    {
        return $this->sortedProperties;
    }

    public function setSortedProperties(?PropertyGroupCollection $sortedProperties): void
    {
        $this->sortedProperties = $sortedProperties;
    }

    public function hasPriceRange(): bool
    {
        return $this->getCalculatedListingPrice()->hasRange() || $this->getCalculatedPrices()->count() > 1;
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function setIsNew(bool $isNew): void
    {
        $this->isNew = $isNew;
    }

    public function getCalculatedMaxPurchase(): int
    {
        return $this->calculatedMaxPurchase;
    }

    public function setCalculatedMaxPurchase(int $calculatedMaxPurchase): void
    {
        $this->calculatedMaxPurchase = $calculatedMaxPurchase;
    }

    /**
     * @deprecated tag:v6.4.0 - Only used for backward compatibility
     */
    public function getConfigurator(): ?PropertyGroupCollection
    {
        return $this->configurator;
    }

    /**
     * @deprecated tag:v6.4.0 - Only used for backward compatibility
     */
    public function setConfigurator(PropertyGroupCollection $configurator): void
    {
        $this->configurator = $configurator;
    }

    public function getSeoCategory(): ?CategoryEntity
    {
        return $this->seoCategory;
    }

    public function setSeoCategory(?CategoryEntity $category): void
    {
        $this->seoCategory = $category;
    }
}
