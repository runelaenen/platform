<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\CustomerWishlistNotActivatedException;
use Shopware\Core\Checkout\Customer\Exception\CustomerWishlistNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\WishlistProductNotFoundException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal (flag:FEATURE_NEXT_10549)
 *
 * @RouteScope(scopes={"store-api"})
 */
class RemoveWishlistProductRoute extends AbstractRemoveWishlistProductRoute
{
    /**
     * @var EntityRepositoryInterface
     */
    private $wishlistRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        EntityRepositoryInterface $wishlistRepository,
        EntityRepositoryInterface $productRepository,
        SystemConfigService $systemConfigService
    ) {
        $this->wishlistRepository = $wishlistRepository;
        $this->productRepository = $productRepository;
        $this->systemConfigService = $systemConfigService;
    }

    public function getDecorated(): AbstractRemoveWishlistProductRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.4.0")
     * @OA\Delete(
     *      path="/customer/wishlist/delete/{productId}",
     *      summary="Delete customer wishlist",
     *      operationId="deleteProductOnWishlist",
     *      tags={"Store API", "Wishlist"},
     *      @OA\Parameter(
     *        name="productId",
     *        in="path",
     *        description="Product Id",
     *        @OA\Schema(type="string"),
     *        required=true
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Success",
     *          @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
     *     )
     * )
     * @Route("/store-api/v{version}/customer/wishlist/delete/{productId}", name="store-api.customer.wishlist.delete", methods={"DELETE"})
     */
    public function delete(string $productId, SalesChannelContext $context): SuccessResponse
    {
        if (!$this->systemConfigService->get('core.cart.wishlistEnabled', $context->getSalesChannel()->getId())) {
            throw new CustomerWishlistNotActivatedException();
        }

        $customer = $context->getCustomer();
        if ($customer === null) {
            throw new CustomerNotLoggedInException();
        }

        $wishlistId = $this->getWishlistId($context, $customer);

        $wishlistProductId = $this->getWishlistProductId($wishlistId, $productId, $context);

        $this->productRepository->delete([
            [
                'id' => $wishlistProductId,
            ],
        ], $context->getContext());

        return new SuccessResponse();
    }

    private function getWishlistId(SalesChannelContext $context, CustomerEntity $customer): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('customerId', $customer->getId()),
            new EqualsFilter('salesChannelId', $customer->getSalesChannelId()),
        ]));

        $wishlistIds = $this->wishlistRepository->searchIds($criteria, $context->getContext());

        if ($wishlistIds->firstId() === null) {
            throw new CustomerWishlistNotFoundException();
        }

        return $wishlistIds->firstId();
    }

    private function getWishlistProductId(string $wishlistId, string $productId, SalesChannelContext $context): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('wishlistId', $wishlistId),
            new EqualsFilter('productId', $productId),
            new EqualsFilter('productVersionId', Defaults::LIVE_VERSION),
        ]));
        $wishlistProductIds = $this->productRepository->searchIds($criteria, $context->getContext());

        if ($wishlistProductIds->firstId() === null) {
            throw new WishlistProductNotFoundException($productId);
        }

        return $wishlistProductIds->firstId();
    }
}
