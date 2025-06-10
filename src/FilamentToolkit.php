<?php

namespace Kirschbaum\Loop\Filament;

use Filament\Resources\Resource;
use Kirschbaum\Loop\Collections\ToolCollection;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Toolkit;
use Kirschbaum\Loop\Enums\Mode;

/**
 * @method static self make(string[] $resources = [], Mode $mode = Mode::ReadOnly)
 */
class FilamentToolkit implements Toolkit
{
    use Makeable;

    /**
     * @param  class-string<resource>[]  $resources
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
