/**
 * Registers a dedicated "Flutterwave refund" admin permission so it can be
 * granted to roles independently of the generic order editor privilege. Appears
 * as a checkbox under Settings > Users & permissions > Roles > Additional
 * permissions. The composite identifier `flutterwave.refund` is what the refund
 * action (client) and the refund route (server `_acl`) check against.
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'additional_permissions',
    parent: null,
    key: 'flutterwave',
    roles: {
        refund: {
            // Depends on the order editor role, which already grants the order
            // and transaction read/update privileges the refund action needs.
            // The permission is a pure gate for the refund action itself.
            privileges: [],
            dependencies: ['order.editor'],
        },
    },
});
