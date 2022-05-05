<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\ModCon;

abstract class Base extends BaseHandler {

	public function getHandlerController() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->getController_SpamForms();
	}

	/**
	 * @deprecated 15.0
	 */
	public function isSpam() :bool {
		return parent::isBot();
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