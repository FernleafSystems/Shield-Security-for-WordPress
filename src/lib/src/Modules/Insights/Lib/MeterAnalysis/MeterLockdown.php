<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	Lockdown,
	SecurityAdmin
};

class MeterLockdown extends MeterBase {

	const SLUG = 'lockdown';

	protected function title() :string {
		return __( 'Site Lockdown', 'wp-simple-firewall' );
	}

	protected function buildComponents() :array {
		$modLockdown = $this->getCon()->getModule_Lockdown();
		/** @var Lockdown\Options $optsLockdown */
		$optsLockdown = $modLockdown->getOptions();

		$modSecAdmin = $this->getCon()->getModule_SecAdmin();
		$secAdminCon = $modSecAdmin->getSecurityAdminController();
		/** @var SecurityAdmin\Options $optsSecAdmin */
		$optsSecAdmin = $modSecAdmin->getOptions();

		$isSecAdminEnabled = $secAdminCon->isEnabledSecAdmin();
		return [
			'secadmin'         => [
				'title'            => __( 'Security Admin Protection', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'The security plugin is protected against tampering through use of a Security Admin PIN.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "The security plugin isn't protected against tampering through use of a Security Admin PIN.", 'wp-simple-firewall' ),
				'href'             => $modSecAdmin->getUrl_DirectLinkToOption( 'admin_access_key' ),
				'protected'        => $isSecAdminEnabled,
				'weight'           => 40,
			],
			'secadmin_admins'  => [
				'title'            => __( 'WordPress Admins Protection', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'WordPress admin accounts are protected against tampering by other WordPress admins.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "WordPress admin accounts aren't protected against tampering by other WordPress admins.", 'wp-simple-firewall' ),
				'href'             => $modSecAdmin->getUrl_DirectLinkToOption( 'admin_access_restrict_admin_users' ),
				'protected'        => $isSecAdminEnabled && $optsSecAdmin->isSecAdminRestrictUsersEnabled(),
				'weight'           => 20,
			],
			'secadmin_options' => [
				'title'            => __( 'WordPress Settings Protection', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Critical WordPress settings are protected against tampering by other WordPress admins.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Critical WordPress settings aren't protected against tampering by other WordPress admins.", 'wp-simple-firewall' ),
				'href'             => $modSecAdmin->getUrl_DirectLinkToOption( 'admin_access_restrict_options' ),
				'protected'        => $isSecAdminEnabled && $optsSecAdmin->isRestrictWpOptions(),
				'weight'           => 20,
			],
			'xmlrpc'           => [
				'title'            => __( 'XML-RPC Access', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Access to XML-RPC is disabled.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Access to XML-RPC is available.", 'wp-simple-firewall' ),
				'href'             => $modLockdown->getUrl_DirectLinkToOption( 'disable_xmlrpc' ),
				'protected'        => $optsLockdown->isXmlrpcDisabled(),
				'weight'           => 30,
			],
			'file_editing'     => [
				'title'            => __( 'WordPress File Editing', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Editing files from within the WordPress admin area is disabled.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Editing files from within the WordPress admin area is allowed.", 'wp-simple-firewall' ),
				'href'             => $modLockdown->getUrl_DirectLinkToOption( 'disable_file_editing' ),
				'protected'        => $optsLockdown->isOptFileEditingDisabled(),
				'weight'           => 30,
			],
			'author_discovery' => [
				'title'            => sprintf( '%s / %s', __( 'Username Fishing', 'wp-simple-firewall' ), __( 'Author Discovery', 'wp-simple-firewall' ) ),
				'desc_protected'   => __( 'The ability to fish for WordPress usernames is disabled.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "The ability to fish for WordPress usernames isn't blocked.", 'wp-simple-firewall' ),
				'href'             => $modLockdown->getUrl_DirectLinkToOption( 'block_author_discovery' ),
				'protected'        => $optsLockdown->isBlockAuthorDiscovery(),
				'weight'           => 30,
			],
			'anonymous_rest'   => [
				'title'            => __( 'Anonymous REST API Access', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Anonymous/Unauthenticated access to the WordPress REST API is disabled.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Anonymous/Unauthenticated access to the WordPress REST API isn't blocked.", 'wp-simple-firewall' ),
				'href'             => $modLockdown->getUrl_DirectLinkToOption( 'disable_anonymous_restapi' ),
				'protected'        => $optsLockdown->isRestApiAnonymousAccessDisabled(),
				'weight'           => 20,
			],
		];
	}
}