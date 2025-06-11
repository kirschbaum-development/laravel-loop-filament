<?php

namespace Kirschbaum\Loop\Filament;

use Exception;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use JsonException;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Tool;
use Kirschbaum\Loop\Exceptions\LoopMcpException;
use Kirschbaum\Loop\Filament\Concerns\ProvidesFilamentResourceInstance;
use Kirschbaum\Loop\Filament\Exceptions\FilamentResourceIndexPageDoesNotExist;
use Prism\Prism\Tool as PrismTool;
use Throwable;

class GetFilamentResourceDataTool implements Tool
{
    use Makeable;
    use ProvidesFilamentResourceInstance;

    public function build(): PrismTool
    {
        return app(PrismTool::class)
            ->as($this->getName())
            ->for('Retrieves paginated data from a Filament resource with optional filtering. Response includes page count and total record count. Must call describe_filament_resource first to understand available filters and structure. Use filters to narrow results when possible for better performance.')
            ->withStringParameter('resource', 'The resource class name of the resource to get data for, from the list_filament_resources tool.')
            ->withStringParameter('filters', 'JSON string of filters to apply (e.g., \'{"status": "published", "author_id": [1, 2]}\').', required: false)
            ->withNumberParameter('perPage', 'The resource data is paginated. This is the number of records per page. It defaults to 10. Maximum is 100.', required: false)
            ->withNumberParameter('page', 'The resource data is paginated. This is the page the paginated results should be from.', required: false)
            ->using(function (string $resource, ?string $filters = null, ?int $perPage = 10, ?int $page = null) {
                $resource = $this->getResourceInstance($resource);
                $filters = $this->parseFilters($filters);

                try {
                    $listPage = $this->getListPage($resource);

                    $table = $listPage->getTable();
                    $tableColumns = $table->getColumns();

                    collect($tableColumns)
                        ->filter(fn (Column $column) => $column->isSearchable() && ! str_contains($column->getName(), '.')) // Only direct model attributes for now
                        ->filter(fn (Column $column) => isset($filters[$column->getName()]))
                        ->each(function (Column $column) use (&$listPage, $filters) {
                            $listPage->tableSearch = $filters[$column->getName()];
                        });

                    $listPage->resetTableFiltersForm();

                    foreach ($listPage->getTable()->getFilters() as $filter) {
                        if (method_exists($filter, 'isMultiple') && $filter->isMultiple()) {
                            $listPage->tableFilters[$filter->getName()] = [
                                'values' => isset($filters[$filter->getName()])
                                    ? (array) $filters[$filter->getName()]
                                    : null,
                            ];
                        } else {
                            $listPage->tableFilters[$filter->getName()] = [
                                'value' => $filters[$filter->getName()] ?? null,
                            ];

                            if ($filter->hasFormSchema()) {
                                foreach ($filter->getFormSchema() as $formSchema) {
                                    $listPage->tableFilters[$filter->getName()][$formSchema->getName()] =
                                        $filters[$formSchema->getName()] ?? null;
                                }
                            }
                        }
                    }

                    $perPage = $perPage > 100 ? 100 : $perPage;
                    $results = $listPage->getFilteredTableQuery()->paginate(perPage: $perPage, page: $page);

                    $outputData = [
                        'data' => $results->getCollection()
                            ->map(function (Model $model) use ($tableColumns) {
                                $rowData = [
                                    $model->getKeyName() => $model->getKey(),
                                ];

                                foreach ($tableColumns as $column) {
                                    /** @var Column $column */
                                    $columnName = $column->getName();

                                    try {
                                        if (str_contains($columnName, '.')) {
                                            $relationName = strtok($columnName, '.');

                                            if (method_exists($model, $relationName)) {
                                                $model->loadMissing($relationName);
                                                $value = data_get($model, $columnName);
                                            } else {
                                                $value = null;
                                                Log::warning("Relation '{$relationName}' not found on model for column '{$columnName}'.");
                                            }
                                        } else {
                                            $value = $model->getAttribute($columnName);
                                        }

                                        $rowData[$columnName] = $value;
                                    } catch (Exception $e) {
                                        $rowData[$columnName] = null;
                                        Log::error("Could not retrieve value for column '{$columnName}' on model ID {$model->getKey()}': {$e->getMessage()}");
                                    }
                                }

                                return $rowData;
                            }),

                        'pagination' => [
                            'total' => $results->total(),
                            'per_page' => $results->perPage(),
                            'current_page' => $results->currentPage(),
                        ],
                    ];

                    return json_encode($outputData);
                } catch (Exception $e) {
                    Log::error("[Laravel Loop] Error processing resource data: {$e->getMessage()}");
                    Log::debug('[Laravel Loop] Error trace: '.$e->getTraceAsString());

                    return sprintf('Error processing data for resource %s: %s', get_class($resource), $e->getMessage());
                }
            });
    }

    public function getName(): string
    {
        return 'get_filament_resource_data';
    }

    protected function parseFilters(?string $filtersJson = null): array
    {
        $filters = [];

        if (! $filtersJson) {
            return $filters;
        }

        try {
            $decodedFilters = json_decode($filtersJson, true, 512, JSON_THROW_ON_ERROR);

            if (is_array($decodedFilters)) {
                $filters = $decodedFilters;
            } else {
                throw new LoopMcpException('Error: Invalid JSON provided for filters.');
            }
        } catch (JsonException $e) {
            logger()->error($e);
            throw new LoopMcpException(sprintf('Error decoding filters JSON: %s', $e->getMessage()));
        }

        return $filters;
    }

    /**
     * @throws Throwable
     */
    protected function getListPage(Resource $resource): ListRecords
    {
        /**
         * @var ?PageRegistration $listPageClass
         */
        $listPageClass = data_get($resource::getPages(), 'index');

        throw_unless(
            $listPageClass instanceof PageRegistration,
            FilamentResourceIndexPageDoesNotExist::class,
            'No index page exists for ['.get_class($resource).']'
        );

        /**
         * @var class-string<ListRecords> $component
         */
        $component = $listPageClass->getPage();

        /**
         * @var ListRecords $listPage
         */
        $listPage = new $component;

        $listPage->bootedInteractsWithTable();

        return $listPage;
    }
}
