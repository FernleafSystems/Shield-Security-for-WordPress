<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Find;

class UserFormsController extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseBotDetectionController {

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
			Find::ARMEMBER_LITE             => Handlers\ArmemberLite::class,
			Find::BUDDYBOSS                 => Handlers\Buddyboss::class,
			Find::BUDDYPRESS                => Handlers\Buddypress::class,
			Find::CLASSIFIED_LISTING        => Handlers\ClassifiedListing::class,
			Find::EASY_DIGITAL_DOWNLOADS    => Handlers\EasyDigitalDownloads::class,
			Find::LEARNPRESS                => Handlers\LearnPress::class,
			Find::LIFTERLMS                 => Handlers\LifterLMS::class,
			Find::MEMBERPRESS               => Handlers\MemberPress::class,
			Find::PAID_MEMBER_SUBSCRIPTIONS => Handlers\PaidMemberSubscriptions::class,
			Find::PROFILE_BUILDER           => Handlers\ProfileBuilder::class,
			Find::RESTRICT_CONTENT_PRO      => Handlers\RestrictContentPro::class,
			Find::SIMPLE_MEMBERSHIP         => Handlers\SimpleMembership::class,
			Find::ULTIMATE_MEMBER           => Handlers\UltimateMember::class,
			Find::WOOCOMMERCE               => Handlers\WooCommerce::class,
			'wordpress'                     => Handlers\WordPress::class,
			Find::WP_MEMBERS                => Handlers\WPMembers::class,
		];
	}
}