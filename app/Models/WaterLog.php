<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterLog extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
            'amount_ml' => 'integer',
        ];
    }
}
