<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Services\Utilities\Obfuscate;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageSecurityAdminRestricted extends PageModeLandingBase {

	use SecurityAdminNotRequired;

	public const SLUG = 'admin_plugin_page_security_admin_restricted';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/security_admin.twig';

	protected function getRenderData() :array {
		$con = self::con();
		return \array_replace_recursive( parent::getRenderData(), [
			'flags'   => [
				'allow_email_override' => $con->opts->optIs( 'allow_email_override', 'Y' )
			],
			'imgs'    => [
				'icon_shield'           => $con->svgs->iconClass( 'shield-fill' ),
				'icon_external_link'    => $con->svgs->iconClass( 'box-arrow-up-right' ),
			],
			'strings' => [
				'disable_security_admin' => __( 'Disable Security Admin via Email', 'wp-simple-firewall' ),
				'send_to_email'          => sprintf( __( 'Confirmation email will be sent to %s', 'wp-simple-firewall' ),
					Obfuscate::Email( $con->comps->opts_lookup->getReportEmail() ) ),
			],
		] );
	}

	protected function getLandingTitle() :string {
		return __( 'Security Plugin Protection', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return sprintf( __( 'Access to the %s Security plugin is restricted.', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	protected function getLandingIcon() :string {
		return 'person-badge';
	}

	protected function getLandingMode() :string {
		return PluginNavs::NAV_DASHBOARD;
	}
}
