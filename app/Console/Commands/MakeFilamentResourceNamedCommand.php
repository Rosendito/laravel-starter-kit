<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Filament\Commands\FileGenerators\Resources\Pages\ResourceCreateRecordPageClassGenerator;
use Filament\Commands\FileGenerators\Resources\Pages\ResourceEditRecordPageClassGenerator;
use Filament\Commands\FileGenerators\Resources\Pages\ResourceListRecordsPageClassGenerator;
use Filament\Commands\FileGenerators\Resources\Pages\ResourceManageRecordsPageClassGenerator;
use Filament\Commands\FileGenerators\Resources\Pages\ResourceViewRecordPageClassGenerator;
use Filament\Commands\FileGenerators\Resources\Schemas\ResourceFormSchemaClassGenerator;
use Filament\Commands\FileGenerators\Resources\Schemas\ResourceInfolistSchemaClassGenerator;
use Filament\Commands\FileGenerators\Resources\Schemas\ResourceTableClassGenerator;
use Filament\Commands\MakeResourceCommand as BaseMakeResourceCommand;
use Filament\Resources\Pages\Page;
use Filament\Support\Commands\Exceptions\FailureCommandOutput;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

final class MakeFilamentResourceNamedCommand extends BaseMakeResourceCommand
{
    protected $name = 'make:filament-resource';

    protected $description = 'Create a new Filament resource with a custom resource class name.';

    /**
     * @return array<InputOption>
     */
    protected function getOptions(): array
    {
        return [
            ...parent::getOptions(),
            new InputOption(
                name: 'resource-name',
                shortcut: null,
                mode: InputOption::VALUE_REQUIRED,
                description: 'The base name of the resource (singular), used to infer the resource class and folder (e.g. Owner => Owners/OwnerResource).',
            ),
        ];
    }

    protected function configureLocation(): void
    {
        $resourceNameOption = $this->option('resource-name');

        if (is_string($resourceNameOption) && $resourceNameOption !== '') {
            $resourceBaseName = $this->normalizeResourceBaseName($resourceNameOption);

            $pluralDirectoryName = Str::pluralStudly($resourceBaseName);
            $resourceClassBasename = Str::singular($resourceBaseName).'Resource';

            $this->fqnEnd = "{$pluralDirectoryName}\\{$resourceClassBasename}";

            $resourceFqn = $this->resourcesNamespace.'\\'.$this->fqnEnd;

            /** @var class-string $resourceFqn */
            $this->fqn = $resourceFqn;

            // Use the standard Filament convention:
            // app/Filament/Resources/<Plural>/<Singular>Resource.php
            // This also avoids collisions when generating multiple resources for the same model.
            $this->namespace = (string) str($this->fqn)->beforeLast('\\');
            $this->directory = (string) str("{$this->resourcesDirectory}/{$pluralDirectoryName}")
                ->replace('\\', '/')
                ->replace('//', '/');

            return;
        }

        parent::configureLocation();
    }

    protected function configurePageRoutes(): void
    {
        $resourceBasename = $this->resourceBasename();
        $pluralResourceBasename = Str::pluralStudly($resourceBasename);

        if ($this->isSimple) {
            $managePageFqn = "{$this->namespace}\\Pages\\Manage{$pluralResourceBasename}";
            /** @var class-string<Page> $managePageFqn */
            /** @var array<string, array{class: class-string<Page>, path: string}> $routes */
            $routes = [
                'index' => [
                    'class' => $managePageFqn,
                    'path' => '/',
                ],
            ];

            $this->pageRoutes = $routes;

            return;
        }

        /** @var array<string, array{class: class-string<Page>, path: string}> $routes */
        $routes = [];

        if (blank($this->parentResourceFqn)) {
            $listPageFqn = "{$this->namespace}\\Pages\\List{$pluralResourceBasename}";
            /** @var class-string<Page> $listPageFqn */
            $routes['index'] = [
                'class' => $listPageFqn,
                'path' => '/',
            ];
        }

        $createPageFqn = "{$this->namespace}\\Pages\\Create{$resourceBasename}";
        /** @var class-string<Page> $createPageFqn */
        $routes['create'] = [
            'class' => $createPageFqn,
            'path' => '/create',
        ];

        if ($this->hasViewOperation) {
            $viewPageFqn = "{$this->namespace}\\Pages\\View{$resourceBasename}";
            /** @var class-string<Page> $viewPageFqn */
            $routes['view'] = [
                'class' => $viewPageFqn,
                'path' => '/{record}',
            ];
        }

        $editPageFqn = "{$this->namespace}\\Pages\\Edit{$resourceBasename}";
        /** @var class-string<Page> $editPageFqn */
        $routes['edit'] = [
            'class' => $editPageFqn,
            'path' => '/{record}/edit',
        ];

        $this->pageRoutes = $routes;
    }

    protected function createFormSchema(): void
    {
        if ($this->hasEmbeddedSchemas()) {
            return;
        }

        $resourceBasename = $this->resourceBasename();

        $path = "{$this->directory}/Schemas/{$resourceBasename}Form.php";

        throw_if(! $this->option('force') && $this->checkForCollision($path), FailureCommandOutput::class);

        $this->formSchemaFqn = "{$this->namespace}\\Schemas\\{$resourceBasename}Form";

        $this->writeFile($path, resolve(ResourceFormSchemaClassGenerator::class, [
            'fqn' => $this->formSchemaFqn,
            'modelFqn' => $this->modelFqn,
            'parentResourceFqn' => $this->parentResourceFqn,
            'isGenerated' => $this->isGenerated,
        ]));
    }

