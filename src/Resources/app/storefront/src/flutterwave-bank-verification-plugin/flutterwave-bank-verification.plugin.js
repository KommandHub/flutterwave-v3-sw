const { PluginBaseClass } = window;

/**
 * Flutterwave Bank Verification Plugin
 *
 * Backs the customer bank-profile form:
 * - Loads the supported banks for the configured country.
 * - Resolves the account number against the selected bank via Flutterwave.
 * - Fills in the verified account name and blocks submission until it verifies.
 * - Validates the BVN format client-side when the field is shown.
 *
 * Improvements over the Paystack plugin this is modelled on:
 * - In-flight resolution requests are aborted when the inputs change again, so a
 *   slow earlier response can no longer overwrite a newer one (a race in the
 *   original).
 * - The account name is treated as authoritative only while it matches the
 *   verified input, so editing the number after a match re-locks submission.
 * - Talks to a normalised `{status, data}` contract, independent of provider.
 */
export default class FlutterwaveBankVerificationPlugin extends PluginBaseClass {
    static options = {
        banksUrl: '',
        verifyUrl: '',

        bankSelectSelector: '#bankName',
        accountNumberSelector: '#accountNumber',
        bvnSelector: '#bvn',
        accountNameSelector: '#accountName',
        submitButtonSelector: 'button[type="submit"]',

        initialBankCode: '',
    };

    /** Nigerian bank account numbers are exactly 10 digits. */
    static ACCOUNT_NUMBER_LENGTH = 10;

    /** BVN is exactly 11 digits. */
    static BVN_LENGTH = 11;

    init() {
        this._bankSelect = this.el.querySelector(this.options.bankSelectSelector);
        this._accountNumberInput = this.el.querySelector(this.options.accountNumberSelector);
        this._bvnInput = this.el.querySelector(this.options.bvnSelector);
        this._accountNameInput = this.el.querySelector(this.options.accountNameSelector);
        this._submitButton = this.el.querySelector(this.options.submitButtonSelector);

        this._verified = false;
        this._inFlight = null;

        if (!this._bankSelect || !this._accountNumberInput) {
            return;
        }

        this._registerEvents();
        this._loadBanks();
    }

    _registerEvents() {
        this._accountNumberInput.addEventListener('blur', this._verifyAccount.bind(this));
        this._accountNumberInput.addEventListener('input', this._onAccountNumberInput.bind(this));
        this._bankSelect.addEventListener('change', this._verifyAccount.bind(this));

        if (this._bvnInput) {
            this._bvnInput.addEventListener('blur', this._validateBvn.bind(this));
        }

        this.el.addEventListener('submit', this._onFormSubmit.bind(this));
    }

    _loadBanks() {
        if (!this.options.banksUrl) {
            return;
        }

        fetch(this.options.banksUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => response.json())
            .then((result) => {
                if (result.status && Array.isArray(result.data)) {
                    this._populateBankSelect(result.data);
                }
            })
            .catch(() => {
                /* Bank list is a convenience; failure leaves the placeholder. */
            });
    }

    _populateBankSelect(banks) {
        while (this._bankSelect.options.length > 1) {
            this._bankSelect.remove(1);
        }

        banks.forEach((bank) => {
            const option = document.createElement('option');
            option.value = bank.code;
            option.text = bank.name;
            option.dataset.bankName = bank.name;

            if (bank.code === this.options.initialBankCode) {
                option.selected = true;
            }

            this._bankSelect.add(option);
        });

        // Re-verify an already-stored profile so the name field is trustworthy.
        if (this._bankSelect.value && this._accountNumberInput.value && !this._accountNameInput.value) {
            this._verifyAccount();
        }
    }

    /**
     * Any edit to the number invalidates a prior match and re-locks submission.
     *
     * @private
     */
    _onAccountNumberInput() {
        this._verified = false;
        this._accountNameInput.value = '';
        this._markValidationState(this._accountNumberInput, null);
    }

