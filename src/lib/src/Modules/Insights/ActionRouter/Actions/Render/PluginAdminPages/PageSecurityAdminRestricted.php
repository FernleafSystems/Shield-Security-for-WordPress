<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Options;
use FernleafSystems\Wordpress\Services\Utilities\Obfuscate;

class PageSecurityAdminRestricted extends Actions\Render\BaseRender {

	use Actions\Traits\SecurityAdminNotRequired;

	public const SLUG = 'admin_plugin_page_security_admin_restricted';
	public const PRIMARY_MOD = 'admin_access_restriction';
	public const TEMPLATE = '/wpadmin_pages/security_admin/index.twig';

	protected function getRenderData() :array {
		/** @var Options $secOpts */
		$secOpts = $this->primary_mod->getOptions();
		return [
			'flags'   => [
				'allow_email_override' => $secOpts->isEmailOverridePermitted()
			],
			'hrefs'   => [
				'form_action' => $this->getCon()->getPluginUrl_DashboardHome()
			],
			'strings' => [
				'force_remove_email' => __( "If you've forgotten your PIN, a link can be sent to the plugin administrator email address to remove this restriction.", 'wp-simple-firewall' ),
				'click_email'        => __( "Click here to send the verification email.", 'wp-simple-firewall' ),
				'send_to_email'      => sprintf( __( "Email will be sent to %s", 'wp-simple-firewall' ),
					Obfuscate::Email( $this->primary_mod->getPluginReportEmail() ) ),
				'no_email_override'  => __( "The Security Administrator has restricted the use of the email override feature.", 'wp-simple-firewall' ),
			],
		];
	}
}