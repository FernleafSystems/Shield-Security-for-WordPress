<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\UserForms\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders\BaseFormProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders\AntiBot;
use FernleafSystems\Wordpress\Services\Services;

abstract class Base extends BaseFormProvider {

	const SLUG = '';

	protected function canRun() :bool {
		return $this->getCon()->isPremiumActive() && $this->isEnabled() && $this->isProviderAvailable();
	}

	/**
	 * Only use AntiBot provider
	 * @return AntiBot[]
	 */
	protected function getProtectionProviders() :array {
		return array_filter(
			parent::getProtectionProviders(),
			function ( $provider ) {
				return $provider instanceof AntiBot;
			}
		);
	}

	public function isSpam() :bool {
		$isSpam = $this->isSpam_Bot();
		$this->getCon()->fireEvent(
			sprintf( 'user_form_%s', $isSpam ? 'fail' : 'pass' ),
			[
				'audit' => [
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

	protected function isEnabled() :bool {
		return in_array( $this->getProviderSlug(), $this->getOptions()->getOpt( 'user_form_providers', [] ) );
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