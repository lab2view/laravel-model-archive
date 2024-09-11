# lab2view/laravel-model-archive

A simple package for making Laravel Eloquent models 'archivable'. This package allows for the easy archiving of models in a secondary database and provides the necessary macros to retrieve them when needed.

## Installation

You can install the package via composer:

```bash
composer require lab2view/laravel-model-archive
```

## Usage

#### Migrations

Archiving is done in 2 steps. The first consists of copying the models to the database dedicated to archiving and the second step is to check that the archiving of said models has been done well and to delete them from the main database. This package to work must create a polymorphic table intended to record the archived archivable models

```php
Schema::create('archives', function (Blueprint $table) {
    $table->id();
    $table->string("archive_with");
    $table->morphs("archivable");
    $table->timestamp('validated_at')->nullable();
    $table->timestamps();
});
```

To publish migration file use

```
php artisan vendor:publish --provider="Lab2view\ModelArchive\ModelArchiveServiceProvider" --tag="migrations"
```

#### Commands

This package provides 2 commands to respectively copy the archivable models to the database dedicated to archiving and a second one to validate the archiving and delete the archived elements from the main database.
```
lab2view:model_archive
lab2view:validate_model_archive
``````
This commands require direct manipulation with the database by executing queries on the model defined as archiveable. For example, using the ```LadaCacheTrait``` trait of spiritix/lada-cache package implies that queries on models do not always return the ```Illuminate\Database\Eloquent\Builder``` interface but a builder version specific to this package based on the cache. In this case, it is necessary to execute the ```lada-cache:desable``` command before the commands and ```lada-cache:enable``` after.

#### Between Commands 
It is possible to register callables that will be called before or after commands or a specific command by registering them in the ```between_commands``` config. Example:

``` php

class BeforeAndAfterAnything {
    public function __invoke()
    {
        // Do Anything after and before commands
    }
}

class BeforeAnything {
    public function __invoke()
    {
        Artisan::command("lada-cache:desable", ...);
    }
}

class AfterAnything {
    public function __invoke()
    {
        Artisan::command("lada-cache:enable", ...);
    }
}

function afterModelArchive(){
    // Do anything after the lab2view:model_archive command has been successfully executed
}

return [
    ...,
    'between_commands' => [
        'all' => [new BeforeAndAfterAnything],
        'before' => [
            'all' => [new BeforeAnything],
            'lab2view:model_archive' => ['afterModelArchive'],
            'lab2view:validate_model_archive' => []
        ],
        'after' => [
            'all' => [],
            'lab2view:model_archive' => [],
            'lab2view:validate_model_archive' => []
        ]
    ]
];

```
#### Config

Archiving needs to know the database to dedicate to archiving [archve_db_connection] and the main database from which to clone archiveable models [main_db_connection].

```
[
    'main_db_connection' => env('DB_CONNECTION', 'mysql'),
    'archive_db_connection' => env('ARCHIVE_DB_CONNECTION', 'archive'),
    'archive_delete_from_main' => env('ARCHIVE_DELETE_FROM_MAIN', true),
]
```
To publish config file use:

```
php artisan vendor:publish --provider="Lab2view\ModelArchive\ModelArchiveServiceProvider" --tag="config"
```

#### Eloquent
You can now, safely, include the `Archivable` trait in your Eloquent model:

``` php
namespace App\Models;

use \Illuminate\Database\Eloquent\Model;
use Lab2view\ModelArchive\Traits\Archivable;

class Sale extends Model {

    use Archivable;
    ...
}
```

#### Extensions

The extensions shipped with this trait include; `Archivable`, `Archived`, `Unarchived`, `OnlyArchived` and can be used accordingly:

```php 
$onlyArchivedSales = Sale::select('*')->onlyArchived();
```

By default, the global scope of this trait uses the `unarchived` extension when the trait `Archivable` is added to a model. This prevents manipulation of archived models whose validation has not yet been done by the second command and which still exist in the source database.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.