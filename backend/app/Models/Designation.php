<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Designation extends Model {
    protected $fillable = ['title', 'level', 'department_id', 'min_salary', 'max_salary', 'is_active'];
    public function department() { return $this->belongsTo(Department::class); }
    public function employees() { return $this->hasMany(Employee::class); }
}
