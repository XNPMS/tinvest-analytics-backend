<?php

declare(strict_types=1);

namespace User\Entity;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    public const TABLE = 'users';

    /** @var string */
    protected $table = self::TABLE;
}