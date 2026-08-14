<?php

namespace App\Domains\Site\Models;

use App\Domains\Employee\Models\Employee;
use App\Models\User;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'slug', 'is_active'])]
#[UseFactory(SiteFactory::class)]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
