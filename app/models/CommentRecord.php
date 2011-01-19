<?php

namespace app\models;

use app\models\Comment;
use row\utils\DateTime;
use app\models\VisitableRecord;

class CommentRecord extends Comment implements VisitableRecord {

	public function _init() {
		$this->_created_on = new DateTime($this->created_on);
	}

	public function url( $more = '' ) {
		return '/blog/view/' . $this->post_id . '#comment-' . $this->comment_id;
	}

}

