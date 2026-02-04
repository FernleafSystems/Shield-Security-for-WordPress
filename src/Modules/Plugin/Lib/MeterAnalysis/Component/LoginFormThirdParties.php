<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers\WordPress;

class LoginFormThirdParties extends Base {

	use Traits\OptConfigBased;

	public const MINIMUM_EDITION = 'starter';
	public const SLUG = 'login_forms_third_parties';

	protected function getOptConfigKey() :string {
		return 'user_form_providers';
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
			self::con()->comps->forms_users->getInstalled()
		) );
	}

	protected function isApplicable() :bool {
		$installed = self::con()->comps->forms_users->getInstalled();
		unset( $installed[ WordPress::Slug() ] );
		return \count( $installed ) > 0;
	}

	protected function testIfProtected() :bool {
		return \count( $this->getUnprotectedProvidersByName() ) === 0;
	}

	public function title() :string {
		return __( '3rd Party Login Forms', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "It appears that any 3rd party login forms you're using are protected against Bots.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return sprintf( __( "It appears that certain 3rd-party login forms aren't protected against Bots: %s", 'wp-simple-firewall' ),
			\implode( ', ', $this->getUnprotectedProvidersByName() )
		);
	}
}