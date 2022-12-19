<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa\Components;

use FernleafSystems\Utilities\Data\CaptureOutput;

class WpLoginReplicaFooter extends Base {

	public const SLUG = 'render_shield_wploginreplica_footer';
	public const TEMPLATE = '/components/wplogin_replica/login_footer.twig';

	protected function getRenderData() :array {
		/**
		 * Fires in the login page footer.
		 *
		 * @since 3.1.0
		 */
		$actionLoginFooter = CaptureOutput::Capture( function () {
			do_action( 'login_footer' );
		} );

		return [
			'content' => [
				'action_login_footer' => $actionLoginFooter,
			],
		];
	}
}