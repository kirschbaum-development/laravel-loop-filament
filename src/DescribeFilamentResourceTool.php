<?php

namespace Kirschbaum\Loop\Filament;

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
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Tool;
use Kirschbaum\Loop\Filament\Concerns\ProvidesFilamentResourceInstance;
use Livewire\Component as LivewireComponent;
use Prism\Prism\Tool as PrismTool;

class DescribeFilamentResourceTool implements Tool
{
    use Makeable;
    use ProvidesFilamentResourceInstance;

    public function build(): PrismTool
    {
        return app(PrismTool::class)
            ->as($this->getName())
            ->for('Describes the structure, fields, columns, actions, and relationships for a given Filament resource. Must call the list_filament_resources tool before calling this tool.')
            ->withStringParameter('resource', 'The class name of the resource to describe.', required: true)
            ->using(function (string $resource) {
                return json_encode($this->describe($resource));
            });
    }

    public function getName(): string
    {
        return 'describe_filament_resource';
    }

    public function describe(string $resourceClass): array
    {
        $resource = $this->getResourceInstance($resourceClass);

        return [
            'resource' => class_basename($resource),
            'model' => $resourceClass::getModel(),
            // 'form' => $this->extractFormSchema($resourceClass),
            'table' => $this->extractTableSchema($resource),
            'relationships' => $this->extractRelationshipsInfo($resource),
        ];
    }

    public function extractBasicInfo(Resource $resource): array
    {
        return [
            'resource' => class_basename($resource),
            'model' => $resource::getModel(),
        ];
    }

    public function extractFormSchema(Resource $resource): array
    {
        $livewireComponent = new class extends LivewireComponent implements HasForms
        {
            use \Filament\Forms\Concerns\InteractsWithForms;
        };

        $form = $resource::form(new Form($livewireComponent));
        $fields = collect($form->getComponents(true))
            ->reject(fn (Component $component) => $component instanceof Grid || $component instanceof Fieldset)
            ->map(fn (Component $component) => $this->mapFormComponent($component, $resource))
            ->filter()
            ->values()
            ->all();

        return ['fields' => $fields];
    }

    public function extractNavigationInfo(Resource $resource): array
    {
        return [
            'group' => $resource::getNavigationGroup(),
            'icon' => $resource::getNavigationIcon(),
            'label' => $resource::getNavigationLabel() ?: $resource::getPluralModelLabel(),
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
                // Relationship details are often defined within the manager or inferred by naming.
                // This requires more specific introspection or assumptions based on conventions.
                // Placeholder: Use manager class name as key.
                $relationName = $manager->getRelationshipName();
                $modelClass = $resource::getModel();
                $modelInstance = new $modelClass;
                $relation = $modelInstance->$relationName();

                $relationships[$relationName] = [
                    'type' => class_basename($relation),
                    'manager' => $managerClass,
                    'model' => get_class($relation->getRelated()),
                    'foreignKey' => $relation->getForeignKeyName(),
                ];
            } catch (\Throwable $e) {
                // Log error if manager instantiation fails
            }
        }

        return $relationships;
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
                'filters' => array_values($filters),
                'actions' => [
                    'bulk' => $bulkActions,
                ],
                'pagination' => [
                    'total' => 'Total number of records (int)',
                    'per_page' => 'Number of records per page (int)',
                    'current_page' => 'Current page number (int)',
                ],
            ];
        } catch (Exception $e) {
            Log::error("Error extracting table schema for resource {$resource}: {$e->getMessage()}");

            return [];
        }
    }

    public function mapComponentType(Component $component): string
    {
        return match (true) {
            $component instanceof TextInput => 'text',
            $component instanceof Select => 'select',
            $component instanceof DateTimePicker => 'datetime',
            $component instanceof \Filament\Forms\Components\RichEditor => 'richEditor',
            $component instanceof \Filament\Forms\Components\Textarea => 'textarea',
            $component instanceof \Filament\Forms\Components\Checkbox => 'checkbox',
            $component instanceof \Filament\Forms\Components\Toggle => 'toggle',
            // Add more mappings as needed
            default => class_basename($component), // Fallback to class name
        };
    }

    public function mapFilterType(BaseFilter $filter): string
    {
        return match (true) {
            $filter instanceof TernaryFilter => 'boolean',
            $filter instanceof SelectFilter => 'select',
            default => class_basename($filter),
        };
    }

    public function mapFormComponent(Component $component, ?Resource $resource = null): ?array
    {
        $baseInfo = [
            'name' => $component->getName(),
            'type' => $this->mapComponentType($component),
            'label' => $component->getLabel(),
            'required' => method_exists($component, 'isRequired') ? $component->isRequired() : null,
        ];

        if ($component instanceof TextInput) {
            $baseInfo['maxLength'] = $component->getMaxLength();
        }

        if ($resource && $component instanceof Select && $component->getRelationshipName()) {
            $modelClass = $resource::getModel();
            $modelInstance = app($modelClass);
            $relationshipDefinition = $modelInstance->{$component->getRelationshipName()}();

            $baseInfo['relationship'] = [
                'type' => class_basename($relationshipDefinition), // e.g., BelongsTo
                'model' => get_class($relationshipDefinition->getRelated()),
                'displayColumn' => $component->getRelationshipTitleAttribute(),
                'foreignKey' => $relationshipDefinition->getForeignKeyName(),
            ];
        }

        return $baseInfo;
    }

    public function mapTableAction(Action|BulkAction $action): string
    {
        $name = $action->getName();

        return match ($name) {
            'view', 'edit', 'delete', 'forceDelete', 'restore', 'replicate' => $name,
            default => $name,
        };
    }

    public function mapTableColumn(Column $column): array
    {
        $baseInfo = [
            'name' => $column->getName(),
            'label' => $column->getLabel(),
            'searchable' => $column->isSearchable(),
            'sortable' => $column->isSortable(),
            'hidden' => $column->isHidden(),
        ];

        return $baseInfo;
    }

    public function mapTableFilter(BaseFilter $filter): array
    {
        $baseInfo = [
            'name' => $filter->getName(),
            'label' => $filter->getLabel(),
            'type' => $this->mapFilterType($filter),
        ];

        if ($filter->hasFormSchema()) {
            $baseInfo['usage'] = 'Please use the form schema to filter the data.';
            $baseInfo['type'] = 'form';
            $baseInfo['form'] = collect($filter->getFormSchema())
                ->reject(fn (Component $component) => $component instanceof Grid || $component instanceof Fieldset)
                ->map(fn (Component $component) => $this->mapFormComponent($component))
                ->filter()
                ->values()
                ->all();
        }

        if ($filter instanceof TernaryFilter) {
            // Condition is implicit (true/false/all)
        } elseif ($filter instanceof SelectFilter) {
            $baseInfo['options'] = 'Dynamic';

            if (method_exists($filter, 'getOptions') && is_array($options = $filter->getOptions())) {
                $baseInfo['options'] = $options;
            }
        }

        return $baseInfo;
    }
}
