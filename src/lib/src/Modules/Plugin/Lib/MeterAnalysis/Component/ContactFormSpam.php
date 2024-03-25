<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ContactFormSpam extends Base {

	use Traits\OptConfigBased;

	public const MINIMUM_EDITION = 'starter';
	public const SLUG = 'contact_form_spam';

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

	protected function getOptConfigKey() :string {
		return 'form_spam_providers';
	}

	protected function isApplicable() :bool {
		return \count( self::con()->comps->forms_spam->getInstalled() ) > 0;
	}

	protected function testIfProtected() :bool {
		return \count( $this->getUnprotectedProvidersByName() ) === 0;
	}

	public function title() :string {
		return __( '3rd Party Contact Form SPAM', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "It appears that any contact forms you're using are protected against Bot SPAM.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return sprintf( __( "It appears that certain contact forms aren't protected against Bot SPAM: %s", 'wp-simple-firewall' ),
			\implode( ', ', $this->getUnprotectedProvidersByName() )
		);
	}
}