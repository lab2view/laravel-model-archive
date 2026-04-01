<?php

namespace Lab2view\ModelArchive\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ReadArchiveScope implements Scope
{
    public function __construct(
        protected string $archiveConnection,
    ) {}

    /**
     * Set of extensions to read archives
     *
     * @var array<int, string>
     */
    protected array $extensions = [];

    public function apply(Builder $builder, Model $model): void {}

    public function extend(Builder $builder): void
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }
    }

}
