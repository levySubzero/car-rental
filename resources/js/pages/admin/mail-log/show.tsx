import { Head } from '@inertiajs/react';
import { Paperclip } from 'lucide-react';
import { useState } from 'react';
import MailLogController from '@/actions/App/Http/Controllers/Admin/MailLogController';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDateTime } from '@/lib/format';

type Email = {
    id: number;
    to: string;
    cc: string | null;
    subject: string | null;
    type: string | null;
    mailer: string | null;
    html: string | null;
    text: string | null;
    attachments: { name: string; mime: string; size: number }[];
    created_at: string;
};

/**
 * One logged email as the recipient saw it. The HTML is shown in a sandboxed frame: no
 * scripts, no forms, no access to this page.
 */
export default function MailLogShow({ email }: { email: Email }) {
    const [view, setView] = useState<'html' | 'text'>(
        email.html ? 'html' : 'text',
    );

    return (
        <>
            <Head title={email.subject ?? 'Email'} />
            <AdminPage>
                <PageHeader
                    title={email.subject ?? '(no subject)'}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            {formatDateTime(email.created_at)}
                            {email.type && (
                                <Badge variant="secondary">{email.type}</Badge>
                            )}
                            {email.mailer && (
                                <Badge variant="outline">
                                    {email.mailer === 'log'
                                        ? 'not delivered (log mailer)'
                                        : `via ${email.mailer}`}
                                </Badge>
                            )}
                        </span>
                    }
                />

                <Card>
                    <CardContent className="grid gap-2 text-sm">
                        <Meta label="To" value={email.to} />
                        {email.cc && <Meta label="Cc" value={email.cc} />}
                        {email.attachments.length > 0 && (
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="w-16 text-muted-foreground">
                                    Files
                                </span>
                                {email.attachments.map((file) => (
                                    <span
                                        key={file.name}
                                        className="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs"
                                    >
                                        <Paperclip className="size-3" />
                                        {file.name} ·{' '}
                                        {Math.max(
                                            1,
                                            Math.round(file.size / 1024),
                                        )}{' '}
                                        KB
                                    </span>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {email.html && email.text && (
                    <Tabs
                        value={view}
                        onValueChange={(value) =>
                            setView(value as 'html' | 'text')
                        }
                    >
                        <TabsList>
                            <TabsTrigger value="html">Formatted</TabsTrigger>
                            <TabsTrigger value="text">Plain text</TabsTrigger>
                        </TabsList>
                    </Tabs>
                )}

                {view === 'html' && email.html ? (
                    <iframe
                        title="Email content"
                        sandbox=""
                        srcDoc={email.html}
                        className="h-[70vh] w-full rounded-lg border bg-white"
                    />
                ) : (
                    <pre className="overflow-x-auto rounded-lg border bg-muted p-4 text-sm whitespace-pre-wrap">
                        {email.text ?? 'This email has no body.'}
                    </pre>
                )}
            </AdminPage>
        </>
    );
}

function Meta({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex gap-2">
            <span className="w-16 shrink-0 text-muted-foreground">{label}</span>
            <span className="break-all">{value}</span>
        </div>
    );
}

MailLogShow.layout = {
    breadcrumbs: [
        { title: 'Email log', href: MailLogController.index() },
        { title: 'Email', href: '#' },
    ],
};
