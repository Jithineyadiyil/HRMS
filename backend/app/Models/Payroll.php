<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model {
    protected $fillable = ['cycle_name','month','period_start','period_end','status','total_gross','total_deductions','total_net','created_by','approved_by','approved_at','notes'];
    protected $casts = ['period_start'=>'date','period_end'=>'date','approved_at'=>'datetime','total_gross'=>'decimal:2','total_deductions'=>'decimal:2','total_net'=>'decimal:2'];
    public function payslips() { return $this->hasMany(Payslip::class); }
    public function creator() { return $this->belongsTo(User::class,'created_by'); }
    public function approver() { return $this->belongsTo(User::class,'approved_by'); }
    public function scopePending($q) { return $q->where('status','pending_approval'); }
}
