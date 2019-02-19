<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Diff;

class DiffUsers extends Base {

	public function run() {


	}

	/**
	 * @return string[]
	 */
	protected function getAttributesToCompare() {
		return [
			'user_pass',
			'user_email',
		];
	}
}