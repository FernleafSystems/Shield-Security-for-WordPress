<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterUsers extends MeterBase {

	const SLUG = 'users';

	protected function title() :string {
		return __( 'Customer And Visitor Protection', 'wp-simple-firewall' );
	}

	protected function subtitle() :string {
		return __( 'How well customers, users, and visitors are protected', 'wp-simple-firewall' );
	}

	protected function description() :array {
		return [
			__( "Regular file scanning is important to ensure malicious files are caught before they can be abused.", 'wp-simple-firewall' ),
			__( "Scanning is often marketed as the most important aspect of security, but this thinking is backwards.", 'wp-simple-firewall' )
			.' '.__( "Scanning is remedial and detection of malware, for example, is a symptom of larger problem i.e. that your site is vulnerable to intrusion.", 'wp-simple-firewall' ),
			__( "It is, nevertheless, a critical component of WordPress security hygiene.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponentSlugs() :array {
		$components = [
			'admin_user',
			'secadmin_admins',
			'2fa',
			'sessions_idle',
			'users_inactive',
			'pass_policies',
			'pass_pwned',
			'pass_str',
			'headers',
		];
		if ( !$this->getCon()->getModule_SecAdmin()->getWhiteLabelController()->isEnabled() ) {
			$components[] = 'plugin_badge';
		}
		return $components;
	}
}