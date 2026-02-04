<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Headers extends Base {

	public function tooltip() :string {
		return 'Edit settings for the entire HTTP Headers Zone';
	}

	public function components() :array {
		return [
			Component\HeadersGeneral::class,
			Component\HeadersCsp::class,
		];
	}

	public function description() :array {
		return [
			__( 'HTTP Headers are directives sent to your browser that control how it handles the content and responses it receives.', 'wp-simple-firewall' ),
			__( "These headers are processed in your visitors' browsers and they're designed to protect them from certain types of malicious content and attacks.", 'wp-simple-firewall' ),
			\implode( ' ', [
				__( "Misconfiguration can cause issues for your visitors, so we always recommend consulting experts in this area on how they might affect your site in particular.", 'wp-simple-firewall' ),
				__( "This is especially true of Content Security Policies (CSP) and you should always include full testing in your deployments.", 'wp-simple-firewall' ),
			] ),
			\implode( ' ', [
				__( "Please note that many WordPress Page Caching plugins completely ignore custom HTTP headers, and your chosen HTTP Headers will be absent during your tests.", 'wp-simple-firewall' ),
				__( "In this case, please disable your caching plugin(s) and consult with them on how you should proceed.", 'wp-simple-firewall' ),
				__( "You may need to seek out an alternative caching plugin.", 'wp-simple-firewall' ),
			] ),
		];
	}

	public function icon() :string {
		return 'chat-left-dots';
	}

	public function title() :string {
		return __( 'HTTP Headers', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'HTTP Headers provide protection for your site visitors.', 'wp-simple-firewall' );
	}

	protected function getUnderlyingModuleZone() :?string {
		return Component\Modules\ModuleHeaders::class;
	}
}