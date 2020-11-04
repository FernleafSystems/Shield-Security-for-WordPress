<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib;

class ICWP_WPSF_FeatureHandler_Integrations extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @var Lib\MainWP\Controller
	 */
	private $mwp;

	public function getControllerMWP() :Lib\MainWP\Controller {
		if ( empty( $this->mwp ) ) {
			$this->mwp = ( new Lib\MainWP\Controller() )
				->setMod( $this );
		}
		return $this->mwp;
	}

	protected function getProcessorClassName() :string {
		return $this->getNamespace().'Processor';
	}

	protected function getNamespaceBase() :string {
		return 'Integrations';
	}
}