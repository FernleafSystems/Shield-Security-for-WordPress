<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Options extends Base\ShieldOptions {

	public function getFrequencyAlert() :string {
		return $this->getFrequency( 'alert' );
	}

	public function getFrequencyInfo() :string {
		return $this->getFrequency( 'info' );
	}

	private function getFrequency( string $type ) :string {
		$key = 'frequency_'.$type;
		$sDefault = $this->getOptDefault( $key );
		return ( $this->isPremium() || in_array( $this->getOpt( $key ), [ 'disabled', $sDefault ] ) )
			? $this->getOpt( $key )
			: $sDefault;
	}
}