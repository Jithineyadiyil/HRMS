<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PayrollComponent extends Model {
    protected $fillable = ['name','code','type','calculation','value','is_taxable','is_active','description'];
}
