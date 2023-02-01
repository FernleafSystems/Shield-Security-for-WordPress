<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Options;
use FernleafSystems\Wordpress\Services\Utilities\Obfuscate;

class PageSecurityAdminRestricted extends BasePluginAdminPage {

	use SecurityAdminNotRequired;

	public const SLUG = 'admin_plugin_page_security_admin_restricted';
	public const PRIMARY_MOD = 'admin_access_restriction';
	public const TEMPLATE = '/wpadmin_pages/plugin_admin/security_admin.twig';

	protected function getPageContextualHrefs() :array {
		return [
			[
				'text' => 'Disable Security Admin',
				'href' => '#',
				'id'   => 'SecAdminRemoveConfirmEmail',
			],
		];
	}

	protected function getRenderData() :array {
		$con = $this->getCon();
		/** @var Options $secOpts */
		$secOpts = $this->primary_mod->getOptions();
		return [
			'flags'   => [
				'allow_email_override' => $secOpts->isEmailOverridePermitted()
			],
			'hrefs'   => [
				'form_action' => $con->plugin_urls->adminHome(),
			],
			'strings' => [
				'inner_page_title'    => __( 'Security Plugin Protection', 'wp-simple-firewall' ),
				'inner_page_subtitle' => sprintf( __( 'Access to the %s Security plugin is restricted.', 'wp-simple-firewall' ),
					$con->getHumanName() ),

				'force_remove_email' => __( "If you've forgotten your PIN, a link can be sent to the plugin administrator email address to remove this restriction.", 'wp-simple-firewall' ),
				'send_to_email'      => sprintf( __( "Email will be sent to %s", 'wp-simple-firewall' ),
					Obfuscate::Email( $this->primary_mod->getPluginReportEmail() ) ),
				'no_email_override'  => __( "The Security Administrator has restricted the use of the email override feature.", 'wp-simple-firewall' ),
			],
		];
	}
}