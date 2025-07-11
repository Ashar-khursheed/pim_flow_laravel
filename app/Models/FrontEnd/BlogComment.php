<?php
namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Blog;

class BlogComment extends Model
{
    protected $table = 'post_comments';

	protected $fillable = [
		'post_id',
		'parent_id',
		'comment',
		'created_by',
	];

	public function post()
{
    return $this->belongsTo(Blog::class, 'post_id'); // Explicitly tell Laravel the FK column
}
    public function replies()
    {
        return $this->hasMany(BlogComment::class, 'parent_id')->with('replies');
    }

    public function creator()
    {
        return $this->belongsTo(Customer::class, 'created_by'); // or your correct User model path
    }

}
