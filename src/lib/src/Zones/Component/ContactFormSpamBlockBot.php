<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class ContactFormSpamBlockBot extends Base {

	public function title() :string {
		return __( 'Block Bot SPAM on Contact Forms', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Block the most common type of Contact Form SPAM.', 'wp-simple-firewall' );
	}

	public function description() :array {
		return [
		];
	}

	public function enabledStatus() :string {
		return \count( $this->getUnprotectedProvidersByName() ) > 0 ? EnumEnabledStatus::BAD : EnumEnabledStatus::GOOD;
	}

	/**
	 * @return string[]
	 */
	private function getUnprotectedProvidersByName() :array {
		return \array_filter( \array_map(
			function ( string $providerClass ) {
				$provider = new $providerClass();
				return $provider->isEnabled() ? null : $provider->getHandlerName();
			},
			self::con()->comps->forms_spam->getInstalled()
		) );
	}
}