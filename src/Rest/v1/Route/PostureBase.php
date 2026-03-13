<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;

abstract class PostureBase extends Base {

	public function getRoutePathPrefix() :string {
		return '/posture';
	}
}
