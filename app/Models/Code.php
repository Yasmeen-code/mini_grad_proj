<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Code extends Model
{
    protected $fillable = [
        'source_code',
        'tokens',
        'errors',
        'ast',
        'assembly',
        'machine_code',
        'cpu_simulation',
        'compilation_steps'
    ];

    protected $casts = [
        'tokens' => 'array',
        'errors' => 'array',
        'ast' => 'array',
        'assembly' => 'array',
        'machine_code' => 'array',
        'cpu_simulation' => 'array',
        'compilation_steps' => 'array'
    ];
}
