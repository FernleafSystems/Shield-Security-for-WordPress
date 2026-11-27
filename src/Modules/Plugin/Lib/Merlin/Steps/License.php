<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use \FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;

class License extends Base {

	public const SLUG = 'license';

	public function processStepFormSubmit( array $form ) :Response {
		$resp = parent::processStepFormSubmit( $form );
		if ( $resp->success = self::con()->comps->license->verify( true )->hasValidWorkingLicense() ) {
			$resp->message = __( 'License found and installed successfully', 'wp-simple-firewall' );
		}
		else {
			/* translators: %s: plugin name */
			$resp->error = sprintf( __( "There doesn't appear to be an active %s license available for this site.", 'wp-simple-firewall' ), self::con()->labels->Name );
		}

		$resp->addData( 'page_reload', $resp->success );

		return $resp;
	}

	public function getName() :string {
		return __( 'Activate Pro License', 'wp-simple-firewall' );
	}

	protected function getStepRenderData() :array {
		$pluginName = self::con()->labels->Name;
		return [
			'strings' => [
				/* translators: %s: plugin name */
				'step_title'           => sprintf( __( "Activate Your %s License", 'wp-simple-firewall' ), $pluginName ),
				/* translators: %s: plugin name */
				'license_check_text'   => sprintf( __( "This step is only for sites with a paid %s plan. If you've bought one for this site, check for your licence below and it's activated straight away.", 'wp-simple-firewall' ), $pluginName ),
				/* translators: %s: a "skip to the next step" button */
				'license_skip_text'    => __( "If this doesn't apply to you, %s.", 'wp-simple-firewall' ),
				'license_skip_button'  => __( 'skip to the next step', 'wp-simple-firewall' ),
				'check_license_button' => __( 'Check for my licence', 'wp-simple-firewall' ),
			],
		];
	}

	public function skipStep() :bool {
		return self::con()->isPremiumActive();
	}
}
