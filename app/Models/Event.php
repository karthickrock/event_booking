<?php
namespace App\Models;

use App\Traits\CommonQueryScopes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory, HasUuids, CommonQueryScopes;

    protected $fillable = ['title', 'description', 'date', 'location', 'created_by'];
    protected $casts = ['date' => 'datetime:Y-m-d H:i:s'];

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}