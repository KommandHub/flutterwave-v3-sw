import template from './sw-order-detail.html.twig';
import { FLUTTERWAVE_HANDLER_IDENTIFIER, FLUTTERWAVE_REFERENCE_FIELD, isAbortError } from '../../../../util/flutterwave';

const { Criteria } = Shopware.Data;

Shopware.Component.override('sw-order-detail', {
    template,

    inject: ['repositoryFactory'],

    props: {
        orderId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            isLoading: true,
            flutterwaveOrder: null,
        };
    },

    computed: {
        /**
         * Extend the default order criteria with the transaction and state
         * associations the Flutterwave tab needs.
         *
         * @returns {Criteria}
         */
        orderCriteria() {
            const criteria = this.$super('orderCriteria');

            criteria.addAssociation('transactions.paymentMethod');
            criteria.addAssociation('transactions.stateMachineState');
            criteria.addAssociation('transactions.captures.refunds.stateMachineState');
            criteria.addAssociation('transactions.captures.stateMachineState');

            return criteria;
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        /**
         * Whether the order contains a Flutterwave transaction, driving tab
         * visibility.
         *
         * @returns {boolean}
         */
        isFlutterwavePayment() {
            if (!this.flutterwaveOrder?.transactions?.length) {
                return false;
            }

            const transactions = this.flutterwaveOrder.transactions;

            return transactions.some(
                (transaction) =>
                    transaction.paymentMethod?.handlerIdentifier === FLUTTERWAVE_HANDLER_IDENTIFIER
                    || (transaction.customFields && transaction.customFields[FLUTTERWAVE_REFERENCE_FIELD])
            );
        },
    },

    watch: {
        orderId() {
            void this.fetchFlutterwaveOrder();
        },
    },

    created() {
        void this.fetchFlutterwaveOrder();
    },

    methods: {
        async fetchFlutterwaveOrder() {
            if (!this.orderId) {
                this.isLoading = false;
                return;
            }

            this.isLoading = true;

            try {
                this.flutterwaveOrder = await this.orderRepository.get(
                    this.orderId,
                    Shopware.Context.api,
                    this.orderCriteria
                );
            } catch (error) {
                if (isAbortError(error)) {
                    return;
                }

                console.error('[Flutterwave] Failed to load order details:', error?.message ?? error);
            } finally {
                this.isLoading = false;
            }
        },
    },
});
