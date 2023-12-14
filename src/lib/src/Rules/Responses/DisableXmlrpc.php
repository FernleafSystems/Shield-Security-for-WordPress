<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

/**
 * @deprecated 18.5.8
 */
class DisableXmlrpc extends Base {

	public const SLUG = 'disable_xmlrpc';

	private $processed = false;

	public function execResponse() :bool {
		add_filter( 'xmlrpc_enabled', [ $this, 'disableXmlrpc' ], 1000, 0 );
		add_filter( 'xmlrpc_methods', [ $this, 'disableXmlrpc' ], 1000, 0 );
		return true;
	}

	/**
	 * @return array|false
	 */
	public function disableXmlrpc() {
		if ( !$this->processed ) {
			$this->processed = true;
			self::con()->fireEvent( 'block_xml' );
		}
		return ( current_filter() == 'xmlrpc_enabled' ) ? false : [];
	}
}