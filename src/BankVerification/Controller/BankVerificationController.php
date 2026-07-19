<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\BankVerification\Controller;

use Kommandhub\FlutterwaveV3SW\BankVerification\Service\BankValidationFactory;
use Kommandhub\FlutterwaveV3SW\Client\FlutterwaveClient;
use Kommandhub\FlutterwaveV3SW\Logging\ConfigurableLogger;
use Kommandhub\FlutterwaveV3SW\Setting\Service\Config;
use Kommandhub\FlutterwaveV3SW\Util\FlutterwaveConstants;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Storefront endpoints backing the customer bank-profile form.
 *
 * Improvements over the Paystack equivalent:
 * - Talks to Flutterwave through the typed {@see FlutterwaveClient} rather than
 *   issuing raw HTTP from the controller, so the endpoint, auth and error-body
 *   handling live in one tested place.
 * - Emits structured, sales-channel-scoped logs (the Paystack controller logged
 *   nothing), while never logging the account number or BVN.
 * - Normalises Flutterwave's response shape to a stable `{status: bool, data}`
 *   contract, so the storefront JS is decoupled from provider specifics.
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class BankVerificationController extends StorefrontController
{
    public function __construct(
        private readonly FlutterwaveClient $flutterwave,
        private readonly Config $config,
        private readonly EntityRepository $customerRepository,
        private readonly BankValidationFactory $bankValidationFactory,
        private readonly DataValidator $validator,
        private readonly ConfigurableLogger $logger
    ) {
    }

    /**
     * Lists the banks Flutterwave supports for the configured country.
     */
    #[Route(
        path: '/flutterwave/bank/list',
        name: 'frontend.flutterwave.bank.list',
        defaults: ['XmlHttpRequest' => true, PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: ['GET']
    )]
    public function getBanks(SalesChannelContext $context): JsonResponse
    {
        $salesChannelId = $context->getSalesChannelId();

        if (!$this->isBankDataCollectionEnabled($salesChannelId)) {
            return new JsonResponse(['status' => false, 'message' => 'Feature disabled'], Response::HTTP_NOT_FOUND);
        }

        try {
            $response = $this->flutterwave->banks()->list($this->bankCountry($salesChannelId), $salesChannelId);
            $data = is_array($response['data'] ?? null) ? $response['data'] : [];

            return new JsonResponse(['status' => true, 'data' => array_values($data)]);
        } catch (\Throwable $e) {
            $this->logger->error('[Flutterwave] Failed to load banks.', [
                ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => $salesChannelId,
                'exception' => $e,
            ]);

            return new JsonResponse(['status' => false, 'message' => 'Unable to load banks.'], Response::HTTP_BAD_GATEWAY);
        }
    }

    /**
     * Resolves an account number to its holder name via Flutterwave.
     */
    #[Route(
        path: '/flutterwave/bank/verify',
        name: 'frontend.flutterwave.bank.verify',
        defaults: ['XmlHttpRequest' => true, PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: ['POST']
    )]
    public function verifyAccount(Request $request, SalesChannelContext $context): JsonResponse
    {
        $salesChannelId = $context->getSalesChannelId();

        if (!$this->isBankDataCollectionEnabled($salesChannelId)) {
            return new JsonResponse(['status' => false, 'message' => 'Feature disabled'], Response::HTTP_NOT_FOUND);
        }

        $accountNumber = trim((string)$request->request->get('account_number'));
        $bankCode = trim((string)$request->request->get('bank_code'));

        if ($accountNumber === '' || $bankCode === '') {
            return new JsonResponse(['status' => false, 'message' => 'Missing parameters'], Response::HTTP_BAD_REQUEST);
        }

        // Flutterwave's sandbox resolves only against bank code 044; any other is
        // rejected outright. Send the sandbox bank so verification works in test
        // mode — the customer's real selection is persisted separately on save.
        $resolveBankCode = $this->config->isSandbox($salesChannelId)
            ? FlutterwaveConstants::SANDBOX_ACCOUNT_BANK
            : $bankCode;

        try {
            $response = $this->flutterwave->banks()->resolveAccount($accountNumber, $resolveBankCode, $salesChannelId);
            $data = is_array($response['data'] ?? null) ? $response['data'] : [];
            $accountName = is_string($data['account_name'] ?? null) ? $data['account_name'] : null;

            if (($response['status'] ?? null) !== 'success' || $accountName === null || $accountName === '') {
                return new JsonResponse([
                    'status' => false,
                    'message' => is_string($response['message'] ?? null) ? $response['message'] : 'Account verification failed.',
                ], Response::HTTP_BAD_REQUEST);
            }

            return new JsonResponse(['status' => true, 'data' => ['account_name' => $accountName]]);
        } catch (\Throwable $e) {
            // Never log the account number — it is customer financial data.
            $this->logger->error('[Flutterwave] Account resolution failed.', [
                ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => $salesChannelId,
                'bankCode' => $bankCode,
                'exception' => $e,
            ]);

            return new JsonResponse(['status' => false, 'message' => 'Verification service unavailable.'], Response::HTTP_BAD_GATEWAY);
        }
    }

    /**
     * Persists the verified bank profile onto the customer record.
     */
    #[Route(
        path: '/flutterwave/bank/save',
        name: 'frontend.flutterwave.bank.save',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: ['POST']
    )]
    public function saveBank(RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        if (!$this->isBankDataCollectionEnabled($context->getSalesChannelId())) {
            throw $this->createNotFoundException();
        }

        $validation = $this->bankValidationFactory->create($context);
        $violations = $this->validator->getViolations($data->all(), $validation);

        if ($violations->count() > 0) {
            $this->addFlash(StorefrontController::DANGER, $this->trans('kommandhub-flutterwave.bank.saveError'));

            foreach ($violations as $violation) {
                $this->addFlash(StorefrontController::DANGER, $violation->getMessage());
            }

            return $this->redirectToRoute('frontend.account.profile.page');
        }

        $customFields = [
            FlutterwaveConstants::CUSTOMER_FIELD_BANK_NAME => $data->get('bankName'),
            FlutterwaveConstants::CUSTOMER_FIELD_BANK_CODE => $data->get('bankCode'),
            FlutterwaveConstants::CUSTOMER_FIELD_ACCOUNT_NUMBER => $data->get('accountNumber'),
            FlutterwaveConstants::CUSTOMER_FIELD_ACCOUNT_NAME => $data->get('accountName'),
        ];

        $bvn = $data->get('bvn');

        if (is_string($bvn) && $bvn !== '') {
            $customFields[FlutterwaveConstants::CUSTOMER_FIELD_BVN] = $bvn;
        }

        $this->customerRepository->update([
            [
                'id' => $customer->getId(),
                'customFields' => $customFields,
            ],
        ], $context->getContext());

        $this->addFlash(StorefrontController::SUCCESS, $this->trans('kommandhub-flutterwave.bank.saveSuccess'));

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    /**
     * ISO 3166-1 alpha-2 country whose bank list is shown. Defaults to Nigeria,
     * the market where 10-digit accounts and BVN apply; overridable per channel.
     */
    private function bankCountry(?string $salesChannelId): string
    {
        $country = strtoupper($this->config->getString('bankCountry', $salesChannelId));

        return $country !== '' ? $country : 'NG';
    }

    private function isBankDataCollectionEnabled(?string $salesChannelId): bool
    {
        return $this->config->getBool('collectBankData', $salesChannelId);
    }
}
