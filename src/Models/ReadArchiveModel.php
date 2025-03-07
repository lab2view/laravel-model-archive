<?php

namespace Lab2view\ModelArchive\Models;

use Illuminate\Database\Eloquent\Model;
use Lab2view\ModelArchive\Traits\ReadArchive;

/**
 * @template TModel of Model
 *
 * class ArchiveModel
 *
 * @use ReadArchive<Model>
 */
abstract class ReadArchiveModel extends Model
{
    use ReadArchive;
}
