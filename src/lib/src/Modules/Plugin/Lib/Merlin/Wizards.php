<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin;

class Wizards {

	public const WIZARD_WELCOME = 'welcome';
	public const WIZARD_STEPS_WELCOME = [
		Steps\GuidedSetupWelcome::class,
		Steps\NewsletterSubscribe::class,
		Steps\License::class,
		Steps\SecurityAdmin::class,
		Steps\BotBlocking::class,
		Steps\LoginProtection::class,
		Steps\CommentSpam::class,
		Steps\SecurityBadge::class,
		Steps\ThankYou::class,
	];
}