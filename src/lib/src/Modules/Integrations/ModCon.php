<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon {

	public const SLUG = 'integrations';

	/**
	 * @var Lib\MainWP\Controller
	 */
	private $mwp;

	/**
	 * @var Lib\Bots\UserForms\UserFormsController
	 */
	private $userFormsCon;

	/**
	 * @var Lib\Bots\Spam\SpamController
	 */
	private $spamFormsCon;

	public function getControllerMWP() :Lib\MainWP\Controller {
		return $this->mwp ?? $this->mwp = new Lib\MainWP\Controller();
	}

	public function getController_SpamForms() :Lib\Bots\Spam\SpamController {
		return $this->spamFormsCon ?? $this->spamFormsCon = new Lib\Bots\Spam\SpamController();
	}

	public function getController_UserForms() :Lib\Bots\UserForms\UserFormsController {
		return $this->userFormsCon ?? $this->userFormsCon = new Lib\Bots\UserForms\UserFormsController();
	}

	public function isModOptEnabled() :bool {
		return true;
	}
}