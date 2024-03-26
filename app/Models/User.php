<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class User extends Model
{
    use HasFactory;

    protected $fillable = ['first_name', 'last_name', 'email'];
    protected $hidden = ['password', 'token'];

    public $timestamps = false;

    public function getFullnameAttribute() {
        return $this->first_name.' '.$this->last_name;
    }

    public function files() {
        return $this->belongsToMany(File::class, 'file_user', 'user_id', 'file_id');
    }
}
