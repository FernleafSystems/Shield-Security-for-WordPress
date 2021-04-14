<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

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

	protected function doPrePluginOptionsSave() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$userForms = $opts->getOpt( 'user_form_providers' );
		if ( !in_array( 'wordpress', $userForms ) ) {
			$userForms[] = 'wordpress';
			$opts->setOpt( 'user_form_providers', $userForms );
		}
	}
}