<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class Base extends BaseHandler {

	public function isSpam() :bool {
		$isSpam = $this->isBot();
		$this->getCon()->fireEvent(
			sprintf( 'spam_form_%s', $isSpam ? 'fail' : 'pass' ),
			[
				'audit' => [
					'form_provider' => $this->getProviderName(),
				]
			]
		);
		return $isSpam;
	}

	protected function isSpam_Human() :bool {
		return false;
	}

	public function isEnabled() :bool {
		return in_array( $this->getHandlerSlug(), $this->getOptions()->getOpt( 'form_spam_providers', [] ) );
	}
}