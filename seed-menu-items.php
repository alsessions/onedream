<?php

use craft\elements\Category;
use craft\elements\Entry;
use craft\helpers\StringHelper;

$source = __DIR__ . '/templates/menu/_seed.html';

if (!is_file($source)) {
    throw new RuntimeException("Missing source file: $source");
}

function slugValue(string $value): string
{
    return trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $value)), '-');
}

function parseMenuHtml(string $source): array
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTMLFile($source);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $sections = [];

    foreach ($xpath->query('//main//section') as $section) {
        $heading = trim($xpath->evaluate('string(.//h2[1])', $section));

        if ($heading === '') {
            continue;
        }

        $items = [];

        foreach ($xpath->query('.//li', $section) as $item) {
            $spans = $xpath->query('./span', $item);

            if ($spans->length < 2) {
                continue;
            }

            $name = trim($spans->item(0)->textContent);
            $price = trim($spans->item(1)->textContent);

            if ($name !== '' && $price !== '') {
                $items[] = compact('name', 'price');
            }
        }

        if ($items) {
            $sections[] = compact('heading', 'items');
        }
    }

    return $sections;
}

$menu = parseMenuHtml($source);

if (!$menu) {
    throw new RuntimeException('No menu sections found in source file.');
}

$site = Craft::$app->getSites()->getPrimarySite();
$categories = Craft::$app->getCategories();
$group = $categories->getGroupByHandle('menuCategories');

if (!$group) {
    throw new RuntimeException('Missing menuCategories category group. Run project-config/apply first.');
}

foreach (['sectionCategory', 'sectionItems', 'menuSections'] as $fieldHandle) {
    if (!Craft::$app->getFields()->getFieldByHandle($fieldHandle)) {
        throw new RuntimeException("Missing $fieldHandle field. Run project-config/apply first.");
    }
}

$categoryIdsByHeading = [];

foreach ($menu as $section) {
    $category = Category::find()
        ->group('menuCategories')
        ->title($section['heading'])
        ->siteId($site->id)
        ->status(null)
        ->one();

    if (!$category) {
        $category = new Category([
            'groupId' => $group->id,
            'siteId' => $site->id,
            'title' => $section['heading'],
            'slug' => slugValue($section['heading']),
            'enabled' => true,
        ]);
    }

    if (!Craft::$app->getElements()->saveElement($category)) {
        throw new RuntimeException("Unable to save category {$section['heading']}: " . json_encode($category->getErrors()));
    }

    $categoryIdsByHeading[$section['heading']] = $category->id;
}

$entry = Entry::find()
    ->section('menu')
    ->siteId($site->id)
    ->status(null)
    ->one();

if (!$entry) {
    throw new RuntimeException('Missing menu single entry.');
}

$existingSections = $entry->menuSections->status(null)->all();
$matrixEntries = [];
$sortOrder = [];
$i = 0;

foreach ($existingSections as $section) {
    $matrixEntries[$section->id] = [
        'enabled' => false,
    ];
}

foreach ($menu as $section) {
    $key = 'new' . (++$i);
    $sortOrder[] = $key;
    $matrixEntries[$key] = [
        'type' => 'menuSection',
        'enabled' => true,
        'fresh' => true,
        'fields' => [
            'sectionCategory' => [$categoryIdsByHeading[$section['heading']]],
            'sectionItems' => array_map(fn(array $item) => [
                'rowId' => StringHelper::uuid(),
                'item' => $item['name'],
                'price' => $item['price'],
            ], $section['items']),
        ],
    ];
}

$entry->setFieldValue('menuSections', [
    'sortOrder' => $sortOrder,
    'entries' => $matrixEntries,
]);

if (!Craft::$app->getElements()->saveElement($entry)) {
    throw new RuntimeException('Unable to seed menu entry: ' . json_encode($entry->getErrors()));
}

echo sprintf("Seeded %d menu sections and %d menu items.\n", count($sortOrder), array_sum(array_map(fn(array $section) => count($section['items']), $menu)));
