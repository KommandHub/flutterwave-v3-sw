<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Webhook\Controller;

use Kommandhub\FlutterwaveSW\Logging\ConfigurableLogger;
use Kommandhub\FlutterwaveSW\Webhook\Service\WebhookProcessor;
use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/*
 * The `storefront` scope keeps the endpoint reachable without Shopware
 * authentication — Flutterwave cannot present an API token. Authenticity is
 * established by the verif-hash header instead.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['storefront']])]
class WebhookController extends AbstractController
{
    public function __construct(
        private readonly WebhookProcessor $webhookProcessor,
        private readonly ConfigurableLogger $logger
    ) {
    }

    /**
     * Receives Flutterwave webhooks, and the per-refund `callbackurl` callbacks
     * that share the same payload shape.
     *
     * Flutterwave requires a 200 to consider a delivery successful — "any other
     * response codes, including 3xx codes, will be treated as a failure" — so a
     * handled event returns 200, not 204.
     *
     * @see https://developer.flutterwave.com/v3.0.0/docs/webhooks
     *
     * Failure codes are chosen for retry behaviour:
     *  - 403 for a bad/absent hash: never retry, the caller is not Flutterwave.
     *  - 400 for a malformed body: never retry, a redelivery is byte-identical
     *    and would fail the same way.
     *  - 500 for an unexpected fault: DO retry, the delivery was probably fine
     *    and the failure is ours (a database blip, a gateway timeout).
     */
    #[Route(
        path: '/flutterwave/webhook',
        name: 'frontend.flutterwave.webhook',
        methods: [Request::METHOD_POST]
    )]
    public function execute(Request $request, Context $context): Response
    {
        try {
            $this->webhookProcessor->process($request, $context);

            return new JsonResponse(['status' => 'success'], Response::HTTP_OK);
        } catch (AccessDeniedHttpException $exception) {
            $this->logger->warning('[Flutterwave] Webhook signature validation failed.', [
                'message' => $exception->getMessage(),
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(['status' => 'error', 'message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        } catch (BadRequestHttpException $exception) {
            $this->logger->warning('[Flutterwave] Invalid webhook payload received.', [
                'message' => $exception->getMessage(),
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(['status' => 'error', 'message' => 'Bad request'], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            $this->logger->error('[Flutterwave] Webhook processing failed.', [
                'exception' => $exception,
            ]);

            return new JsonResponse(
                ['status' => 'error', 'message' => 'Internal server error'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
