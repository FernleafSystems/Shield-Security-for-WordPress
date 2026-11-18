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
		$pluginName = self::con()->labels->Name;
		return [
			'strings' => [
				'step_title'         => __( '3rd Party Integrations', 'wp-simple-firewall' ),
				/* translators: %s: plugin name */
				'integrations_text'  => sprintf( __( '%s provides built-in integrations to protect the forms of 3rd party plugins against SPAM Bots.', 'wp-simple-firewall' ), $pluginName ),
				/* translators: %s: silentCAPTCHA, the name of the anti-bot technology */
				'custom_built'       => __( 'Each integration is custom-built and implements our %s anti-bot technology.', 'wp-simple-firewall' ),
				'form_types_intro'   => __( 'The types of forms that can be protected include:', 'wp-simple-firewall' ),
				'form_types'         => [
					__( 'Contact Forms', 'wp-simple-firewall' ),
					__( 'Login Forms', 'wp-simple-firewall' ),
					__( 'Registration Forms', 'wp-simple-firewall' ),
					__( 'Password Reset Forms', 'wp-simple-firewall' ),
				],
				/* translators: %s: plugin name */
				'integrations_text2' => sprintf( __( "Protection for WordPress forms is included as-standard, and you'll have access to some, or all, 3rd party integrations, depending on your %s plan.", 'wp-simple-firewall' ), $pluginName ),
				'select_heading'     => __( 'Select 3rd Party Integrations', 'wp-simple-firewall' ),
				/* translators: %s: name of a 3rd party plugin */
				'switch_on'          => __( 'Switch-on integration for %s', 'wp-simple-firewall' ),
				/* translators: %s: silentCAPTCHA, the name of the anti-bot technology */
				'help_available'     => __( '%s will detect and block bot requests to these forms, without challenging your legitimate visitors.', 'wp-simple-firewall' ),
				'help_unavailable'   => __( 'Not available on your current plan.', 'wp-simple-firewall' ),
				'enable_button'      => __( 'Enable Selected Integrations', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'integrations' => $this->integrationsWaiting(),
			],
		];
	}
}