<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class WpIsXmlrpc extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'wp_is_xmlrpc';

	protected function execConditionCheck() :bool {
		return $this->req->wp_is_xmlrpc;
	}

	public function getName() :string {
		return __( 'Is WP XML-RPC', 'wp-simple-firewall' );
	}

	public function getDescription() :string {
		return __( 'Is the request to the WordPress XML-RPC endpoint.', 'wp-simple-firewall' );
	}
}