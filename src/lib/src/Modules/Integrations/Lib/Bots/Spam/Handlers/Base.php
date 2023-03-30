<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseHandler;

abstract class Base extends BaseHandler {

	public function getHandlerController() {
		return $this->mod()->getController_SpamForms();
	}

	protected function fireBotEvent() {
		$this->getCon()->fireEvent(
			sprintf( 'spam_form_%s', $this->isBot() ? 'fail' : 'pass' ),
			[
				'audit_params' => [
					'form_provider' => $this->getHandlerName(),
				]
			]
		);
	}

	protected function isBotBlockEnabled() :bool {
		return $this->isEnabled();
	}

	protected function getCommonSpamMessage() :string {
		return sprintf( __( "This appears to be spam as it failed %s AntiBot protection checks.", 'wp-simple-firewall' ),
			$this->getCon()->getHumanName() );
	}
}