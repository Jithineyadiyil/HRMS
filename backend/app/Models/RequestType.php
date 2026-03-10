<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RequestType extends Model {
    protected $fillable = ['name','code','category','description','instructions','sla_days',
        'requires_attachment','requires_manager_approval','is_active','sort_order','icon','color'];
    protected $casts = ['requires_attachment'=>'boolean','requires_manager_approval'=>'boolean','is_active'=>'boolean'];
    public function requests() { return $this->hasMany(EmployeeRequest::class); }
}
