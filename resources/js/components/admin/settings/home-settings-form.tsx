import { Link, useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import PageController from '@/actions/App/Http/Controllers/Admin/PageController';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import {
    Field,
    SelectField,
    TextareaField,
    TextField,
} from '@/components/admin/form-field';
import { SortableList } from '@/components/admin/sortable-list';
import { MediaUploader } from '@/components/media/media-uploader';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/use-can';
import type { MediaCollectionLimits, MediaItem } from '@/types';

export type HomeSettings = {
    hero_media_uuid: string | null;
    headline: string;
    subline: string;
    primary_cta_label: string;
    secondary_cta_label: string;
    featured_car_count: number;
    marquee_position: 'below_hero' | 'page_bottom';
    sections: { key: string; visible: boolean }[];
    trust_figures: { value: string; label: string }[];
};

export type HomeSettingsContext = {
    hero: MediaItem | null;
    heroLimits: MediaCollectionLimits;
};

// Mirrors UpdateHomeSettingsRequest::SECTIONS.
const SECTION_LABELS: Record<string, string> = {
    about: 'About',
    featured_fleet: 'Featured fleet',
    car_types: 'Car types',
    services: 'Services',
    policy_highlights: 'Policy highlights',
    requirements: 'Requirements (01–03)',
    why_choose_us: 'Why choose us (01–04)',
    trust_strip: 'Trust figures',
    contact_cta: 'Contact call to action',
};

export function HomeSettingsForm({
    values,
    context,
}: {
    values: HomeSettings;
    context: HomeSettingsContext;
}) {
    const can = useCan();
    const [hero, setHero] = useState<MediaItem[]>(
        context.hero ? [context.hero] : [],
    );
    const form = useForm({
        ...values,
        featured_car_count: String(values.featured_car_count),
    });
    const errors = form.errors as Record<string, string | undefined>;

    const setFigure = (index: number, key: 'value' | 'label', value: string) =>
        form.setData(
            'trust_figures',
            form.data.trust_figures.map((figure, i) =>
                i === index ? { ...figure, [key]: value } : figure,
            ),
        );

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(SettingsController.update.url('home'), {
                    preserveScroll: true,
                });
            }}
            className="grid gap-6"
        >
            {can('pages.view') && (
                <p className="text-sm text-muted-foreground">
                    The copy for the About, Requirements, Why choose us and
                    contact sections is on the{' '}
                    <Link
                        href={PageController.edit('home')}
                        className="underline underline-offset-4"
                    >
                        Home page content
                    </Link>
                    .
                </p>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Hero</CardTitle>
                    <CardDescription>
                        The first screen of the home page.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Background image"
                        error={errors.hero_media_uuid}
                        className="sm:col-span-2"
                    >
                        <MediaUploader
                            limits={context.heroLimits}
                            value={hero}
                            onChange={(items) => {
                                setHero(items);
                                form.setData(
                                    'hero_media_uuid',
                                    items[0]?.uuid ?? null,
                                );
                            }}
                        />
                    </Field>
                    <TextField
                        name="headline"
                        label="Headline"
                        required
                        maxLength={120}
                        className="sm:col-span-2"
                        value={form.data.headline}
                        error={errors.headline}
                        onChange={(event) =>
                            form.setData('headline', event.target.value)
                        }
                    />
                    <TextareaField
                        name="subline"
                        label="Subline"
                        rows={2}
                        required
                        maxLength={300}
                        className="sm:col-span-2"
                        value={form.data.subline}
                        error={errors.subline}
                        onChange={(event) =>
                            form.setData('subline', event.target.value)
                        }
                    />
                    <TextField
                        name="primary_cta_label"
                        label="Main button"
                        required
                        maxLength={30}
                        value={form.data.primary_cta_label}
                        error={errors.primary_cta_label}
                        onChange={(event) =>
                            form.setData(
                                'primary_cta_label',
                                event.target.value,
                            )
                        }
                    />
                    <TextField
                        name="secondary_cta_label"
                        label="Second button"
                        required
                        maxLength={30}
                        value={form.data.secondary_cta_label}
                        error={errors.secondary_cta_label}
                        onChange={(event) =>
                            form.setData(
                                'secondary_cta_label',
                                event.target.value,
                            )
                        }
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Sections</CardTitle>
                    <CardDescription>
                        Drag to set the order below the hero, and switch off
                        sections to hide them.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <SortableList
                        items={form.data.sections.map((section) => ({
                            ...section,
                            id: section.key,
                        }))}
                        onReorder={(_, items) =>
                            form.setData(
                                'sections',
                                items.map(({ key, visible }) => ({
                                    key,
                                    visible,
                                })),
                            )
                        }
                        renderItem={(section) => (
                            <div className="flex items-center gap-3">
                                <span className="flex-1 text-sm">
                                    {SECTION_LABELS[section.key] ?? section.key}
                                </span>
                                <Switch
                                    checked={section.visible}
                                    aria-label={`Show ${SECTION_LABELS[section.key] ?? section.key}`}
                                    onCheckedChange={(visible) =>
                                        form.setData(
                                            'sections',
                                            form.data.sections.map((s) =>
                                                s.key === section.key
                                                    ? { ...s, visible }
                                                    : s,
                                            ),
                                        )
                                    }
                                />
                            </div>
                        )}
                    />
                    <InputError message={errors.sections} />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            name="featured_car_count"
                            label="Featured cars shown"
                            type="number"
                            min={1}
                            max={12}
                            value={form.data.featured_car_count}
                            error={errors.featured_car_count}
                            onChange={(event) =>
                                form.setData(
                                    'featured_car_count',
                                    event.target.value,
                                )
                            }
                        />
                        <SelectField
                            name="marquee_position"
                            label="Brand logo marquee"
                            options={[
                                {
                                    value: 'below_hero',
                                    label: 'Below the hero',
                                },
                                {
                                    value: 'page_bottom',
                                    label: 'Bottom of the page',
                                },
                            ]}
                            value={form.data.marquee_position}
                            error={errors.marquee_position}
                            onChange={(event) =>
                                form.setData(
                                    'marquee_position',
                                    event.target
                                        .value as HomeSettings['marquee_position'],
                                )
                            }
                        />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Trust figures</CardTitle>
                    <CardDescription>
                        Up to six short figures, e.g. "500+" happy clients.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3">
                    {form.data.trust_figures.map((figure, index) => (
                        <div key={index} className="grid gap-1">
                            <div className="flex gap-2">
                                <Input
                                    aria-label="Figure"
                                    placeholder="500+"
                                    className="w-28"
                                    maxLength={20}
                                    value={figure.value}
                                    onChange={(event) =>
                                        setFigure(
                                            index,
                                            'value',
                                            event.target.value,
                                        )
                                    }
                                />
                                <Input
                                    aria-label="Label"
                                    placeholder="Happy clients"
                                    maxLength={60}
                                    value={figure.label}
                                    onChange={(event) =>
                                        setFigure(
                                            index,
                                            'label',
                                            event.target.value,
                                        )
                                    }
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Remove"
                                    onClick={() =>
                                        form.setData(
                                            'trust_figures',
                                            form.data.trust_figures.filter(
                                                (_, i) => i !== index,
                                            ),
                                        )
                                    }
                                >
                                    <X />
                                </Button>
                            </div>
                            <InputError
                                message={
                                    errors[`trust_figures.${index}.value`] ??
                                    errors[`trust_figures.${index}.label`]
                                }
                            />
                        </div>
                    ))}
                    {form.data.trust_figures.length < 6 && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="justify-self-start"
                            onClick={() =>
                                form.setData('trust_figures', [
                                    ...form.data.trust_figures,
                                    { value: '', label: '' },
                                ])
                            }
                        >
                            <Plus /> Add figure
                        </Button>
                    )}
                </CardContent>
                <CardFooter>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        Save home page settings
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}
