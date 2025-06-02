<?php

namespace Tests\Feature;

use Exception;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\Column;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Livewire\Component as LivewireComponent;

class TestableDescribeFilamentResourceTool
{
    public function getName(): string
    {
        return 'describe_filament_resource';
    }

    protected function getResourceInstance(string $resourceClass): Resource
    {
        try {
            $resource = app($resourceClass);

            if (! $resource instanceof Resource) {
                throw new Exception(sprintf('Could not find %s resource class', $resourceClass));
            }

            return $resource;
        } catch (Exception $e) {
            throw new Exception(sprintf('Could describe %s resource class. Error: %s', $resourceClass, $e->getMessage()));
        }
    }

    public function extractBasicInfo(Resource $resource): array
    {
        return [
            'resource' => class_basename($resource),
            'model' => $resource::getModel(),
        ];
    }

    public function extractRelationshipsInfo(Resource $resource): array
    {
        if (! method_exists($resource, 'getRelations')) {
            return [];
        }

        $relationshipManagers = $resource::getRelations();
        $relationships = [];

        foreach ($relationshipManagers as $managerClass) {
            try {
                $manager = app($managerClass);
                $relationName = $manager->getRelationshipName();
                
                // Try to determine relationship type by inspecting the model
                $relationshipType = $this->determineRelationshipType($resource, $relationName);

                $relationships[$relationName] = [
                    'type' => $relationshipType,
                    'manager' => $managerClass,
                ];
            } catch (\Throwable $e) {
                // Log error if manager instantiation fails
            }
        }

        return $relationships;
    }

    protected function determineRelationshipType(Resource $resource, string $relationName): string
    {
        try {
            $modelClass = $resource::getModel();
            $modelInstance = new $modelClass();
            
            if (method_exists($modelInstance, $relationName)) {
                $relation = $modelInstance->$relationName();
                
                return match(get_class($relation)) {
                    'Illuminate\Database\Eloquent\Relations\HasMany' => 'hasMany',
                    'Illuminate\Database\Eloquent\Relations\BelongsTo' => 'belongsTo',
                    'Illuminate\Database\Eloquent\Relations\HasOne' => 'hasOne',
                    'Illuminate\Database\Eloquent\Relations\BelongsToMany' => 'belongsToMany',
                    default => 'unknown',
                };
            }
        } catch (\Throwable $e) {
            // If we can't determine the type, return unknown
        }
        
        return 'unknown';
    }

    public function extractTableSchema(Resource $resource): array
    {
        try {
            $livewireComponent = new class extends LivewireComponent implements HasTable
            {
                use \Filament\Tables\Concerns\InteractsWithTable;

                public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
                {
                    return null;
                }
            };

            $table = $resource::table(new Table($livewireComponent));

            $columns = collect($table->getColumns())
                ->map(fn (Column $column) => $this->mapTableColumn($column))
                ->all();

            $existingFilters = collect($table->getFilters())
                ->map(fn (BaseFilter $filter) => $this->mapTableFilter($filter))
                ->all();

            $searchableColumnFilters = collect($columns)
                ->filter(fn (array $column) => $column['searchable'] ?? false)
                ->map(fn (array $column) => [
                    'name' => $column['name'],
                    'label' => $column['label'],
                    'type' => 'searchable_column',
                ])
                ->keyBy('name')
                ->all();

            $filters = array_merge($searchableColumnFilters, $existingFilters);

            $bulkActions = collect($table->getBulkActions())
                ->flatMap(function ($action) {
                    if ($action instanceof BulkActionGroup) {
                        return collect($action->getActions())
                            ->map(fn (BulkAction $childAction) => $this->mapTableAction($childAction));
                    }

                    return [$this->mapTableAction($action)];
                })
                ->all();

            return [
                'columns' => $columns,
                'filters' => array_values($filters), // Re-index the array
                'actions' => [
                    'bulk' => $bulkActions,
                ],
            ];
        } catch (Exception $e) {
            Log::error("Error extracting table schema for resource {$resource}: {$e->getMessage()}");

            return [];
        }
    }

    protected function mapComponentType(Component $component): string
    {
        return match (true) {
            $component instanceof TextInput => 'text',
            $component instanceof Select => 'select',
            $component instanceof DateTimePicker => 'datetime',
            $component instanceof \Filament\Forms\Components\RichEditor => 'richEditor',
            $component instanceof \Filament\Forms\Components\Textarea => 'textarea',
            $component instanceof \Filament\Forms\Components\Checkbox => 'checkbox',
            $component instanceof \Filament\Forms\Components\Toggle => 'toggle',
            default => class_basename($component), // Fallback to class name
        };
    }

    protected function mapFilterType(BaseFilter $filter): string
    {
        return match (true) {
            $filter instanceof TernaryFilter => 'boolean',
            $filter instanceof SelectFilter => 'select',
            default => class_basename($filter), // Fallback to class name
        };
    }

    protected function mapTableAction(Action|BulkAction $action): string
    {
        $name = $action->getName();

        return match ($name) {
            'view', 'edit', 'delete', 'forceDelete', 'restore', 'replicate' => $name,
            default => $name,
        };
    }

    protected function mapTableColumn(Column $column): array
    {
        return [
            'name' => $column->getName(),
            'label' => $column->getLabel(),
            'searchable' => $column->isSearchable(),
            'sortable' => $column->isSortable(),
            'hidden' => $column->isHidden(),
        ];
    }

    protected function mapTableFilter(BaseFilter $filter): array
    {
        $baseInfo = [
            'name' => $filter->getName(),
            'label' => $filter->getLabel(),
            'type' => $this->mapFilterType($filter),
        ];

        if ($filter instanceof TernaryFilter) {
            // Condition is implicit (true/false/all)
        } elseif ($filter instanceof SelectFilter) {
            $baseInfo['optionsSource'] = 'Dynamic/Callable'; // Getting exact source is complex

            // Try to get options if they are simple array
            if (method_exists($filter, 'getOptions') && is_array($options = $filter->getOptions())) {
                $baseInfo['optionsSource'] = $options;
            }
        }

        return $baseInfo;
    }

    public function describe(string $resourceClass): array
    {
        $resource = $this->getResourceInstance($resourceClass);

        return [
            'resource' => class_basename($resource),
            'model' => $resource::getModel(),
            'table' => $this->extractTableSchema($resource),
            'relationships' => $this->extractRelationshipsInfo($resource),
        ];
    }
}