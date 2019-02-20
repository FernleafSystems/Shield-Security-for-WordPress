<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Diff;

class DiffPages extends DiffPosts {

	/**
	 * @return string[]
	 */
	protected function getAttributesToCompare() {
		return array_merge(
			parent::getAttributesToCompare(),
			[ 'is_blog', 'is_front' ]
		);
	}
}