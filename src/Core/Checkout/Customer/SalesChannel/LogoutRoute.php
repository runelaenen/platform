<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class LogoutRoute extends AbstractLogoutRoute
{
    /**
     * @var SalesChannelContextPersister
     */
    private $contextPersister;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SystemConfigService
     */
    private $systemConfig;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        SalesChannelContextPersister $contextPersister,
        EventDispatcherInterface $eventDispatcher,
        SystemConfigService $systemConfig,
        CartService $cartService,
        Connection $connection
    ) {
        $this->contextPersister = $contextPersister;
        $this->eventDispatcher = $eventDispatcher;
        $this->systemConfig = $systemConfig;
        $this->cartService = $cartService;
        $this->connection = $connection;
    }

    public function getDecorated(): AbstractLogoutRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Post(
     *      path="/account/logout",
     *      summary="Logouts current loggedin customer",
     *      operationId="logoutCustomer",
     *      tags={"Store API", "Account"},
     *      @OA\Response(
     *          response="200",
     *          description=""
     *     )
     * )
     * @Route(path="/store-api/v{version}/account/logout", name="store-api.account.logout", methods={"POST"})
     */
    public function logout(SalesChannelContext $context, ?RequestDataBag $data = null)
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $salesChannelId = $context->getSalesChannel()->getId();
        if ($this->systemConfig->get('core.loginRegistration.invalidateSessionOnLogOut', $salesChannelId)) {
            $this->cartService->deleteCart($context);
            $this->deleteContextToken($context->getToken());
        } else {
            $newToken = Random::getAlphanumericString(32);

            if ($data && (bool) $data->get('replace-token')) {
                $newToken = $this->contextPersister->replace($context->getToken(), $context);
            }

            $context->assign([
                'token' => $newToken,
            ]);
        }

        $event = new CustomerLogoutEvent($context, $context->getCustomer());
        $this->eventDispatcher->dispatch($event);

        return new ContextTokenResponse($context->getToken());
    }

    /**
     * @deprecated tag:v6.4.0 use \Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister::delete
     */
    private function deleteContextToken(string $token): void
    {
        $this->connection->executeUpdate(
            'DELETE FROM sales_channel_api_context WHERE token = :token',
            [
                'token' => $token,
            ]
        );
    }
}
