<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use BelongsToAccount, HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'type',
        'document_type',
        'document_number',
        'names',
        'paternal_last_name',
        'maternal_last_name',
        'company_name',
        'display_name',
        'mobile',
        'phone',
        'email',
        'address',
        'bank_name',
        'account_number',
        'cci',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
