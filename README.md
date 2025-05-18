# Filament MCP Server - Laravel Loop

The Laravel Loop Filament MCP Server is an extension for [Laravel Loop](https://github.com/kirschbaum-development/laravel-loop) that exposes your Filament Resources as an MCP server. This allows AI assistants and MCP clients to interact with your Filament Resources for data listing, querying, and (optionally) actions.

## What It Does

- Exposes your Filament Resources as MCP tools
- Allows AI assistants and MCP clients to:
  - List available Filament Resources
  - Describe resource structure, fields, columns, filters, and relationships
  - Query resource data with filters
  - (Optionally) Execute resource actions (bulk actions, etc.)

## Installation

1. Make sure you have Laravel Loop installed and configured.

2. Install the package:

```bash
composer require kirschbaum-development/laravel-loop-filament
```

3. Register the Filament toolkit in your application. This is typically done in a service provider (e.g., AppServiceProvider):

```php
use Kirschbaum\Loop\Loop;
use Kirschbaum\Loop\Filament\FilamentToolkit;

Loop::toolkit(FilamentToolkit::make());
```

By default, it exposes all your Filament resources. You can control which resources are exposed with the `resources` parameter.

```php
use Kirschbaum\Loop\Loop;
use Kirschbaum\Loop\Filament\FilamentToolkit;

Loop::toolkit(FilamentToolkit::make(resources: [
    \App\Filament\Resources\UserResource::class,
    \App\Filament\Resources\PostResource::class,
]));
```

By default, the toolkit is in read-only mode. To expose the bulk actions of your Filament resources, you can register the tool with ReadWrite model.

```php
use Kirschbaum\Loop\Loop;
use Kirschbaum\Loop\Filament\FilamentToolkit;
use Kirschbaum\Loop\Enums\Mode;

Loop::toolkit(
    FilamentToolkit::make(mode: Mode::ReadWrite)
);
```

## Usage


## Security
Only expose the MCP endpoint to trusted clients. Use authentication middleware (e.g., Sanctum) for HTTP endpoints.

## License

MIT