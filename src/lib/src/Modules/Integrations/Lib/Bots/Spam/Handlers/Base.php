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

	public function isSpam() :bool {
		$isSpam = $this->isBot();
		$this->getCon()->fireEvent(
			sprintf( 'spam_form_%s', $isSpam ? 'fail' : 'pass' ),
			[
				'audit_params' => [
					'form_provider' => $this->getHandlerName(),
				]
			]
		);
		return $isSpam;
	}

	protected function getCommonSpamMessage() :string {
		return sprintf( __( "This appears to be spam as it failed %s AntiBot protection checks.", 'wp-simple-firewall' ),
			$this->getCon()->getHumanName() );
	}
}