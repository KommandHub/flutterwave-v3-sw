import {
    currencyDecimals,
    roundToCurrency,
    minRefundableAmount,
    sumActiveRefunds,
    maxRefundableAmount,
} from './refund-calculator';

describe('refund-calculator', () => {
    describe('currencyDecimals', () => {
        it('returns the configured decimals', () => {
            expect(currencyDecimals('NGN')).toBe(2);
            expect(currencyDecimals('RWF')).toBe(0);
            expect(currencyDecimals('KWD')).toBe(3);
        });

        it('defaults unknown currencies to 2', () => {
            expect(currencyDecimals('ZZZ')).toBe(2);
        });
    });

    describe('roundToCurrency', () => {
        it('rounds to the currency precision', () => {
            expect(roundToCurrency(100.555, 'NGN')).toBe(100.56);
            expect(roundToCurrency(100.4, 'RWF')).toBe(100);
        });

        it('treats nullish as zero', () => {
            expect(roundToCurrency(null, 'NGN')).toBe(0);
            expect(roundToCurrency(undefined, 'NGN')).toBe(0);
        });
    });

    describe('minRefundableAmount', () => {
        it('keeps the configured major amount, only normalising precision', () => {
            expect(minRefundableAmount(100, 'NGN')).toBe(100);
            expect(minRefundableAmount(10.005, 'NGN')).toBe(10.01);
        });

        it('treats nullish as zero', () => {
            expect(minRefundableAmount(null, 'NGN')).toBe(0);
        });
    });

    describe('sumActiveRefunds', () => {
        it('counts every non-failed status, including successful and completed', () => {
            const refunds = [
                { status: 'completed', amount_refunded: 10 },
                { status: 'successful', amount_refunded: 5 },
                { status: 'pending', amount_refunded: 2 },
            ];
            expect(sumActiveRefunds(refunds)).toBe(17);
        });

        it('ignores only failed refunds, freeing their amount', () => {
            const refunds = [
                { status: 'completed', amount_refunded: 10 },
                { status: 'failed', amount_refunded: 40 },
            ];
            expect(sumActiveRefunds(refunds)).toBe(10);
        });

        it('is case-insensitive on status', () => {
            expect(sumActiveRefunds([{ status: 'COMPLETED', amount: 3 }])).toBe(3);
        });

        it('prefers amount_refunded over amount', () => {
            expect(sumActiveRefunds([{ status: 'completed', amount_refunded: 7, amount: 99 }])).toBe(7);
        });

        it('defaults to an empty list', () => {
            expect(sumActiveRefunds()).toBe(0);
        });

        it('reads local Shopware records: price object amount and state machine status', () => {
            const refunds = [
                { stateMachineState: { technicalName: 'completed' }, amount: { totalPrice: 30 } },
                { stateMachineState: { technicalName: 'open' }, amount: { totalPrice: 20 } },
            ];
            expect(sumActiveRefunds(refunds)).toBe(50);
        });

        it('frees both failed and cancelled Shopware refund states', () => {
            const refunds = [
                { stateMachineState: { technicalName: 'completed' }, amount: { totalPrice: 10 } },
                { stateMachineState: { technicalName: 'failed' }, amount: { totalPrice: 40 } },
                { stateMachineState: { technicalName: 'cancelled' }, amount: { totalPrice: 25 } },
            ];
            expect(sumActiveRefunds(refunds)).toBe(10);
        });
    });

    describe('maxRefundableAmount', () => {
        it('is the charged amount when nothing is refunded', () => {
            expect(maxRefundableAmount({ transactionAmount: 100, refunds: [], currency: 'NGN' })).toBe(100);
        });

        it('subtracts refunds already made', () => {
            const refunds = [{ status: 'completed', amount: 30 }];
            expect(maxRefundableAmount({ transactionAmount: 100, refunds, currency: 'NGN' })).toBe(70);
        });

        it('never goes below zero', () => {
            const refunds = [{ status: 'completed', amount: 150 }];
            expect(maxRefundableAmount({ transactionAmount: 100, refunds, currency: 'NGN' })).toBe(0);
        });

        it('rounds to currency precision', () => {
            expect(maxRefundableAmount({ transactionAmount: 100.005, refunds: [], currency: 'NGN' })).toBe(100.01);
        });
    });
});
