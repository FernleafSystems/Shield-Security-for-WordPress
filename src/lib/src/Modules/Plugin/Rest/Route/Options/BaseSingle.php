<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Route\Options;

abstract class BaseSingle extends Base {

	public function getRoutePath() :string {
		return '/(?P<key>[0-9a-z_]+)';
	}

	protected function getRouteArgsDefaults() :array {
		return \array_merge(
			parent::getRouteArgsDefaults(),
			[
				'key' => $this->getRouteArgSchema( 'key' ),
			]
		);
	}
}