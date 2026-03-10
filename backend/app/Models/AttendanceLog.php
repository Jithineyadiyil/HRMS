<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model {
    protected $fillable = ['employee_id','date','check_in','check_out','total_minutes','status','source','ip_address','notes'];
    protected $casts = ['date'=>'date'];
    public function employee() { return $this->belongsTo(Employee::class); }
}
