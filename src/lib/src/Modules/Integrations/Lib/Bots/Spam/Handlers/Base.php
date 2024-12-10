<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\AntiBot\CoolDownHandler;

abstract class Base extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseHandler {

	public function getHandlerController() {
		return self::con()->comps->forms_spam;
	}

	protected function fireBotEvent() {
		self::con()->comps->events->fireEvent(
			sprintf( 'spam_form_%s', $this->isBot() ? 'fail' : 'pass' ),
			[
				'audit_params' => [
					'form_provider' => $this->getHandlerName(),
				]
			]
		);
	}

	protected function getCommonSpamMessage() :string {
		return sprintf( __( "This appears to be spam as it failed %s AntiBot protection checks.", 'wp-simple-firewall' ),
			self::con()->labels->Name );
	}

	protected function getCooldownContext() :string {
		return CoolDownHandler::CONTEXT_SPAM;
	}
}