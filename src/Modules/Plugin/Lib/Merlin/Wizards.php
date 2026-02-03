<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin;

class Wizards {

	public const WIZARD_WELCOME = 'welcome';
	public const WIZARD_STEPS_WELCOME = [
		Steps\GuidedSetupWelcome::class,
		Steps\License::class,
		Steps\NewsletterSubscribe::class,
		Steps\ApplySecurityProfile::class,
		Steps\SecurityAdmin::class,
		Steps\Integrations::class,
//		Steps\BotBlocking::class,
//		Steps\LoginProtection::class,
//		Steps\CommentSpam::class,
		Steps\SecurityBadge::class,
		Steps\ThankYou::class,
	];
}