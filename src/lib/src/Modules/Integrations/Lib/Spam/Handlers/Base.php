<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Spam\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class Base extends ExecOnceModConsumer {

	const SLUG = '';

	protected function canRun() :bool {
		return $this->getCon()->isPremiumActive() && $this->isEnabled() && $this->isProviderAvailable();
	}

	public function isSpam() :bool {
		$isSpam = $this->isSpam_Bot();
		$this->getCon()->fireEvent(
			sprintf( 'spam_form_%s', $isSpam ? 'fail' : 'pass' ),
			[
				'audit_params' => [
					'form_provider' => $this->getProviderName(),
				]
			]
		);
		return $isSpam;
	}

	protected function isSpam_Bot() :bool {
		return $this->getCon()
					->getModule_IPs()
					->getBotSignalsController()
					->isBot( Services::IP()->getRequestIp() );
	}

	protected function isSpam_Human() :bool {
		return false;
	}

	protected function isEnabled() :bool {
		return in_array( $this->getProviderSlug(), $this->getOptions()->getOpt( 'form_spam_providers', [] ) );
	}

	protected function isProviderAvailable() :bool {
		return false;
	}

	protected function getProviderName() :string {
		return '';
	}

	protected function getProviderSlug() :string {
		try {
			$slug = strtolower( ( new \ReflectionClass( $this ) )->getShortName() );
		}
		catch ( \Exception $e ) {
			$slug = '';
		}
		return $slug;
	}
}