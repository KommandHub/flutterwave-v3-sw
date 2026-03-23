import template from './kommandhub-flutterwave-detail.html.twig'
import './kommandhub-flutterwave-detail.scss'
import icon from './icon.png'

const { Store } = Shopware;

Shopware.Component.register('kommandhub-flutterwave-detail', {
    template,

    metaInfo() {
        return {
            title: this.$t('kommandhub-flutterwave-detail.title')
        };
    },

    inject: [
        'repositoryFactory',
    ],

    props: {
        orderId: {
            type: String,
            required: false,
            default: null,
        },
    },

    computed: {
        order: () => Store.get('swOrderDetail').order,

        orderChanges() {
            if (!this.order) {
                return false;
            }

            return this.orderRepository.hasChanges(this.order);
        },

        flutterwaveTransaction() {
            if (!this.order || !this.order.transactions) {
                return null;
            }

            return this.order.transactions.find((transaction) => {
                return transaction.customFields && transaction.customFields.flutterwave_reference;
            });
        },

        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },

        flutterwaveIcon() {
            return icon;
        },
    },

    watch: {
        orderId() {
            this.createdComponent();
        },
    },
});