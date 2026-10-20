<?php

/*
|--------------------------------------------------------------------------
| Preset pages (D22)
|--------------------------------------------------------------------------
|
| The pages whose copy staff edit in the admin. Pages and their fields are
| defined here, in code: staff fill in the content, M7 decides the layout.
| To add a page or a field, add it here; PageService::ensureAll() creates
| the missing rows (it runs from CmsSeeder).
|
| Field keys:
|
| type       text | markdown | image | list (App\Enums\ContentFieldType)
| label      shown in the admin
| section    optional heading the admin groups following fields under
| help       optional hint under the field
| required   needed before launch: an empty required field puts the page
|            on the dashboard's "Before launch" list. Saving never requires it.
| max        text: maximum characters (default 255, markdown 20000)
| multiline  text: a short paragraph instead of one line
| items      list: exact number of items
| fields     list: subfields, each a text field (label, max, multiline)
|
*/

$heading = ['type' => 'text', 'label' => 'Heading', 'max' => 120, 'required' => true];
$intro = ['type' => 'text', 'label' => 'Introduction', 'max' => 400, 'multiline' => true, 'required' => true];
$item = [
    'title' => ['label' => 'Title', 'max' => 80],
    'body' => ['label' => 'Text', 'max' => 300, 'multiline' => true],
];

return [

    'home' => [
        'title' => 'Home page',
        'description' => 'Copy for the home page sections. The hero, featured cars, section order and trust figures are on the Home page settings tab.',
        'fields' => [
            'about_heading' => [...$heading, 'section' => 'About'],
            'about_body' => ['type' => 'markdown', 'label' => 'Text', 'max' => 3000, 'required' => true],
            'about_image' => ['type' => 'image', 'label' => 'Image'],
            'requirements' => [
                'type' => 'list',
                'label' => 'Rental requirements',
                'section' => 'Requirements',
                'help' => 'The three numbered steps (01–03).',
                'items' => 3,
                'fields' => $item,
                'required' => true,
            ],
            'why_choose_us' => [
                'type' => 'list',
                'label' => 'Reasons',
                'section' => 'Why choose us',
                'help' => 'The four numbered reasons (01–04).',
                'items' => 4,
                'fields' => $item,
                'required' => true,
            ],
            'contact_heading' => [...$heading, 'section' => 'Contact call to action'],
            'contact_body' => ['type' => 'text', 'label' => 'Text', 'max' => 300, 'multiline' => true, 'required' => true],
        ],
    ],

    'about' => [
        'title' => 'About us',
        'description' => 'The About page.',
        'fields' => [
            'heading' => $heading,
            'intro' => $intro,
            'body' => ['type' => 'markdown', 'label' => 'Story', 'required' => true],
            'image' => ['type' => 'image', 'label' => 'Image'],
        ],
    ],

    'services' => [
        'title' => 'Services',
        'description' => 'The header of the Services page. The services themselves are edited under Services.',
        'fields' => ['heading' => $heading, 'intro' => $intro],
    ],

    'fleet' => [
        'title' => 'Fleet',
        'description' => 'The header of the Fleet page.',
        'fields' => ['heading' => $heading, 'intro' => $intro],
    ],

    'rental-policy' => [
        'title' => 'Rental policy',
        'description' => 'The header of the Rental policy page. Each policy is edited under Policies.',
        'fields' => ['heading' => $heading, 'intro' => $intro],
    ],

    'contact' => [
        'title' => 'Contact',
        'description' => 'The header of the Contact page. Phone, email, address and map come from the General settings.',
        'fields' => ['heading' => $heading, 'intro' => $intro],
    ],

    'privacy' => [
        'title' => 'Privacy notice',
        'description' => 'The privacy notice required by the Data Protection Act 2019.',
        'fields' => [
            'heading' => $heading,
            'body' => ['type' => 'markdown', 'label' => 'Notice', 'required' => true],
        ],
    ],

];
