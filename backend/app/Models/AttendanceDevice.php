<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AttendanceDevice extends Model
{
    protected $fillable = [
        'name','brand','ip_address','port','protocol','api_path','api_key',
        'username','password','timeout_seconds','employee_number_field',
        'is_active','last_synced_at','last_sync_status','last_sync_count','last_sync_error',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = ['password','api_key'];

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getPasswordAttribute($value): ?string
    {
        try { return $value ? Crypt::decryptString($value) : null; }
        catch (\Throwable) { return null; }
    }

    public function setApiKeyAttribute($value)
    {
        $this->attributes['api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getApiKeyAttribute($value): ?string
    {
        try { return $value ? Crypt::decryptString($value) : null; }
        catch (\Throwable) { return null; }
    }

    public function deviceLogs() { return $this->hasMany(DeviceAttendanceLog::class, 'device_id'); }
}
