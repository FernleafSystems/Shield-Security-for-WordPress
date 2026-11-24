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
				// What the PIN protects on any site, whoever its admins are; what it's like to live with; and the way back.
				/* translators: %s: plugin name */
				'security_admin_text_1'      => sprintf( __( "A Security Admin PIN locks your security settings. If someone gets into an admin account, yours included, they can't change %s's settings without the PIN.", 'wp-simple-firewall' ), $pluginName ),
				'security_admin_text_2'      => __( 'It can lock key WordPress settings too.', 'wp-simple-firewall' ),
				/* translators: %1$s: plugin name, %2$s: number of minutes */
				'security_admin_text_3'      => sprintf( __( "You'll be asked for the PIN when you change %1\$s's settings. It then stays unlocked for %2\$s minutes.", 'wp-simple-firewall' ), $pluginName, (int)self::con()->opts->optGet( 'admin_access_timeout' ) ),
				'pin_heading'                => __( 'Choose your Security Admin PIN', 'wp-simple-firewall' ),
				'pin_label'                  => __( 'PIN', 'wp-simple-firewall' ),
				'pin_placeholder'            => __( 'Create a new secret PIN', 'wp-simple-firewall' ),
				'pin_help'                   => __( 'If you forget the PIN, a link emailed to your report address turns Security Admin off, so you can set a new one.', 'wp-simple-firewall' ),
				'pin_confirm'                => __( 'Confirm PIN', 'wp-simple-firewall' ),
				'security_admin_text_button' => __( 'Turn on Security Admin', 'wp-simple-firewall' ),
			],
		];
	}

	public function skipStep() :bool {
		return self::con()->comps->sec_admin->isEnabledSecAdmin();
	}
}