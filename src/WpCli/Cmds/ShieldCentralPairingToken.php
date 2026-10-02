<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\CentralDisabledException;

class ShieldCentralPairingToken extends BaseCmd {

	protected function canRun() :bool {
		return self::con()->comps->central->isEnabled() && parent::canRun();
	}

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
		try {
			$issued = self::con()->comps->central->issuePairingToken();
		}
		catch ( CentralDisabledException $e ) {
			\WP_CLI::error( $e->getMessage() );
			return;
		}

		\WP_CLI\Utils\format_items(
			'json',
			[ $issued ],
			[ 'pairing_token', 'ttl_seconds', 'expires_at' ]
		);
		\WP_CLI::success( 'ShieldCentral pairing token created.' );
	}
}
