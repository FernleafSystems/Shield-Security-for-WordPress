<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\AssetSnapshots;

use FernleafSystems\Wordpress\Services\Core\General;

class SnapshotWpGeneral extends General {

	private array $transients = [];

	public function canUseTransients() :bool {
		return true;
	}

	public function getTransient( $key ) {
		return $this->transients[ $key ] ?? false;
	}

	public function setTransient( $key, $value, $expiration = 0 ) :bool {
		unset( $expiration );
		$this->transients[ $key ] = $value;
		return true;
	}
}
