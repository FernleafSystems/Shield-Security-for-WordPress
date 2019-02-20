<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Diff;

class DiffUsers extends Base {

	/**
	 * @return string[]
	 */
	protected function getAttributesToCompare() {
		return [
			'user_pass',
			'user_email',
			'is_admin',
		];
	}
}