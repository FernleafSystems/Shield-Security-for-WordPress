<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Diff;

class DiffThemes extends Base {

	/**
	 * @return string[]
	 */
	protected function getAttributesToCompare() {
		return [
			'version',
			'is_active',
			'has_update',
			'is_child',
			'is_parent',
		];
	}
}