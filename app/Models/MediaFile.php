<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'media_files';

    protected $fillable = [
        'user_id',
        'name',
        'alt',
        'folder_id',
        'mime_type',
        'size',
        'url',
        'options',
        'visibility',
    ];

    // Relationship: Media belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
