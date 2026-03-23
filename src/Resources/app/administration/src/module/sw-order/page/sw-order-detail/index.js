import template from './sw-order-detail.html.twig';

const { Store, Mixin, Utils } = Shopware;
const { Criteria } = Shopware.Data;

Shopware.Component.override('sw-order-detail', {
    template,

    props: {
        orderId: {
            type: String,
            required: false,
            default: null,
        },
    },
})