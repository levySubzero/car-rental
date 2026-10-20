import { useHttp } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useId, useState } from 'react';
import MarkdownPreviewController from '@/actions/App/Http/Controllers/Admin/MarkdownPreviewController';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

/**
 * Markdown text with Write and Preview tabs (D23). The preview is rendered by the server,
 * with the same sanitising the public site uses, so what staff see is what renters see.
 */
export function MarkdownField({
    name,
    label,
    value,
    onChange,
    error,
    description,
    rows = 10,
    maxLength,
    disabled = false,
    className,
}: {
    name: string;
    label: ReactNode;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    description?: ReactNode;
    rows?: number;
    maxLength?: number;
    disabled?: boolean;
    className?: string;
}) {
    const id = useId();
    const [tab, setTab] = useState('write');
    const [html, setHtml] = useState<string | null>(null);
    const http = useHttp<{ markdown: string }, { html: string }>({
        markdown: '',
    });

    const preview = async () => {
        setHtml(null);
        http.transform(() => ({ markdown: value }));

        try {
            const response = await http.post(MarkdownPreviewController.url());
            setHtml(response?.html ?? '');
        } catch {
            setHtml('<p>The preview could not be loaded.</p>');
        }
    };

    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={id}>{label}</Label>
            <Tabs
                value={tab}
                onValueChange={(next) => {
                    setTab(next);

                    if (next === 'preview') {
                        void preview();
                    }
                }}
            >
                <TabsList>
                    <TabsTrigger value="write">Write</TabsTrigger>
                    <TabsTrigger value="preview">Preview</TabsTrigger>
                </TabsList>
                <TabsContent value="write" className="mt-2">
                    <Textarea
                        id={id}
                        name={name}
                        rows={rows}
                        value={value}
                        maxLength={maxLength}
                        disabled={disabled}
                        aria-invalid={!!error}
                        className="font-mono text-sm"
                        onChange={(event) => onChange(event.target.value)}
                    />
                </TabsContent>
                <TabsContent value="preview" className="mt-2">
                    <div
                        className="prose prose-sm min-h-24 max-w-none rounded-md border px-4 py-3 dark:prose-invert"
                        aria-busy={html === null}
                    >
                        {html === null ? (
                            <p className="text-muted-foreground">Loading…</p>
                        ) : html === '' ? (
                            <p className="text-muted-foreground">
                                Nothing to preview.
                            </p>
                        ) : (
                            // Rendered and sanitised by App\Support\Markdown.
                            <div dangerouslySetInnerHTML={{ __html: html }} />
                        )}
                    </div>
                </TabsContent>
            </Tabs>
            {!error && (
                <p className="text-xs text-muted-foreground">
                    {description}
                    {description && ' '}
                    Formatting: **bold**, *italic*, ### heading, - list item,
                    [link text](https://…).
                </p>
            )}
            <InputError message={error} />
        </div>
    );
}
