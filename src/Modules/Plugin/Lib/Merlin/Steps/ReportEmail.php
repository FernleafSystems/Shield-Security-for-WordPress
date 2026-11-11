<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ReportEmailHold;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;
use FernleafSystems\Wordpress\Services\Services;

class ReportEmail extends Base {

	public const SLUG = 'report_email';

	public function processStepFormSubmit( array $form ) :Response {
		// Choosing the site admin email is still a choice, so it's saved like any other address.
		$email = ( $form[ 'use_site_admin_email' ] ?? '' ) === 'Y'
			? Services::WpGeneral()->getSiteAdminEmail()
			: \trim( (string)( $form[ 'ReportEmail' ] ?? '' ) );
		if ( !Services::Data()->validEmail( $email ) ) {
			throw new \Exception( __( 'Please provide a valid email address, or skip this step.', 'wp-simple-firewall' ) );
		}

		self::con()->opts->optSet( 'block_send_email_address', $email )->store();

		$resp = parent::processStepFormSubmit( $form );
		$resp->success = true;
		$resp->message = __( 'Reports and alerts will be sent to that address', 'wp-simple-firewall' );
		return $resp;
	}

	public function getName() :string {
		return __( 'Report Email', 'wp-simple-firewall' );
	}

	protected function getStepRenderData() :array {
		$adminEmail = Services::WpGeneral()->getSiteAdminEmail();
		$hold = new ReportEmailHold();
		return [
			'strings' => [
				'step_title'          => __( 'Where Should Reports And Alerts Go?', 'wp-simple-firewall' ),
				'report_email_text'   => __( 'Shield sends security reports and instant alerts by email. Use an inbox someone reads; a shared address works well if several people look after the site.', 'wp-simple-firewall' ),
				'hold_notice'         => $hold->isActive() ? $hold->explanation() : '',
				'report_email_label'  => __( 'Report email', 'wp-simple-firewall' ),
				'report_email_button' => __( 'Save Report Email', 'wp-simple-firewall' ),
				/* translators: %s: the site admin email address */
				'use_admin_button'    => sprintf( __( 'Use the site admin email (%s)', 'wp-simple-firewall' ), $adminEmail ),
			],
			'vars'    => [
				'report_email' => (string)self::con()->opts->optGet( 'block_send_email_address' ),
				'admin_email'  => $adminEmail,
			],
		];
	}

	public function skipStep() :bool {
		return self::con()->comps->opts_lookup->getChosenReportEmail() !== '';
	}
}
