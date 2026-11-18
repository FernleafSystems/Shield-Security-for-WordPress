<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;

class Integrations extends Base {

	public const SLUG = 'integrations';

	public function skipStep() :bool {
		return !self::con()->isPremiumActive() || \count( $this->integrationsWaiting() ) === 0;
	}

	/**
	 * The integrations whose plugins are installed and that aren't switched on yet.
	 * @return array<string,array{slug:string,state:string,name:string,has_cap:bool}>
	 */
	private function integrationsWaiting() :array {
		return \array_filter(
			self::con()->comps->integrations->buildIntegrationsStates(),
			static fn( array $int ) :bool => $int[ 'state' ] === 'available'
		);
	}

	public function getName() :string {
		return __( 'Integrations', 'wp-simple-firewall' );
	}

	/**
	 * Each integration ticked on the form arrives as its slug set to 'Y'. One left unticked isn't sent, so it
	 * stays off; submitting with none ticked is a way of saying no to all of them, and still completes the step.
	 */
	public function processStepFormSubmit( array $form ) :Response {
		$con = self::con();
		$switchedOn = $con->comps->integrations->switchOn( \array_map( '\strval', \array_keys( $form, 'Y', true ) ) );
		if ( !empty( $switchedOn ) ) {
			$con->opts->store();
		}

		$resp = parent::processStepFormSubmit( $form );
		$resp->success = true;
		$resp->message = empty( $switchedOn )
			? __( 'No integrations were switched on.', 'wp-simple-firewall' )
			/* translators: %s: a list of the names of 3rd party plugins */
			: sprintf( __( 'Integrations switched on: %s', 'wp-simple-firewall' ), \implode( ', ', $switchedOn ) );
		return $resp;
	}

	protected function getStepRenderData() :array {
		return [
			'strings' => [
				'step_title' => __( '3rd Party Integrations', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'integrations' => $this->integrationsWaiting(),
			],
		];
	}
}
