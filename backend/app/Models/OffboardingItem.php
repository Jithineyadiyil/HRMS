<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OffboardingItem extends Model {
    protected $fillable = ['separation_id','template_id','title','category','is_required','status','completed_by','completed_at','notes','sort_order'];
    protected $casts    = ['completed_at'=>'datetime','is_required'=>'boolean'];
    public function separation()  { return $this->belongsTo(Separation::class); }
    public function completedBy() { return $this->belongsTo(User::class, 'completed_by'); }
}
