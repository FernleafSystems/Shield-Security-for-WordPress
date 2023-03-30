<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;
use FernleafSystems\Wordpress\Services\Services;

class UserFormsController extends Integrations\Lib\Bots\Common\BaseBotDetectionController {

	protected function canRun() :bool {
		return parent::canRun()
			   && Services::Request()->isPost()
			   && !Services::WpUsers()->isUserLoggedIn();
	}

	public function getSelectedProvidersOptKey() :string {
		return 'user_form_providers';
	}

	/**
	 * @inheritDoc
	 */
	public function enumProviders() :array {
		return [
			'buddyboss'               => Handlers\Buddyboss::class,
			'buddypress'              => Handlers\Buddypress::class,
			'easydigitaldownloads'    => Handlers\EasyDigitalDownloads::class,
			'learnpress'              => Handlers\LearnPress::class,
			'lifterlms'               => Handlers\LifterLMS::class,
			'memberpress'             => Handlers\MemberPress::class,
			'paidmembersubscriptions' => Handlers\PaidMemberSubscriptions::class,
			'profilebuilder'          => Handlers\ProfileBuilder::class,
			'restrictcontentpro'      => Handlers\RestrictContentPro::class,
			'ultimatemember'          => Handlers\UltimateMember::class,
			'woocommerce'             => Handlers\WooCommerce::class,
			'wordpress'               => Handlers\WordPress::class,
			'wpmembers'               => Handlers\WPMembers::class,
		];
	}
}