<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LoanType extends Model {
    protected $fillable = ['name','code','max_amount','max_installments','interest_rate','requires_guarantor','is_active','description'];
    protected $casts    = ['max_amount'=>'float','interest_rate'=>'float','requires_guarantor'=>'boolean','is_active'=>'boolean'];
    public function loans() { return $this->hasMany(Loan::class); }
}
