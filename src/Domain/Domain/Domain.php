<?php

declare(strict_types=1);

namespace LogicPanel\Domain\Domain;

use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    protected $table = 'domains';

    protected $fillable = [
        'name',
        'user_id',
        'type', // primary, addon, subdomain, alias
        'path', // document root
        'parent_id' // for subdomains
    ];

    public function user()
    {
        return $this->belongsTo(\LogicPanel\Domain\User\User::class);
    }

    public function parent()
    {
        return $this->belongsTo(Domain::class, 'parent_id');
    }
}
