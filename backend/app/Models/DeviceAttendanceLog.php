<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DeviceAttendanceLog extends Model
{
    protected $fillable = [
        'device_id','device_employee_number','employee_id',
        'punch_time','punch_type','verification_mode','processed',
    ];
    protected $casts = ['punch_time'=>'datetime','processed'=>'boolean'];
    public function device()   { return $this->belongsTo(AttendanceDevice::class,'device_id'); }
    public function employee() { return $this->belongsTo(Employee::class); }
}
