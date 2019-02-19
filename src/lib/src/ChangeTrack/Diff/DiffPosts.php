<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Diff;

class DiffPosts extends Base {

	public function run() {
	}

	/**
	 * @return string[]
	 */
	protected function getAttributesToCompare() {
		return [
			'slug',
			'modified_at',
			'hash_content',
			'hash_title',
		];
	}
}