<p align="center">
  <a href="https://shopware.com" target="_blank">
    <img src="src/Resources/config/shopware.png" alt="Shopware Logo">
  </a>
</p>

# Flutterwave for Shopware 6

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
![Shopware](https://img.shields.io/badge/Shopware-6.6%20%7C%206.7-blue)

Integrate Flutterwave's secure and flexible payment gateway into your Shopware 6 store. Accept payments globally with ease.

## Features

- **Seamless Integration:** Easy setup within the Shopware 6 ecosystem.
- **Global Payments:** Accept a wide range of payment methods supported by Flutterwave (Cards, Bank Transfers, Mobile Money, etc.).
- **Sandbox & Live Modes:** Safely test your integration before going live.
- **Detailed Logging:** Optional debug logging for easier troubleshooting.
- **Automated Verification:** Real-time payment verification and order status updates.

## Installation

### Via Composer (Recommended)

Run the following command in your Shopware root directory:

```bash
composer require kommandhub/flutterwave-v3-sw
bin/console plugin:refresh
bin/console plugin:install --activate KommandhubFlutterwaveV3SW
bin/console cache:clear
```

### Manual Installation (GitHub Upload)

1. **Download the Plugin:** Download the ZIP file from your repository.
2. **Ensure correct structure:**

   ```text
   KommandhubFlutterwaveV3SW.zip
   └── src/
   └── composer.json
   ```
3. **Upload to Shopware:**
   - Log in to your Shopware Admin.
   - Go to **Administration > Extensions > My extensions**.
   - Click **Upload extension** and select the ZIP file.
4. **Install and Activate:**
   - Locate **Flutterwave for Shopware** in the list.
   - Click **Install**.
   - After installation, toggle the switch to **Activate** it.

## Configuration

After activation, configure the plugin under **Extensions > My extensions > Flutterwave for Shopware > ... > Configuration**:

1. **Environment:** Choose between `Sandbox` (for testing) and `Live`.
2. **API Keys:**
   - Enter your **Public Key** and **Secret Key** from the [Flutterwave Dashboard](https://dashboard.flutterwave.com/settings/apis).
3. **Debug Mode:** Enable "Enable error logging" to capture detailed logs in `var/log/`.

## Usage

Once configured, Flutterwave will appear as a payment method during the checkout process:

### Customer Experience

1. **Select Payment Method:** On the "Review Order" or "Payment Method" page, customers choose **Flutterwave**.
2. **Place Order:** Upon clicking "Place order", customers are redirected to the secure Flutterwave payment portal.
3. **Complete Payment:** Customers provide their payment details (Card, Bank, Mobile Money, etc.) on the Flutterwave hosted page.
4. **Return to Store:** After a successful or failed payment, customers are automatically redirected back to your Shopware store's "Order Confirmation" or "Payment Failed" page.

### Order Management (Admin)

- **Payment Status:** The order's payment status is automatically updated based on the Flutterwave transaction outcome:
    - **Paid:** Successfully verified transaction.
    - **Cancelled:** Customer aborted the payment on the Flutterwave page.
    - **Failed:** Transaction was declined or an error occurred.
- **Transaction References:** Each order will include the Flutterwave `tx_ref` and `transaction_id` for easy reconciliation in the Shopware Admin and your Flutterwave Dashboard.

## Development and Testing

The plugin includes a `Makefile` for common development tasks.

```bash
# Run tests
make test

# Check code coverage
make test-coverage

# Fix code style
make cs-fix
```

## Contributing

1. Fork the repository.
2. Create a feature branch.
3. Ensure tests pass and code style is maintained.
4. Submit a Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
