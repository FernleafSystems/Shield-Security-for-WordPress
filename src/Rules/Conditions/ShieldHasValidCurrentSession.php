<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class ShieldHasValidCurrentSession extends Base {

	public function getName() :string {
		/* translators: %s: plugin name */
		return sprintf( __( 'Has Valid %s User Sessions', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	public function getDescription() :string {
		/* translators: %s: plugin name */
		return sprintf( __( 'The current request has a valid %s user session', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	protected function execConditionCheck() :bool {
		if ( !isset( $this->req->session ) ) {
			$this->req->session = self::con()->comps->session->current();
		}
		return $this->req->session->valid;
	}
}