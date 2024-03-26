<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class File extends Model
{
    use HasFactory;

    protected $fillable = ['file_id', 'url', 'owner_id'];

    protected $hidden = ['id'];
    public $timestamps = false;

    public function owners() {
        return $this->belongsToMany(User::class)->withPivot('type');
    }
}
