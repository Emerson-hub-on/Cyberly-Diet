<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return [
            'performed_on' => 'date',
            'duration_min' => 'integer',
            'calories_burned' => 'integer',
        ];
    }
}
