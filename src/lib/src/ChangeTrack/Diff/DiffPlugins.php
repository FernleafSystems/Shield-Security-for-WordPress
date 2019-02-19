<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Diff;

class DiffPlugins extends Base {

	public function run() {
	}

	/**
	 * @return string[]
	 */
	protected function getAttributesToCompare() {
		return [
			'version',
			'is_active',
			'has_update',
		];
	}
}