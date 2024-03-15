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
		return self::con()->comps !== null ? self::con()->comps->mainwp :
			( $this->mwp ?? $this->mwp = new Lib\MainWP\Controller() );
	}

	public function getController_SpamForms() :Lib\Bots\Spam\SpamController {
		return self::con()->comps !== null ? self::con()->comps->forms_spam :
			( $this->spamFormsCon ?? $this->spamFormsCon = new Lib\Bots\Spam\SpamController() );
	}

	public function getController_UserForms() :Lib\Bots\UserForms\UserFormsController {
		return self::con()->comps !== null ? self::con()->comps->forms_users :
			( $this->userFormsCon ?? $this->userFormsCon = new Lib\Bots\UserForms\UserFormsController() );
	}

	public function isModOptEnabled() :bool {
		return true;
	}
}