    protected function createInfolistSchema(): void
    {
        if (! $this->hasViewOperation) {
            return;
        }

        if ($this->hasEmbeddedSchemas()) {
            return;
        }

        $resourceBasename = $this->resourceBasename();

        $path = "{$this->directory}/Schemas/{$resourceBasename}Infolist.php";

        throw_if(! $this->option('force') && $this->checkForCollision($path), FailureCommandOutput::class);

        $this->infolistSchemaFqn = "{$this->namespace}\\Schemas\\{$resourceBasename}Infolist";

        $this->writeFile($path, resolve(ResourceInfolistSchemaClassGenerator::class, [
            'fqn' => $this->infolistSchemaFqn,
            'modelFqn' => $this->modelFqn,
            'parentResourceFqn' => $this->parentResourceFqn,
            'isGenerated' => $this->isGenerated,
        ]));
    }

    protected function createTable(): void
    {
        if ($this->hasEmbeddedTable()) {
            return;
        }

        $pluralResourceBasename = Str::pluralStudly($this->resourceBasename());

        $path = "{$this->directory}/Tables/{$pluralResourceBasename}Table.php";

        throw_if(! $this->option('force') && $this->checkForCollision($path), FailureCommandOutput::class);

        $this->tableFqn = "{$this->namespace}\\Tables\\{$pluralResourceBasename}Table";

        $this->writeFile($path, resolve(ResourceTableClassGenerator::class, [
            'fqn' => $this->tableFqn,
            'modelFqn' => $this->modelFqn,
            'parentResourceFqn' => $this->parentResourceFqn,
            'hasViewOperation' => $this->hasViewOperation,
            'isGenerated' => $this->isGenerated,
            'isSoftDeletable' => $this->isSoftDeletable,
            'isSimple' => $this->isSimple,
        ]));
    }

    protected function createManagePage(): void
    {
        if (! $this->isSimple) {
            return;
        }

        $pluralResourceBasename = Str::pluralStudly($this->resourceBasename());

        $path = "{$this->directory}/Pages/Manage{$pluralResourceBasename}.php";

        throw_if(! $this->option('force') && $this->checkForCollision($path), FailureCommandOutput::class);

        $this->writeFile($path, resolve(ResourceManageRecordsPageClassGenerator::class, [
            'fqn' => "{$this->namespace}\\Pages\\Manage{$pluralResourceBasename}",
            'resourceFqn' => $this->fqn,
        ]));
    }

    protected function createListPage(): void
    {
        if ($this->isSimple) {
            return;
        }

        if (filled($this->parentResourceFqn)) {
            return;
        }

        $pluralResourceBasename = Str::pluralStudly($this->resourceBasename());

        $path = "{$this->directory}/Pages/List{$pluralResourceBasename}.php";

        throw_if(! $this->option('force') && $this->checkForCollision($path), FailureCommandOutput::class);

        $this->writeFile($path, resolve(ResourceListRecordsPageClassGenerator::class, [
            'fqn' => "{$this->namespace}\\Pages\\List{$pluralResourceBasename}",
            'resourceFqn' => $this->fqn,
        ]));
    }

    protected function createCreatePage(): void
    {
        if ($this->isSimple) {
            return;
        }

        $resourceBasename = $this->resourceBasename();

        $path = "{$this->directory}/Pages/Create{$resourceBasename}.php";

        throw_if(! $this->option('force') && $this->checkForCollision($path), FailureCommandOutput::class);

        $this->writeFile($path, resolve(ResourceCreateRecordPageClassGenerator::class, [
            'fqn' => "{$this->namespace}\\Pages\\Create{$resourceBasename}",
            'resourceFqn' => $this->fqn,
        ]));
    }

    protected function createEditPage(): void
    {
        if ($this->isSimple) {
            return;
        }

        $resourceBasename = $this->resourceBasename();

        $path = "{$this->directory}/Pages/Edit{$resourceBasename}.php";

        throw_if(! $this->option('force') && $this->checkForCollision($path), FailureCommandOutput::class);

        $this->writeFile($path, resolve(ResourceEditRecordPageClassGenerator::class, [
            'fqn' => "{$this->namespace}\\Pages\\Edit{$resourceBasename}",
            'resourceFqn' => $this->fqn,
            'hasViewOperation' => $this->hasViewOperation,
            'isSoftDeletable' => $this->isSoftDeletable,
        ]));
    }

    protected function createViewPage(): void
    {
        if (! $this->hasViewOperation) {
            return;
        }

        if ($this->isSimple) {
            return;
        }

        $resourceBasename = $this->resourceBasename();

        $path = "{$this->directory}/Pages/View{$resourceBasename}.php";

        throw_if(! $this->option('force') && $this->checkForCollision($path), FailureCommandOutput::class);

        $this->writeFile($path, resolve(ResourceViewRecordPageClassGenerator::class, [
            'fqn' => "{$this->namespace}\\Pages\\View{$resourceBasename}",
            'resourceFqn' => $this->fqn,
        ]));
    }

    private function resourceBasename(): string
    {
        $resourceNameOption = $this->option('resource-name');

        if (is_string($resourceNameOption) && $resourceNameOption !== '') {
            return Str::singular($this->normalizeResourceBaseName($resourceNameOption));
        }

        return class_basename($this->modelFqn);
    }

    private function normalizeResourceBaseName(string $resourceName): string
    {
        $normalized = (string) str($resourceName)
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->replace('/', '\\');

        $base = Str::studly((string) str($normalized)->afterLast('\\'));

        if ($base === '') {
            return 'Resource';
        }

        if (Str::endsWith($base, 'Resource')) {
            $base = (string) str($base)->beforeLast('Resource');
        }

        return $base === '' ? 'Resource' : $base;
    }
}
