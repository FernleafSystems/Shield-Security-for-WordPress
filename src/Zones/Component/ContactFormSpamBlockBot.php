<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class ContactFormSpamBlockBot extends Base {

	public function title() :string {
		return sprintf( '%s - %s', __( 'Contact Forms Integration', 'wp-simple-firewall' ), __( 'Block Bot SPAM', 'wp-simple-firewall' ) );
	}

	public function subtitle() :string {
		return __( 'Block SPAM posted to Contact Forms by automated Bots.', 'wp-simple-firewall' );
	}

	protected function tooltip() :string {
		return __( 'Switch on/off SPAM protection for your favourite contact forms plugin', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();

		$unprotected = $this->getUnprotectedProvidersByName();

		if ( empty( $unprotected ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
			$status[ 'exp' ][] = __( "If you use a WP forms plugin for which we don't have an integration, please reach out to us.", 'wp-simple-firewall' );
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = sprintf( __( "It looks like you're using a WP forms plugin for which we have an integration: %s.", 'wp-simple-firewall' ), implode( ', ', $unprotected ) );
		}

		return $status;
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