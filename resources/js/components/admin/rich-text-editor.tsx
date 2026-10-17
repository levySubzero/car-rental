import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Bold,
    Heading2,
    Heading3,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    Redo,
    Undo,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Toggle } from '@/components/ui/toggle';
import { cn } from '@/lib/utils';

/**
 * Rich text for policy bodies, payment instructions and page sections.
 * Submits HTML under `name`; the server sanitises it before storing.
 */
export function RichTextEditor({
    name,
    defaultValue = '',
    invalid = false,
    placeholder,
}: {
    name: string;
    defaultValue?: string;
    invalid?: boolean;
    placeholder?: string;
}) {
    const [html, setHtml] = useState(defaultValue);

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
                link: { openOnClick: false, autolink: true },
            }),
        ],
        content: defaultValue,
        immediatelyRender: false,
        editorProps: {
            attributes: {
                class: 'prose prose-sm dark:prose-invert max-w-none min-h-40 px-3 py-2 focus:outline-none',
                ...(placeholder ? { 'aria-placeholder': placeholder } : {}),
            },
        },
        onUpdate: ({ editor }) =>
            setHtml(editor.isEmpty ? '' : editor.getHTML()),
    });

    const setLink = () => {
        if (!editor) {
            return;
        }

        const previous = editor.getAttributes('link').href as
            | string
            | undefined;
        const url = window.prompt('Link URL', previous ?? 'https://');

        if (url === null) {
            return;
        }

        if (url === '') {
            editor.chain().focus().unsetLink().run();

            return;
        }

        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: url })
            .run();
    };

    return (
        <div
            className={cn(
                'rounded-md border border-input shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
                invalid && 'border-destructive',
            )}
        >
            <input type="hidden" name={name} value={html} />
            {editor && (
                <div className="flex flex-wrap gap-1 border-b p-1">
                    <Tool
                        label="Heading"
                        active={editor.isActive('heading', { level: 2 })}
                        onClick={() =>
                            editor
                                .chain()
                                .focus()
                                .toggleHeading({ level: 2 })
                                .run()
                        }
                    >
                        <Heading2 />
                    </Tool>
                    <Tool
                        label="Subheading"
                        active={editor.isActive('heading', { level: 3 })}
                        onClick={() =>
                            editor
                                .chain()
                                .focus()
                                .toggleHeading({ level: 3 })
                                .run()
                        }
                    >
                        <Heading3 />
                    </Tool>
                    <Tool
                        label="Bold"
                        active={editor.isActive('bold')}
                        onClick={() =>
                            editor.chain().focus().toggleBold().run()
                        }
                    >
                        <Bold />
                    </Tool>
                    <Tool
                        label="Italic"
                        active={editor.isActive('italic')}
                        onClick={() =>
                            editor.chain().focus().toggleItalic().run()
                        }
                    >
                        <Italic />
                    </Tool>
                    <Tool
                        label="Bullet list"
                        active={editor.isActive('bulletList')}
                        onClick={() =>
                            editor.chain().focus().toggleBulletList().run()
                        }
                    >
                        <List />
                    </Tool>
                    <Tool
                        label="Numbered list"
                        active={editor.isActive('orderedList')}
                        onClick={() =>
                            editor.chain().focus().toggleOrderedList().run()
                        }
                    >
                        <ListOrdered />
                    </Tool>
                    <Tool
                        label="Link"
                        active={editor.isActive('link')}
                        onClick={setLink}
                    >
                        <LinkIcon />
                    </Tool>
                    <Tool
                        label="Undo"
                        onClick={() => editor.chain().focus().undo().run()}
                    >
                        <Undo />
                    </Tool>
                    <Tool
                        label="Redo"
                        onClick={() => editor.chain().focus().redo().run()}
                    >
                        <Redo />
                    </Tool>
                </div>
            )}
            <EditorContent editor={editor} />
        </div>
    );
}

function Tool({
    label,
    active = false,
    onClick,
    children,
}: {
    label: string;
    active?: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <Toggle
            size="sm"
            pressed={active}
            onPressedChange={onClick}
            aria-label={label}
            title={label}
        >
            {children}
        </Toggle>
    );
}
