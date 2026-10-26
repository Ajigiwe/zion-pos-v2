<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use BelongsToShop;

    // Linked to purchases at a later phase; product importers reference by name.
    protected $fillable = ['name', 'phone', 'email', 'address'];
}
