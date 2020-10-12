<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
	protected $appends = ['amount_formatted','post_id','post_unique_id'];

	protected $hidden = ['id','unique_id'];

	public function getAmountFormattedAttribute() {

		return formatted_amount($this->amount);
	}

	public function getPostIdAttribute() {

		return $this->id;
	}

	public function getPostUniqueIdAttribute() {

		return $this->unique_id;
	}
}
