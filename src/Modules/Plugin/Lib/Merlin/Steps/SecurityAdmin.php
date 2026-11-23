<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\ToggleSecAdminStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;

class SecurityAdmin extends Base {

	public const SLUG = 'security_admin';

	public function processStepFormSubmit( array $form ) :Response {
		$con = self::con();
		$resp = parent::processStepFormSubmit( $form );

		$pin = $form[ 'SecAdminPIN' ] ?? '';
		if ( empty( $pin ) ) {
			$resp->error = __( 'Please provide a Security PIN, or proceed to the next step.', 'wp-simple-firewall' );
		}
		elseif ( $pin !== ( $form[ 'SecAdminPINConfirm' ] ?? '' ) ) {
			$resp->error = __( 'The Security PINs provided do not match.', 'wp-simple-firewall' );
		}
		elseif ( !$con->isPluginAdmin() ) {
			$resp->error = __( "You don't have permission to update the Security PIN.", 'wp-simple-firewall' );
		}
		else {
			$con->opts
				->optSet( 'enable_'.$con->cfg->configuration->modFromOpt( 'admin_access_key' ), 'Y' )
				->optSet( 'admin_access_key', wp_hash_password( $pin ) )
				->store();

			( new ToggleSecAdminStatus() )->turnOn();

			$resp->success = true;
			$resp->message = __( 'Security Admin is now active', 'wp-simple-firewall' );
			// The page reloads so that, from here on, the PIN is asked for and accepted like everywhere else.
			$resp->addData( 'page_reload', true );
		}
		return $resp;
	}

	public function getName() :string {
		return __( 'Security Admin', 'wp-simple-firewall' );
	}

	protected function getStepRenderData() :array {
		$pluginName = self::con()->labels->Name;
		return [
			'strings' => [
				/* translators: %s: plugin name */
				'step_title'                 => sprintf( __( 'Protect Your %s Plugin From Tampering', 'wp-simple-firewall' ), $pluginName ),
				'security_admin_text_1'      => __( 'Security Admin provides an additional authentication layer that ensures only vetted admins may make changes to your site security.', 'wp-simple-firewall' ),
				'security_admin_text_2'      => __( 'It also helps lockdown critical WordPress settings.', 'wp-simple-firewall' ),
				/* translators: %s: plugin name */
				'security_admin_text_3'      => sprintf( __( 'This feature relies on a secret PIN (which you create below), that you will use to verify your identity with the %s plugin.', 'wp-simple-firewall' ), $pluginName ),
				'pin_heading'                => __( 'Choose Your Security Admin PIN', 'wp-simple-firewall' ),
				'pin_label'                  => __( 'PIN', 'wp-simple-firewall' ),
				'pin_placeholder'            => __( 'Create a new secret PIN', 'wp-simple-firewall' ),
				'pin_help'                   => __( "Don't forget this PIN", 'wp-simple-firewall' ),
				'pin_confirm'                => __( 'Confirm PIN', 'wp-simple-firewall' ),
				'security_admin_text_button' => __( 'Turn On Security Admin', 'wp-simple-firewall' ),
			],
		];
	}

	public function skipStep() :bool {
		return self::con()->comps->sec_admin->isEnabledSecAdmin();
	}
}