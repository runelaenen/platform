<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Wishlist;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistEntity;
use Shopware\Core\Checkout\Customer\Exception\CustomerWishlistNotFoundException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoadWishlistRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LoadWishlistRouteResponse;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class WishlistPageLoader
{
    private const LIMIT = 24;

    private const DEFAULT_PAGE = 1;

    /**
     * @var GenericPageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AbstractLoadWishlistRoute
     */
    private $wishlistLoadRoute;

    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        AbstractLoadWishlistRoute $wishlistLoadRoute,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->genericLoader = $genericLoader;
        $this->wishlistLoadRoute = $wishlistLoadRoute;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $context): WishlistPage
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $criteria = $this->createCriteria($request);

        $page = $this->genericLoader->load($request, $context);
        $page = WishlistPage::createFrom($page);

        try {
            $page->setWishlist($this->wishlistLoadRoute->load($request, $context, $criteria));
        } catch (CustomerWishlistNotFoundException $exception) {
            $page->setWishlist(
                new LoadWishlistRouteResponse(
                    new CustomerWishlistEntity(),
                    new EntitySearchResult(
                        0,
                        new ProductCollection(),
                        null,
                        $criteria,
                        $context->getContext()
                    )
                )
            );
        }

        $this->eventDispatcher->dispatch(
            new WishlistPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function createCriteria(Request $request): Criteria
    {
        $limit = (int) $request->query->get('limit', self::LIMIT);
        $page = (int) $request->query->get('p', self::DEFAULT_PAGE);
        $offset = $limit * ($page - 1);

        return (new Criteria())
            ->addAssociation('manufacturer')
            ->addAssociation('options.group')
            ->setLimit($limit)
            ->setOffset($offset)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
    }
}
