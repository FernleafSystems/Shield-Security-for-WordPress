<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

// Upgrade bridge for the moved silentCAPTCHA signal component.
if ( !\class_exists( __NAMESPACE__.'\\BotEventListener', false ) ) {
	\class_alias(
		\FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\Signals\BotEventListener::class,
		__NAMESPACE__.'\\BotEventListener'
	);
}
