<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\InventoryItem;
use App\Models\User;
use App\Models\Room;
use App\Models\Note;

class BorrowRequest2 extends Model
{
    use HasFactory;

    protected $table = 'borrow_request2s';

    protected $fillable = [
        'borrow_details',
        'purpose',
        'from',
        'to',
        'borrower',
        'status',
        'worker',
        'requested_by',
        'rejection_details',
        'destination_room',
        'date_processed',
        'borrower_file',
        'borrower_note',
        'department'
    ];

    public function items()
    {
        return $this->hasMany(InventoryItem::class, 'borrow_request_id')->withTrashed();
    }

    public function worker()
    {
        return $this->belongsTo(User::class, 'worker')->withTrashed();
    }

    public function worker2()
    {
        return $this->belongsTo(User::class, 'worker')->withTrashed();
    }

    public function requestor()
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function destination()
    {
        return $this->belongsTo(Room::class, 'destination_room')->withTrashed();
    }

    public function notes()
    {
        return $this->hasMany(Note::class, 'borrow_id');
    }
}
