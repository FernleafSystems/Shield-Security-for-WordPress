<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\PairingTokenIssuer;

class ShieldCentralPairingToken extends BaseCmd {

	protected function cmdParts() :array {
		return [ 'shieldcentral', 'pairing-token' ];
	}

	protected function cmdShortDescription() :string {
		return 'Create a ShieldCentral pairing token.';
	}

	/**
	 * @throws \Exception
	 */
	public function runCmd() :void {
		$issued = ( new PairingTokenIssuer() )->issue();

		\WP_CLI\Utils\format_items(
			'json',
			[ $issued ],
			[ 'pairing_token', 'ttl_seconds', 'expires_at' ]
		);
		\WP_CLI::success( 'ShieldCentral pairing token created.' );
	}
}
