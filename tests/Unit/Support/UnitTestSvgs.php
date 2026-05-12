<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class UnitTestSvgs {

	public function iconClass( string $icon ) :string {
		return 'bi bi-'.$icon;
	}
}
