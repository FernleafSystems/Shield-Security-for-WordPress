<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class ShieldHasValidCurrentSession extends Base {

	public function getName() :string {
		return __( 'Has Valid Shield User Sessions', 'wp-simple-firewall' );
	}

	public function getDescription() :string {
		return __( 'The current request has a valid Shield user session', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		if ( !isset( $this->req->session ) ) {
			$con = self::con();
			$this->req->session = ( $con->comps->session !== null ) ?
				$con->comps->session->current() : $con->getModule_Plugin()->getSessionCon()->current();
		}
		return $this->req->session->valid;
	}
}