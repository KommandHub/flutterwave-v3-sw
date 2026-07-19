const PluginManager = window.PluginManager;

PluginManager.register(
    'FlutterwaveBankVerification',
    () => import('./flutterwave-bank-verification-plugin/flutterwave-bank-verification.plugin'),
    '[data-flutterwave-bank-verification]'
);
