<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\Traits;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Utilities\OptUtils;

trait OptConfigBased {

	abstract protected function getOptConfigKey() :string;

	protected function getOptConfigKeyRelevant() :string {
		$mod = OptUtils::ModFromOpt( $this->getOptConfigKey() );
		return $mod->isModOptEnabled() ? $this->getOptConfigKey() : $mod->getEnableModOptKey();
	}

	protected function getOptLink( string $for, bool $offCanvasJS = false ) :string {
		return $offCanvasJS ?
			$this->getCon()->plugin_urls->offCanvasConfigRender( $for )
			: $this->getCon()->plugin_urls->modCfgOption( $for );
	}

	protected function hrefFull() :string {
		return $this->getOptLink( $this->getOptConfigKeyRelevant() );
	}

	protected function hrefOffCanvas() :string {
		return $this->getOptLink( $this->getOptConfigKeyRelevant(), true );
	}

	protected function isOptConfigBased() :bool {
		return true;
	}
}