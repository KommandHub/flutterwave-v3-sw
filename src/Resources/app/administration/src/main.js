import frFR from './snippet/fr-FR.json';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Locale.extend('fr-FR', frFR);
Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

import './module/sw-order/page/sw-order-detail'
import './view/kommandhub-flutterwave-detail'

Shopware.Module.register('kommandhub-flutterwave-detail', {
    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.order.detail') {
            const flutterwaveRoute = 'kommandhub.flutterwave.detail';

            if (currentRoute.name === 'sw.order.detail' && !currentRoute.children.some(child => child.name === flutterwaveRoute)) {
                currentRoute.children.push({
                    name: flutterwaveRoute,
                    path: 'kommandhub/flutterwave',
                    component: 'kommandhub-flutterwave-detail',
                    meta: {
                        parentPath: 'sw.order.detail',
                        privilege: 'order.viewer',
                    },
                    props: {
                        default: ($route) => {
                            return { orderId: $route.params.id.toLowerCase() };
                        },
                    },
                });
            }
        }
        next(currentRoute);
    }
});