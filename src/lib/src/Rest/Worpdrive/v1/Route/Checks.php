<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route;

class Checks extends BaseWorpdrive {

	public function getRoutePath() :string {
		return '/checks';
	}
}