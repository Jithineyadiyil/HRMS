<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model {
    protected $fillable = ['employee_id','title','type','file_path','file_name','mime_type','file_size','expiry_date','is_verified'];
    protected $casts = ['expiry_date'=>'date','is_verified'=>'boolean'];
    public function employee() { return $this->belongsTo(Employee::class); }
}
