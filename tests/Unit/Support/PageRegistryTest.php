<?php

use App\Enums\ContentFieldType;
use App\Support\Content\PageRegistry;

function pageRegistry(): PageRegistry
{
    return new PageRegistry([
        'home' => [
            'title' => 'Home',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 50, 'required' => true],
                'body' => ['type' => 'markdown', 'label' => 'Body'],
                'image' => ['type' => 'image', 'label' => 'Image'],
                'steps' => [
                    'type' => 'list',
                    'label' => 'Steps',
                    'items' => 2,
                    'required' => true,
                    'fields' => ['title' => ['label' => 'Title', 'max' => 20]],
                ],
            ],
        ],
    ]);
}

it('reads pages and fields from config', function () {
    $page = pageRegistry()->get('home');

    expect(pageRegistry()->has('home'))->toBeTrue()
        ->and(pageRegistry()->get('nope'))->toBeNull()
        ->and(array_map(fn ($field) => $field->type, $page->fields))->toBe([
            ContentFieldType::Text, ContentFieldType::Markdown, ContentFieldType::Image, ContentFieldType::List,
        ])
        ->and($page->fields[1]->max)->toBe(20000)
        ->and($page->fields[3]->fields[0]->max)->toBe(20);
});

it('fills content to the defined shape and drops unknown keys', function () {
    $filled = pageRegistry()->get('home')->fill([
        'heading' => '  Welcome ',
        'body' => '',
        'removed_field' => 'x',
        'steps' => [['title' => 'One', 'extra' => 'x']],
    ]);

    expect($filled)->toBe([
        'heading' => 'Welcome',
        'body' => null,
        'image' => null,
        'steps' => [['title' => 'One'], ['title' => null]],
    ]);
});

it('lists required fields that are empty', function () {
    $page = pageRegistry()->get('home');

    expect($page->missing(null))->toBe(['Heading', 'Steps'])
        ->and($page->missing(['heading' => 'Hi', 'steps' => [['title' => 'A'], ['title' => 'B']]]))->toBe([]);
});

it('builds validation rules from the definition', function () {
    $rules = pageRegistry()->rulesFor(pageRegistry()->get('home'));

    expect($rules['content.heading'])->toBe(['nullable', 'string', 'max:50'])
        ->and($rules['content.steps'])->toBe(['present', 'array', 'size:2'])
        ->and($rules['content.steps.*.title'])->toBe(['nullable', 'string', 'max:20'])
        ->and($rules)->toHaveKey('content.image');
});

it('defines every preset page the site needs', function () {
    expect(array_keys(PageRegistry::fromConfig()->all()))
        ->toBe(['home', 'about', 'services', 'fleet', 'rental-policy', 'contact', 'privacy']);
});
