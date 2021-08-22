<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options;
use FernleafSystems\Wordpress\Services\Services;

class UserFormsController extends Integrations\Lib\Bots\Common\BaseBotDetectionController {

	protected function canRun() :bool {
		/** @var Options $loginOpts */
		$loginOpts = $this->getCon()->getModule_LoginGuard()->getOptions();
		return parent::canRun() && Services::Request()->isPost()
			   && !Services::WpUsers()->isUserLoggedIn()
			   && $loginOpts->isEnabledAntiBot();
	}

	protected function isEnabled() :bool {
		/** @var Integrations\Options $opts */
		$opts = $this->getOptions();
		return !empty( $opts->getUserFormProviders() );
	}

	/**
	 * @return Handlers\Base[]
	 */
	public function enumProviders() :array {
		return [
			new Handlers\Buddyboss(),
			new Handlers\Buddypress(),
			new Handlers\EasyDigitalDownloads(),
			new Handlers\LearnPress(),
			new Handlers\LifterLMS(),
			new Handlers\MemberPress(),
			new Handlers\PaidMemberSubscriptions(),
			new Handlers\ProfileBuilder(),
			new Handlers\UltimateMember(),
			new Handlers\WooCommerce(),
			new Handlers\WordPress(),
			new Handlers\WPMembers(),
		];
	}
}