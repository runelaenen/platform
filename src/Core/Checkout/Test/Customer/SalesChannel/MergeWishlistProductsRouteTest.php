<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class MergeWishlistProductsRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var object|null
     */
    private $customerRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $customerId;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var object|null
     */
    private $wishlistProductRepository;

    protected function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10549', $this);
        $this->context = Context::createDefaultContext();
        $this->ids = new TestDataCollection($this->context);

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);
        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $this->wishlistProductRepository = $this->getContainer()->get('customer_wishlist_product.repository');

        $email = Uuid::randomHex() . '@example.com';
        $this->customerId = $this->createCustomer('shopware', $email);

        /* @var SystemConfigService $systemConfigService */
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $this->systemConfigService->set('core.cart.wishlistEnabled', true);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/login',
                [
                    'email' => $email,
                    'password' => 'shopware',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $response['contextToken']);
    }

    public function testMergeProductShouldReturnSuccessNoWishlistExisted(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10549', $this);
        $productData = $this->createProduct($this->context);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/customer/wishlist/merge',
                [
                    'productIds' => [
                        'id' => $productData,
                    ],
                ]
            );
        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertSame($productData, $wishlistProduct->getEntities()->first()->getProductId());
    }

    public function testMergeTwoProductShouldReturnSuccessNoWishlistExisted(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10549', $this);

        $productDataOne = $this->createProduct($this->context);
        $productDataTwo = $this->createProduct($this->context);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/customer/wishlist/merge',
                [
                    'productIds' => [
                        $productDataOne, $productDataTwo,
                    ],
                ]
            );
        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertSame(2, $wishlistProduct->getEntities()->count());
    }

    public function testMergeThreeProductShouldReturnSuccessNoWishlistExisted(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10549', $this);
        $productDataOne = $this->createProduct($this->context);
        $productDataTwo = $this->createProduct($this->context);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/customer/wishlist/merge',
                [
                    'productIds' => [
                        $productDataOne,
                        $productDataTwo,
                        Uuid::randomHex(),
                    ],
                ]
            );
        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertSame(2, $wishlistProduct->getEntities()->count());
    }

    public function testMergeProductShouldThrowCustomerNotLoggedInException(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10549', $this);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', Random::getAlphanumericString(12));

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/customer/wishlist/merge',
                [
                    'productIds' => [
                        'id' => Uuid::randomHex(),
                    ],
                ]
            );
        $response = json_decode($this->browser->getResponse()->getContent(), true);
        $errors = $response['errors'][0];
        static::assertSame(403, $this->browser->getResponse()->getStatusCode());
        static::assertEquals('CHECKOUT__CUSTOMER_NOT_LOGGED_IN', $errors['code']);
        static::assertEquals('Forbidden', $errors['title']);
        static::assertEquals('Customer is not logged in.', $errors['detail']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertNull($wishlistProduct->getEntities()->first());
    }

    public function testMergeProductShouldThrowCustomerWishlistNotActivatedException(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10549', $this);
        $this->systemConfigService->set('core.cart.wishlistEnabled', false);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/customer/wishlist/merge',
                [
                    'productIds' => [
                        'id' => Uuid::randomHex(),
                    ],
                ]
            );
        $response = json_decode($this->browser->getResponse()->getContent(), true);
        $errors = $response['errors'][0];
        static::assertSame(403, $this->browser->getResponse()->getStatusCode());
        static::assertEquals('CHECKOUT__WISHLIST_IS_NOT_ACTIVATED', $errors['code']);
        static::assertEquals('Forbidden', $errors['title']);
        static::assertEquals('Wishlist is not activated!', $errors['detail']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertNull($wishlistProduct->getEntities()->first());
    }

    public function testMergeProductShouldSuccessWithNoProductInsert(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10549', $this);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/customer/wishlist/merge',
                [
                    'productIds' => [
                        'id' => Uuid::randomHex(),
                    ],
                ]
            );
        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertNull($wishlistProduct->getEntities()->first());
    }

    public function testMergeProductShouldReturnSuccessAlreadyWishlistExisted(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10549', $this);
        $productData = $this->createProduct($this->context);
        $this->createCustomerWishlist($productData);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/customer/wishlist/merge',
                [
                    'productIds' => [
                        'id' => $productData,
                    ],
                ]
            );
        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertSame($productData, $wishlistProduct->getEntities()->first()->getProductId());
    }

    public function testMergeProductShouldReturnSuccessAlreadyProductWishlistExisted(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10549', $this);
        $alreadyProductData = $this->createProduct($this->context);
        $this->createCustomerWishlist($alreadyProductData);
        $newProductData = $this->createProduct($this->context);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/customer/wishlist/merge',
                [
                    'productIds' => [
                        'id' => $newProductData,
                    ],
                ]
            );
        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertSame(2, $wishlistProduct->getEntities()->count());
    }

    public function testMergeProductShouldReturnSuccessSameProductWishlistExisted(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10549', $this);
        $alreadyProductData = $this->createProduct($this->context);
        $this->createCustomerWishlist($alreadyProductData);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/customer/wishlist/merge',
                [
                    'productIds' => [
                        'id' => $alreadyProductData,
                    ],
                ]
            );
        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertSame(1, $wishlistProduct->getEntities()->count());
        static::assertSame($alreadyProductData, $wishlistProduct->getEntities()->first()->getProductId());
    }

    private function createProduct(Context $context): string
    {
        $productId = Uuid::randomHex();
        $data = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'Test Product',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.99, 'net' => 11.99, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'taxId' => $this->getValidTaxId(),
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => $this->getSalesChannelApiSalesChannelId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $this->getContainer()->get('product.repository')->create([$data], $context);

        return $productId;
    }

    private function createCustomerWishlist(string $productId): string
    {
        $customerWishlistId = Uuid::randomHex();
        $customerWishlistRepository = $this->getContainer()->get('customer_wishlist.repository');

        $customerWishlistRepository->create([
            [
                'id' => $customerWishlistId,
                'customerId' => $this->customerId,
                'salesChannelId' => $this->getSalesChannelApiSalesChannelId(),
                'products' => [
                    [
                        'productId' => $productId,
                    ],
                ],
            ],
        ], $this->context);

        return $customerWishlistId;
    }
}
