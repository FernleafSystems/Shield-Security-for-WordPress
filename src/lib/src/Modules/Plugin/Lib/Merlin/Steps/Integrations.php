<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;

class Integrations extends Base {

	public const SLUG = 'integrations';

	public function skipStep() :bool {
		return !self::con()->isPremiumActive()
			   || \count(
					  \array_filter(
						  self::con()->comps->integrations->buildIntegrationsStates(),
						  fn( array $int ) => $int[ 'state' ] === 'available'
					  )
				  ) === 0;
	}

	public function getName() :string {
		return __( 'Integrations', 'wp-simple-firewall' );
	}

	public function processStepFormSubmit( array $form ) :Response {
		$resp = parent::processStepFormSubmit( $form );
		$con = self::con();

		$level = \strtolower( $form[ 'security_profile' ] ?? '' );
		if ( empty( $level ) ) {
			$resp->success = true;
			$resp->message = __( 'No profile was applied' );
		}
		else {
			try {
				$con->comps->security_profiles->applyLevel( $level );
				$resp->success = true;
				$resp->message = sprintf( __( "Profile '%s' was applied.", 'wp-simple-firewall' ), $con->comps->security_profiles->meta( $level )[ 'title' ] );
			}
			catch ( \Exception $e ) {
				error_log( $e->getMessage() );
				$resp->success = false;
				$resp->message = $resp->error = __( 'An unsupported profile was selected' );
			}
		}
		return $resp;
	}

	protected function getStepRenderData() :array {
		return [
			'strings' => [
				'step_title' => __( '3rd Party Integrations', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'integrations' => \array_filter(
					self::con()->comps->integrations->buildIntegrationsStates(),
					fn( array $int ) => $int[ 'state' ] === 'available'
				),
			],
		];
	}
}