<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\SpamHandlers;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

abstract class Base {

	use ModConsumer;
	use ExecOnce;

	const SLUG = '';

	protected function canRun() :bool {
		return $this->getCon()->isPremiumActive() && $this->isEnabled() && $this->isPluginInstalled();
	}

	protected function isSpam() :bool {
		$isSpam = $this->isSpamBot();
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

	protected function isSpamBot() :bool {
		return !$this->getCon()
					 ->getModule_IPs()
					 ->getBotSignalsController()
					 ->verifyNotBot();
	}

	protected function isEnabled() :bool {
		return in_array( $this->getProviderSlug(), $this->getOptions()->getOpt( 'form_spam_providers', [] ) );
	}

	protected function isPluginInstalled() :bool {
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