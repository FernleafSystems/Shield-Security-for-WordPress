<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Diff;

class DiffComments extends Base {

	/**
	 * @return string[]
	 */
	protected function getAttributesToCompare() {
		return [
			'post_id',
			'modified_at',
			'hash_content',
			'is_approved',
			'is_spam',
			'is_trash',
		];
	}
}