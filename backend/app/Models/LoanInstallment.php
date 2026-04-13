<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanInstallment extends Model {
    use HasFactory;
    protected $fillable = ['loan_id','installment_no','due_date','amount','paid_amount','status','paid_date','processed_by','notes'];
    protected $casts    = ['due_date'=>'date','paid_date'=>'date','amount'=>'float','paid_amount'=>'float'];
    public function loan()        { return $this->belongsTo(Loan::class); }
    public function processedBy() { return $this->belongsTo(User::class,'processed_by'); }
}
