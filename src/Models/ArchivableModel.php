<?php

namespace Lab2view\ModelArchive\Models;

use Illuminate\Database\Eloquent\Model;
use Lab2view\ModelArchive\Traits\Archivable;

/**
 * @template TModel of Model
 *
 * class ArchiveModel
 *
 * @extends ReadArchiveModel<TModel>
 *
 * @use Archivable<TModel>
 */
abstract class ArchivableModel extends ReadArchiveModel
{
    use Archivable;
}
