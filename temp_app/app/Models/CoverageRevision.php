<?php
namespace App\Models;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CoverageRevision extends Model {
    use LogsActivity;
    protected $fillable=['coverage_id','round','notes','due_on','completed_at','created_by'];
    protected function casts(): array { return ['due_on'=>'date','completed_at'=>'datetime']; }
    public function coverage(): BelongsTo { return $this->belongsTo(Coverage::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
}
