<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Diff;

class DiffPlugins extends Base {

	/**
	 * @return string[]
	 */
	protected function getAttributesToCompare() {
		return [
			'name',
			'version',
			'is_active',
			'has_update',
		];
	}
}