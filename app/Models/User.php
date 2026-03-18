<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    protected $fillable = ['name', 'email', 'password', 'phone', 'role'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['password' => 'hashed'];

    public function events()
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function payments()
    {
        return $this->hasManyThrough(Payment::class, Booking::class); 
    }
}


