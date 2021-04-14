<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseBotDetectionController;

class UserFormsController extends BaseBotDetectionController {

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
			new Handlers\WPMembers(),
		];
	}
}