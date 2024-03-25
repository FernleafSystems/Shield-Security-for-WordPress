<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

abstract class IpRulesBase extends BaseCmd {

	protected function getCmdBase() :array {
		return \array_merge( parent::getCmdBase(), [
			'ip-rules'
		] );
	}

	/**
	 * @throws \Exception
	 */
	protected function checkList( string $list ) {
		if ( !\in_array( $list, [ 'white', 'bypass', 'black', 'block', 'crowdsec' ] ) ) {
			throw new \Exception( sprintf( '%s %s',
				sprintf( __( "'%s' is an unsupported IP list.", 'wp-simple-firewall' ), $list ),
				sprintf( __( 'Please use one of %s.', 'wp-simple-firewall' ), "'bypass' or 'white'; 'block' or 'black'" )
			) );
		}
	}
}