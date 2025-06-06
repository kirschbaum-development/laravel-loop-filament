<?php

use Kirschbaum\Loop\Filament\DescribeFilamentResourceTool;
use Tests\Feature\TestPostWithRelationsResource;
use Tests\Feature\TestUserResource;
use Tests\Feature\TestUserWithRelationsResource;

it('can instantiate the describe filament resource tool', function () {
    $tool = new DescribeFilamentResourceTool;

    expect($tool)->toBeInstanceOf(DescribeFilamentResourceTool::class);
    expect($tool->getName())->toBe('describe_filament_resource');
});

it('can extract table schema using the tool', function () {
    $tool = new DescribeFilamentResourceTool;
    $resource = app(TestUserResource::class);

    $tableSchema = $tool->extractTableSchema($resource);

    expect($tableSchema)->toBeArray()
        ->and($tableSchema)->toHaveKeys(['columns', 'filters', 'actions'])
        ->and($tableSchema['columns'])->toBeArray()
        ->and($tableSchema['columns'])->toHaveCount(3);

    $columnNames = collect($tableSchema['columns'])->pluck('name')->toArray();
    expect($columnNames)->toContain('name', 'email', 'created_at');
});

it('can extract column properties through the tool', function () {
    $tool = new DescribeFilamentResourceTool;
    $resource = app(TestUserResource::class);

    $tableSchema = $tool->extractTableSchema($resource);
    $columns = $tableSchema['columns'];

    $nameColumn = collect($columns)->firstWhere('name', 'name');
    expect($nameColumn)->toBeArray()
        ->and($nameColumn['searchable'])->toBeTrue()
        ->and($nameColumn['sortable'])->toBeTrue();

    $emailColumn = collect($columns)->firstWhere('name', 'email');
    expect($emailColumn)->toBeArray()
        ->and($emailColumn['searchable'])->toBeTrue()
        ->and($emailColumn['sortable'])->toBeTrue();
});

it('can extract filters through the tool', function () {
    $tool = new DescribeFilamentResourceTool;
    $resource = app(TestUserResource::class);

    $tableSchema = $tool->extractTableSchema($resource);
    $filters = $tableSchema['filters'];

    $nameFilter = collect($filters)->firstWhere('name', 'name');
    expect($nameFilter)->toBeArray()
        ->and($nameFilter['type'])->toBe('select')
        ->and($nameFilter['options'])->toBeArray();

    $emailFilter = collect($filters)->firstWhere('name', 'email');
    expect($emailFilter)->toBeArray()
        ->and($emailFilter['type'])->toBe('searchable_column');

    $createdAtFilter = collect($filters)->firstWhere('name', 'created_at');
    expect($createdAtFilter)->toBeArray()
        ->and($createdAtFilter['type'])->toBe('form')
        ->and($createdAtFilter['form'])->toBeArray()
        ->and($createdAtFilter['form'][0])->toBeArray()
        ->and($createdAtFilter['form'][0]['name'])->toBe('created_at_after')
        ->and($createdAtFilter['form'][0]['type'])->toBe('datetime')
        ->and($createdAtFilter['form'][1])->toBeArray()
        ->and($createdAtFilter['form'][1]['name'])->toBe('created_at_before')
        ->and($createdAtFilter['form'][1]['type'])->toBe('datetime');
});

it('can extract bulk actions through the tool', function () {
    $tool = new DescribeFilamentResourceTool;
    $resource = app(TestUserResource::class);

    $tableSchema = $tool->extractTableSchema($resource);
    $bulkActions = $tableSchema['actions']['bulk'];

    expect($bulkActions)->toBeArray()
        ->and($bulkActions)->toContain('delete');
});

it('can extract resource relationships through the tool', function () {
    $tool = new DescribeFilamentResourceTool;
    $resource = app(TestUserWithRelationsResource::class);

    $relationships = $tool->extractRelationshipsInfo($resource);

    expect($relationships)->toBeArray()
        ->and($relationships)->toHaveKeys(['posts', 'comments']);

    expect($relationships['posts'])->toBeArray()
        ->and($relationships['posts']['type'])->toBe('HasMany')
        ->and($relationships['posts']['manager'])->toBe(\Tests\Feature\TestPostsRelationManager::class);

    expect($relationships['comments'])->toBeArray()
        ->and($relationships['comments']['type'])->toBe('HasMany')
        ->and($relationships['comments']['manager'])->toBe(\Tests\Feature\TestCommentsRelationManager::class);
});

it('can describe a complete resource using the tool', function () {
    $tool = new DescribeFilamentResourceTool;

    $result = $tool->describe(TestUserWithRelationsResource::class);

    expect($result)->toBeArray()
        ->and($result)->toHaveKeys(['resource', 'model', 'table', 'relationships'])
        ->and($result['resource'])->toBe('TestUserWithRelationsResource')
        ->and($result['model'])->toBe(\Tests\Feature\TestUser::class)
        ->and($result['table'])->toBeArray()
        ->and($result['relationships'])->toBeArray();

    // Verify table structure
    expect($result['table']['columns'])->toHaveCount(3);
    expect($result['table']['actions']['bulk'])->toContain('delete');

    // Verify relationships structure
    expect($result['relationships'])->toHaveKeys(['posts', 'comments']);
});

it('can extract different relationship types including belongsTo', function () {
    $tool = new DescribeFilamentResourceTool;
    $resource = app(TestPostWithRelationsResource::class);

    $relationships = $tool->extractRelationshipsInfo($resource);

    expect($relationships)->toBeArray()
        ->and($relationships)->toHaveKeys(['comments', 'category']);

    expect($relationships['comments'])->toBeArray()
        ->and($relationships['comments']['type'])->toBe('HasMany')
        ->and($relationships['comments']['manager'])->toBe(\Tests\Feature\TestCommentsRelationManager::class);

    expect($relationships['category'])->toBeArray()
        ->and($relationships['category']['type'])->toBe('BelongsTo')
        ->and($relationships['category']['manager'])->toBe(\Tests\Feature\TestCategoryRelationManager::class);
});

it('can describe a resource with mixed relationship types', function () {
    $tool = new DescribeFilamentResourceTool;

    $result = $tool->describe(TestPostWithRelationsResource::class);

    expect($result)->toBeArray()
        ->and($result)->toHaveKeys(['resource', 'model', 'table', 'relationships'])
        ->and($result['resource'])->toBe('TestPostWithRelationsResource')
        ->and($result['model'])->toBe(\Tests\Feature\TestPost::class);

    // Verify we have both relationship types
    $relationships = $result['relationships'];
    expect($relationships)->toHaveKeys(['comments', 'category'])
        ->and($relationships['comments']['type'])->toBe('HasMany')
        ->and($relationships['category']['type'])->toBe('BelongsTo');
});
