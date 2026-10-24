import { Head, Link, useForm } from '@inertiajs/react';
import ContentController from '@/actions/App/Http/Controllers/Admin/ContentController';
import PaymentMethodController from '@/actions/App/Http/Controllers/Admin/PaymentMethodController';
import { ToggleRow } from '@/components/admin/catalog/toggle-row';
import { TextField } from '@/components/admin/form-field';
import { KeyValueField } from '@/components/admin/key-value-field';
import { MarkdownField } from '@/components/admin/markdown-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCan } from '@/hooks/use-can';
import { metadataFromRows, rowsFromMetadata } from '@/lib/metadata';
import type { AdminPaymentMethod } from '@/types';

export default function PaymentMethodForm({
    method,
}: {
    method: AdminPaymentMethod;
}) {
    const can = useCan();
    const editable = can('payment-methods.update');

    const form = useForm({
        name: method.name,
        instructions: method.instructions,
        requires_transaction_message: method.requires_transaction_message,
        requires_proof: method.requires_proof,
        is_active: method.is_active,
        online_enabled: method.online_enabled,
        metadata_rows: rowsFromMetadata(method.metadata),
    });
    const errors = form.errors as Record<string, string | undefined>;

    return (
        <>
            <Head title={method.name} />
            <AdminPage>
                <PageHeader
                    title={method.name}
                    description="Renters see these instructions after they choose this method."
                />

                {method.has_todo && (
                    <Alert variant="destructive" className="max-w-3xl">
                        <AlertDescription>
                            The instructions still contain TODO placeholders
                            from the seed pack. Replace them with the real
                            payment details before launch.
                        </AlertDescription>
                    </Alert>
                )}

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform(({ metadata_rows, ...data }) => ({
                            ...data,
                            metadata: metadataFromRows(metadata_rows),
                        }));
                        form.put(
                            PaymentMethodController.update.url(method.code),
                            {
                                preserveScroll: true,
                            },
                        );
                    }}
                    className="grid max-w-3xl gap-6"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>Instructions</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <TextField
                                name="name"
                                label="Name"
                                required
                                disabled={!editable}
                                value={form.data.name}
                                error={errors.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                            <MarkdownField
                                name="instructions"
                                label="Instructions"
                                description="Paybill or account numbers, the reference to use, and what to paste after paying."
                                rows={8}
                                maxLength={5000}
                                disabled={!editable}
                                value={form.data.instructions}
                                error={errors.instructions}
                                onChange={(value) =>
                                    form.setData('instructions', value)
                                }
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Payment details</CardTitle>
                            <CardDescription>
                                For staff and vouchers, e.g. paybill,
                                account_number, bank_name, card_link. Not shown
                                to renters unless the instructions include them.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <KeyValueField
                                name="metadata"
                                label="Details"
                                value={form.data.metadata_rows}
                                onChange={(rows) =>
                                    form.setData('metadata_rows', rows)
                                }
                                errors={errors}
                                disabled={!editable}
                            />
                        </CardContent>
                    </Card>

                    {method.supports_online && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Online payments</CardTitle>
                                <CardDescription>
                                    {method.code === 'mpesa'
                                        ? 'Renters get an M-Pesa prompt on their phone, and paybill payments with the booking reference are matched automatically.'
                                        : 'Renters pay by card in a Paystack window on the booking page.'}{' '}
                                    The booking is confirmed as soon as the
                                    payment arrives. Turn this off to go back to
                                    checking payments by hand.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ToggleRow
                                    id="online_enabled"
                                    label="Take payments online"
                                    description={
                                        method.gateway_configured
                                            ? undefined
                                            : `The ${method.code === 'mpesa' ? 'Daraja' : 'Paystack'} keys are not set on the server yet, so renters still pay by hand.`
                                    }
                                    checked={form.data.online_enabled}
                                    disabled={!editable}
                                    onChange={(value) =>
                                        form.setData('online_enabled', value)
                                    }
                                />
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Rules</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <ToggleRow
                                id="requires_transaction_message"
                                label="Ask for the transaction message"
                                description="Renters paste the confirmation SMS or reference after paying."
                                checked={form.data.requires_transaction_message}
                                disabled={!editable}
                                onChange={(value) =>
                                    form.setData(
                                        'requires_transaction_message',
                                        value,
                                    )
                                }
                            />
                            <ToggleRow
                                id="requires_proof"
                                label="Ask for proof of payment"
                                description="Renters upload a screenshot or receipt."
                                checked={form.data.requires_proof}
                                disabled={!editable}
                                onChange={(value) =>
                                    form.setData('requires_proof', value)
                                }
                            />
                            <ToggleRow
                                id="is_active"
                                label="Offer to renters"
                                checked={form.data.is_active}
                                disabled={!editable}
                                onChange={(value) =>
                                    form.setData('is_active', value)
                                }
                            />
                        </CardContent>
                        {editable && (
                            <CardFooter className="gap-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing || !form.isDirty}
                                >
                                    Save
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={PaymentMethodController.index()}
                                    >
                                        Back
                                    </Link>
                                </Button>
                            </CardFooter>
                        )}
                    </Card>
                </form>
            </AdminPage>
        </>
    );
}

PaymentMethodForm.layout = {
    breadcrumbs: [
        { title: 'Content', href: ContentController() },
        { title: 'Payment methods', href: PaymentMethodController.index() },
    ],
};
