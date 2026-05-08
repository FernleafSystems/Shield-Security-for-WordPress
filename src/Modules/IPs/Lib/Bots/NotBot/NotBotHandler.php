<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

// Deprecated 22.0: Upgrade bridge for the moved silentCAPTCHA component namespace.
if ( !\class_exists( __NAMESPACE__.'\\NotBotHandler', false ) ) {
	\class_alias(
		\FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\Signals\NotBotHandler::class,
		__NAMESPACE__.'\\NotBotHandler'
	);
}
