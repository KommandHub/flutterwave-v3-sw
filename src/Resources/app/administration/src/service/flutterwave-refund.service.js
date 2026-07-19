const { Classes } = Shopware;

/**
 * Thin API client for the plugin's admin refund endpoints. Mirrors the Paystack
 * refund service; the endpoints differ per plugin.
 */
class FlutterwaveRefundService extends Classes.ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'flutterwave') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'flutterwaveRefundService';
    }

    /**
     * Issues a refund.
     *
     * @param {Object} payload { orderTransactionId, amount?, comments? }
     * @returns {Promise}
     */
    refund(payload) {
        return this.httpClient.post(
            '_action/flutterwave/refund',
            payload,
            { headers: this.getBasicHeaders() }
        ).then((response) => Classes.ApiService.handleResponse(response));
    }
}

export default FlutterwaveRefundService;
