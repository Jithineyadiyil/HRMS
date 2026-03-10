<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OffboardingTemplate extends Model {
    protected $fillable = ['title','category','description','sort_order','is_required','is_active'];
    protected $casts    = ['is_required'=>'boolean','is_active'=>'boolean'];
}
