<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // Disable automatic timestamps since the existing table doesn't have updated_at
    public $timestamps = false;

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'email',
        'password',
        'student_no',
        'course',
        'year_level',
        'section',
        'notes',
        'role',
        'status',
        'school_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Password is hashed in the controller, not here
    // This mutator would cause double-hashing

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class, 'student_id');
    }

    public function borrowRequests()
    {
        return $this->hasMany(BorrowRequest::class, 'student_id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function isAdmin()
    {
        return strtolower($this->role) === 'admin';
    }

    public function isLibrarian()
    {
        return strtolower($this->role) === 'librarian';
    }

    public function isStudent()
    {
        return strtolower($this->role) === 'student';
    }

    public function getFullNameAttribute()
    {
        $name = $this->first_name;
        if ($this->middle_name) {
            $name .= ' ' . $this->middle_name;
        }
        $name .= ' ' . $this->last_name;
        if ($this->suffix && $this->suffix !== 'N/A') {
            $name .= ' ' . $this->suffix;
        }
        return $name;
    }
}
