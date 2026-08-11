<?php

use craft\elements\Category;
use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Categories;
use craft\fields\Matrix;
use craft\fields\Table;
use craft\helpers\StringHelper;
use craft\models\CategoryGroup;
use craft\models\CategoryGroup_SiteSettings;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;

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

function fieldByHandle(string $handle, string $class, array $config)
{
    $field = Craft::$app->getFields()->getFieldByHandle($handle);

    if (!$field) {
        $field = new $class($config);
    } else {
        Craft::configure($field, $config);
    }

    if (!Craft::$app->getFields()->saveField($field)) {
        throw new RuntimeException("Unable to save field $handle: " . json_encode($field->getErrors()));
    }

    return Craft::$app->getFields()->getFieldByHandle($handle);
}

$menu = parseMenuHtml($source);

if (!$menu) {
    throw new RuntimeException('No menu sections found in source file.');
}

$site = Craft::$app->getSites()->getPrimarySite();
$categories = Craft::$app->getCategories();
$entries = Craft::$app->getEntries();

$group = $categories->getGroupByHandle('menuCategories') ?? new CategoryGroup([
    'name' => 'Menu Categories',
    'handle' => 'menuCategories',
    'maxLevels' => 1,
]);

$group->setSiteSettings([
    new CategoryGroup_SiteSettings([
        'siteId' => $site->id,
        'hasUrls' => false,
    ]),
]);

if (!$categories->saveGroup($group)) {
    throw new RuntimeException('Unable to save menu category group: ' . json_encode($group->getErrors()));
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

$sectionCategoryField = fieldByHandle('sectionCategory', Categories::class, [
    'name' => 'Section Category',
    'handle' => 'sectionCategory',
    'source' => "group:$group->uid",
    'sources' => ["group:$group->uid"],
    'branchLimit' => 1,
    'minRelations' => 1,
    'maxRelations' => 1,
    'maintainHierarchy' => false,
]);

$sectionItemsField = fieldByHandle('sectionItems', Table::class, [
    'name' => 'Section Items',
    'handle' => 'sectionItems',
    'addRowLabel' => 'Add an item',
    'columns' => [
        'col1' => [
            'heading' => 'Item',
            'handle' => 'item',
            'type' => 'singleline',
            'width' => '',
        ],
        'col2' => [
            'heading' => 'Price',
            'handle' => 'price',
            'type' => 'singleline',
            'width' => '120px',
        ],
    ],
    'defaults' => [],
]);

$sectionType = $entries->getEntryTypeByHandle('menuSection') ?? new EntryType([
    'name' => 'Menu Section',
    'handle' => 'menuSection',
    'hasTitleField' => false,
    'titleFormat' => '{sectionCategory.one().title}',
    'showSlugField' => false,
    'showStatusField' => false,
]);

$sectionLayout = new FieldLayout([
    'type' => Entry::class,
]);
$sectionLayout->setTabs([
    new FieldLayoutTab([
        'layout' => $sectionLayout,
        'name' => 'Content',
        'elements' => [
            new CustomField($sectionCategoryField, ['required' => true]),
            new CustomField($sectionItemsField, ['required' => true]),
        ],
    ]),
]);
$sectionType->setFieldLayout($sectionLayout);

if (!$entries->saveEntryType($sectionType)) {
    throw new RuntimeException('Unable to save Menu Section entry type: ' . json_encode($sectionType->getErrors()));
}

$menuSectionsField = fieldByHandle('menuSections', Matrix::class, [
    'name' => 'Menu Sections',
    'handle' => 'menuSections',
    'viewMode' => Matrix::VIEW_MODE_BLOCKS,
    'propagationMethod' => PropagationMethod::All,
    'entryTypes' => [$sectionType],
]);

$menuIndexType = $entries->getEntryTypeByHandle('menuIndex');

if (!$menuIndexType) {
    throw new RuntimeException('Missing menuIndex entry type.');
}

$menuIndexLayout = new FieldLayout([
    'type' => Entry::class,
]);
$menuIndexLayout->setTabs([
    new FieldLayoutTab([
        'layout' => $menuIndexLayout,
        'name' => 'Content',
        'elements' => [
            new EntryTitleField(['required' => true]),
            new CustomField($menuSectionsField),
        ],
    ]),
]);
$menuIndexType->setFieldLayout($menuIndexLayout);

if (!$entries->saveEntryType($menuIndexType)) {
    throw new RuntimeException('Unable to update menuIndex entry type: ' . json_encode($menuIndexType->getErrors()));
}

$entry = Entry::find()
    ->section('menu')
    ->siteId($site->id)
    ->status(null)
    ->one();

if (!$entry) {
    throw new RuntimeException('Missing menu single entry.');
}

if (Craft::$app->getFields()->getFieldByHandle('menuItems')) {
    $entry->setFieldValue('menuItems', [
        'sortOrder' => [],
        'entries' => [],
    ]);
    Craft::$app->getElements()->saveElement($entry);

    foreach (Entry::find()->field('menuItems')->status(null)->all() as $oldItem) {
        Craft::$app->getElements()->deleteElement($oldItem, true);
    }
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

$oldMenuItemsField = Craft::$app->getFields()->getFieldByHandle('menuItems');

if ($oldMenuItemsField) {
    Craft::$app->getFields()->deleteField($oldMenuItemsField);
}

$oldMenuItemType = $entries->getEntryTypeByHandle('menuItem');

if ($oldMenuItemType) {
    $entries->deleteEntryType($oldMenuItemType);
}

foreach (['itemCategory', 'itemName', 'itemPrice'] as $oldFieldHandle) {
    $oldField = Craft::$app->getFields()->getFieldByHandle($oldFieldHandle);

    if ($oldField) {
        Craft::$app->getFields()->deleteField($oldField);
    }
}

echo sprintf("Seeded %d menu sections and %d menu items.\n", count($sortOrder), array_sum(array_map(fn(array $section) => count($section['items']), $menu)));
