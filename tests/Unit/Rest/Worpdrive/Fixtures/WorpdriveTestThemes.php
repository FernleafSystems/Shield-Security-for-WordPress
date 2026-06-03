<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures;

use FernleafSystems\Wordpress\Services\Core\Themes;

class WorpdriveTestThemes extends Themes {

	public function getCurrent() {
		return new \WP_Theme( 'zeta-theme', 'Zeta Theme', '2.0.0' );
	}

	public function getThemes() :array {
		return [
			new \WP_Theme( 'zeta-theme', 'Zeta Theme', '2.0.0' ),
			new \WP_Theme( 'alpha-theme', 'Alpha Theme', '1.0.0' ),
		];
	}
}
