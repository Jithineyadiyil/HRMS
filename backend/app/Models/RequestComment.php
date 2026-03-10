<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RequestComment extends Model {
    protected $fillable = ['request_id','user_id','comment','is_internal'];
    protected $casts    = ['is_internal'=>'boolean'];
    public function user()    { return $this->belongsTo(User::class); }
    public function request() { return $this->belongsTo(EmployeeRequest::class,'request_id'); }
}
