<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    // Linked to purchases at a later phase; product importers reference by name.
    protected $fillable = ['name', 'phone', 'email', 'address'];
}
