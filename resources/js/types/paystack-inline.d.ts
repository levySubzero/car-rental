// @paystack/inline-js ships no types; only what the card popup uses (M11B.7).
declare module '@paystack/inline-js' {
    type PaystackCallbacks = {
        onSuccess?: (transaction: {
            id: number;
            reference: string;
            message: string;
        }) => void;
        onCancel?: () => void;
        onError?: (error: { message: string }) => void;
        onLoad?: () => void;
    };

    export default class PaystackPop {
        resumeTransaction(
            accessCode: string,
            callbacks?: PaystackCallbacks,
        ): unknown;
    }
}
