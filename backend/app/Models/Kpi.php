<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Kpi extends Model {
    protected $fillable = ['title','description','department_id','employee_id','category','target_value','unit','weight','year','is_active'];
    public function department() { return $this->belongsTo(Department::class); }
    public function employee() { return $this->belongsTo(Employee::class); }
}
