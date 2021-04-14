<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseBotDetectionController;
use FernleafSystems\Wordpress\Services\Services;

class UserFormsController extends BaseBotDetectionController {

	protected function canRun() :bool {
		return parent::canRun() && Services::Request()->isPost() && !Services::WpUsers()->isUserLoggedIn();
	}

	protected function isEnabled() :bool {
		return !empty( $this->getOptions()->getOpt( 'user_form_providers' ) );
	}

	/**
	 * @return Handlers\Base[]
	 */
	public function enumProviders() :array {
		return [
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