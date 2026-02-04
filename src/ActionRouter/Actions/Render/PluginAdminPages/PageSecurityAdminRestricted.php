<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Services\Utilities\Obfuscate;

class PageSecurityAdminRestricted extends BasePluginAdminPage {

	use SecurityAdminNotRequired;

	public const SLUG = 'admin_plugin_page_security_admin_restricted';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/security_admin.twig';

	protected function getPageContextualHrefs() :array {
		return [
			[
				'title' => __( 'Disable Security Admin', 'wp-simple-firewall' ),
				'href'  => '#',
				'id'    => 'SecAdminRemoveConfirmEmail',
			],
		];
	}

	protected function getRenderData() :array {
		$con = self::con();
		return [
			'flags'   => [
				'allow_email_override' => $con->opts->optIs( 'allow_email_override', 'Y' )
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->raw( 'person-badge' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Security Plugin Protection', 'wp-simple-firewall' ),
				'inner_page_subtitle' => sprintf( __( 'Access to the %s Security plugin is restricted.', 'wp-simple-firewall' ), $con->labels->Name ),

				'force_remove_email' => __( "If you've forgotten your PIN, use the menu above to disable this restriction.", 'wp-simple-firewall' ),
				'send_to_email'      => sprintf( __( 'Confirmation email will be sent to %s', 'wp-simple-firewall' ),
					Obfuscate::Email( $con->comps->opts_lookup->getReportEmail() ) ),
				'no_email_override'  => __( "The Security Administrator has restricted the use of the email override feature.", 'wp-simple-firewall' ),
			],
		];
	}
}