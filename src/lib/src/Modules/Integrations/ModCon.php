<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	/**
	 * @var Lib\MainWP\Controller
	 */
	private $mwp;

	/**
	 * @var Lib\Bots\UserForms\UserFormsController
	 */
	private $userFormsCon;

	public function getControllerMWP() :Lib\MainWP\Controller {
		if ( empty( $this->mwp ) ) {
			$this->mwp = ( new Lib\MainWP\Controller() )
				->setMod( $this );
		}
		return $this->mwp;
	}

	public function getController_UserForms() :Lib\Bots\UserForms\UserFormsController {
		if ( !$this->userFormsCon instanceof Lib\Bots\UserForms\UserFormsController ) {
			$this->userFormsCon = ( new Lib\Bots\UserForms\UserFormsController() )
				->setMod( $this );
		}
		return $this->userFormsCon;
	}

	public function isModOptEnabled() :bool {
		return true;
	}
}