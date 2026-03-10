<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model {
    use SoftDeletes;
    protected $fillable = ['name', 'code', 'description', 'parent_id', 'manager_id', 'headcount_budget', 'is_active'];

    public function parent() { return $this->belongsTo(Department::class, 'parent_id'); }
    public function children() { return $this->hasMany(Department::class, 'parent_id'); }
    public function manager() { return $this->belongsTo(Employee::class, 'manager_id'); }
    public function employees() { return $this->hasMany(Employee::class); }
    public function designations() { return $this->hasMany(Designation::class); }

    public function excuseLimits() { return $this->hasMany(DepartmentExcuseLimit::class); }

    public function allChildren() {
        return $this->children()->with('allChildren');
    }
}
