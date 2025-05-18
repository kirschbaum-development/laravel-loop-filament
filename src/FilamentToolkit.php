<?php

namespace Kirschbaum\Loop\Filament;

use Kirschbaum\Loop\Enums\Mode;
use Filament\Resources\Resource;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Toolkit;
use Kirschbaum\Loop\Collections\ToolCollection;
use Kirschbaum\Loop\Filament\ExecuteResourceActionTool;
use Kirschbaum\Loop\Filament\ListFilamentResourcesTool;
use Kirschbaum\Loop\Filament\GetFilamentResourceDataTool;
use Kirschbaum\Loop\Filament\DescribeFilamentResourceTool;

/**
 * @method static self make(Resource[] $resources, Mode $mode = Mode::ReadOnly)
 */
class FilamentToolkit implements Toolkit
{
    use Makeable;

    /**
     * @param  resource[]  $resources
     */
    public function __construct(
        public readonly array $resources = [],
        public readonly Mode $mode = Mode::ReadOnly,
    ) {}

    public function getTools(): ToolCollection
    {
        $tools = [
            ListFilamentResourcesTool::make(
                resources: $this->resources,
            ),
            DescribeFilamentResourceTool::make(),
            GetFilamentResourceDataTool::make(),
        ];

        if ($this->mode === Mode::ReadWrite) {
            $tools[] = ExecuteResourceActionTool::make();
        }

        return new ToolCollection($tools);
    }
}
