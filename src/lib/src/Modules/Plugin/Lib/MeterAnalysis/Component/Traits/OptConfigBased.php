<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\Traits;

trait OptConfigBased {

	abstract protected function getOptConfigKey() :string;

	protected function cfgItem() :string {
		return $this->getOptConfigKey();
	}

	protected function getOptLink( string $for ) :string {
		return self::con()->plugin_urls->modCfgOption( $for );
	}

	protected function hrefFull() :string {
		return $this->getOptLink( $this->cfgItem() );
	}

	protected function isOptConfigBased() :bool {
		return true;
	}
}