    _verifyAccount() {
        if (!this.options.verifyUrl) {
            return;
        }

        const accountNumber = this._accountNumberInput.value.trim();
        const bankCode = this._bankSelect.value;

        if (accountNumber.length !== FlutterwaveBankVerificationPlugin.ACCOUNT_NUMBER_LENGTH || !bankCode) {
            if (accountNumber.length > 0 && accountNumber.length !== FlutterwaveBankVerificationPlugin.ACCOUNT_NUMBER_LENGTH) {
                this._markValidationState(this._accountNumberInput, false, 'Account number must be 10 digits');
            } else {
                this._markValidationState(this._accountNumberInput, null);
            }

            return;
        }

        // Abort a still-pending resolution so its response cannot land late and
        // overwrite the result of this newer one.
        if (this._inFlight) {
            this._inFlight.abort();
        }

        const controller = new AbortController();
        this._inFlight = controller;
        this._setLoading(true);

        const formData = new FormData();
        formData.append('account_number', accountNumber);
        formData.append('bank_code', bankCode);

        fetch(this.options.verifyUrl, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal,
        })
            .then((response) => response.json())
            .then((result) => {
                this._inFlight = null;
                this._setLoading(false);

                if (result.status && result.data && result.data.account_name) {
                    this._verified = true;
                    this._accountNameInput.value = result.data.account_name;
                    this._markValidationState(this._accountNumberInput, true, `Account verified: ${result.data.account_name}`);
                } else {
                    this._verified = false;
                    this._markValidationState(this._accountNumberInput, false, result.message || 'Account verification failed');
                }
            })
            .catch((error) => {
                if (error.name === 'AbortError') {
                    return;
                }

                this._inFlight = null;
                this._setLoading(false);
                this._verified = false;
                this._markValidationState(this._accountNumberInput, false, 'Verification service unavailable');
            });
    }

    /**
     * Client-side BVN format check. The server re-validates; this is only for
     * fast feedback.
     *
     * @private
     */
    _validateBvn() {
        if (!this._bvnInput) {
            return;
        }

        const bvn = this._bvnInput.value.trim();

        if (bvn === '') {
            this._markValidationState(this._bvnInput, null);

            return;
        }

        const valid = new RegExp(`^\\d{${FlutterwaveBankVerificationPlugin.BVN_LENGTH}}$`).test(bvn);
        this._markValidationState(this._bvnInput, valid, valid ? '' : 'BVN must be 11 digits');
    }

    _setLoading(loading) {
        this._accountNumberInput.classList.toggle('is-loading', loading);
    }

    /**
     * @param {HTMLElement} input
     * @param {boolean|null} valid true=success, false=error, null=reset
     * @param {string} message
     * @private
     */
    _markValidationState(input, valid, message = '') {
        const container = input.closest('.form-group');
        const invalidFeedback = container?.querySelector('.invalid-feedback');
        const validFeedback = container?.querySelector('.valid-feedback');

        if (valid === null) {
            input.classList.remove('is-valid', 'is-invalid');

            if (invalidFeedback) invalidFeedback.textContent = '';
            if (validFeedback) validFeedback.textContent = '';

            return;
        }

        input.classList.toggle('is-valid', valid);
        input.classList.toggle('is-invalid', !valid);

        if (validFeedback) validFeedback.textContent = valid ? message : '';
        if (invalidFeedback) invalidFeedback.textContent = valid ? '' : message;
    }

    /**
     * Attach the selected bank's name and code as hidden fields on submit.
     *
     * @private
     */
    _onFormSubmit() {
        const selectedOption = this._bankSelect.options[this._bankSelect.selectedIndex];

        if (!selectedOption) {
            return;
        }

        this._createOrUpdateHiddenField('hiddenBankName', 'bankName', selectedOption.dataset.bankName || '');
        this._createOrUpdateHiddenField('hiddenBankCode', 'bankCode', this._bankSelect.value);
    }

    _createOrUpdateHiddenField(id, name, value) {
        let field = this.el.querySelector(`#${id}`);

        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.id = id;
            field.name = name;
            this.el.appendChild(field);
        }

        field.value = value;
    }
}
