# lab2view/laravel-model-archive

A simple package to make Laravel Eloquent models "archivable". This package allows to easily move rarely accessed data (due to its duration for example) to a secondary database and provides the necessary methods to retrieve it when needed; thus reading relevant (frequently accessed) data from the primary database will be faster.

## Installation

You can install the package via composer:

```bash
composer require lab2view/laravel-model-archive
```

## Usage

#### Migrations

Archiving is done in 2 steps. The first is to copy the models marked `archivable` into the database dedicated to archives and the second step is to verify that the archiving of said data (models) has been done and to delete them from the main database. This package to work must create a polymorphic table intended to save the archived archivable models.

```php
Schema::create('archives', function (Blueprint $table) {
    $table->id();
    $table->string("archive_with");
    $table->morphs("archivable");
    $table->timestamp('validated_at')->nullable();
    $table->timestamps();
});
```

To publish the migration file use

```
php artisan vendor:publish --provider="Lab2view\ModelArchive\ModelArchiveServiceProvider" --tag="migrations"
```

#### Commands

This package provides 2 commands, dedicated respectively to copy the archivable models to the database dedicated to archiving and a second one to validate the archived data and delete this well-archived data from the main database.

```
lab2view:model-archive
lab2view:validate-model-archive
```

These commands require direct manipulation with the database by running queries on the model defined as archivable. For example, using the `LadaCacheTrait` trait from the spiritix/lada-cache package means that queries on models do not always return the `Illuminate\Database\Eloquent\Builder` interface, but a cache-based builder version specific to this package. In this case, it is necessary to run the `lada-cache:desable` command before the commands and `lada-cache:enable` after.

#### Between commands

It is possible to register callables that will be called before or after commands or a specific command by registering them in the `between_commands` configuration. Example:

```php
<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Artisan;

class RunCommand
{
    /**
     * Run command to flush lada-cache
     */
    public static function ladaCacheFlush(): void
    {
        Artisan::call('lada-cache:flush');
    }
}
```

```php
<?php

return [
    ...,
    'between_commands' => [
        'all' => [[RunCommand::class, 'ladaCacheFlush']],
        'before' => [
            'all' => [],
            'lab2view:archive-model' => [],
            'lab2view:validate-archive-model' => [],
        ],
        'after' => [
            'all' => [],
            'lab2view:archive-model' => [],
            'lab2view:validate-archive-model' => [],
        ],
    ],

];
```

#### Config

Le package doit connaître la base de données dédiée à l'archivage [archve_db_connection] et la base de données principale à partir de laquelle cloner les modèles archivables [main_db_connection].

```
[
    'main_db_connection' => env('DB_CONNECTION', 'mysql'),
    'archive_db_connection' => env('ARCHIVE_DB_CONNECTION', 'archive'),
    'archive_delete_from_main' => env('ARCHIVE_DELETE_FROM_MAIN', true),
]
```

To publish the configuration file, use:

```
php artisan vendor:publish --provider="Lab2view\ModelArchive\ModelArchiveServiceProvider" --tag="config"
```

#### Eloquent

You can now safely include the `Archivable` trait in your Eloquent model:

```php
namespace App\Models;

use \Illuminate\Database\Eloquent\Model;
use Lab2view\ModelArchive\Traits\Archivable;

class Sale extends Model {
    use Archivable;
    ...
}
```

If a relation is closely related to the model and must be copied along with the model, the `$archiveWith` property can be used to define these relations to be copied with the model. These relations must be marked with the `ReadArchive` trait so that reading can be done from the archives. However, they will not be deleted from the source database unless the `Archivable` trait is used.

#### Filter data to archive:

To define the data to be archived, the model must implement the `scopeArchivable` method. Only data that meets these conditions will be archived.

```php
class Payment extends Model
{
    use Archivable, HasFactory, LadaCacheTrait, SoftDeletes;

    public function scopeSearch(ArchiveBuilder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $query) use ($keyword) {
            $query->where('client_payment_id', 'LIKE', "%$keyword%")
                ->orWhere('payment_id', 'LIKE', "%$keyword%")
                ->orWhere('signature', 'LIKE', "%$keyword%")
                ->orWhere('svc_number', 'LIKE', "%$keyword%")
                ->orWhere('merchant_data', 'LIKE', "%$keyword%");
        })->fallbackToArchive()->fallbackRelation();
    }

    public function scopeArchivable(Builder $builder): Builder
    {
        return $builder->where(function ($query) {
            return $query->where('status', Status::SUCCESS->value)
                ->where('created_at', '<', now()->subDays(7));
        })->orWhere(function ($query) {
            return $query->whereIn('status', [Status::FAILED->value])
                ->where('created_at', '<', now()->subDays(14));
        })->orWhere(function ($query) {
            return $query->where('status', Status::INITIATED->value)
                ->where('created_at', '<', now()->subDays(3));
        });
    }
}
```

#### Extensions :

The extensions provided various functions to query builder to manipulate archives.

##### `fallbackToArchive`

It allows to trigger the automatic search in the archive database if the recovery (`get`, `all`, `exists`, `findFirst` ) does not find anything in the main database.

##### `onlyArchived`

It allows searching only in the database dedicated to archives

##### `fallbackRelations`

When archiving a model it is not necessary to mark all the relations with `archiveWith` property so that they are copied into the archive database in order to be able to be retrieved with the model in question. This method allows to search in the secondary database the relation if it is not found.

##### `load`

This package adds a `fallbackRelation` property when calling `load` and `with`. So if the model in question is in the archive database the loaded relations can be retrieved from the primary database

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
