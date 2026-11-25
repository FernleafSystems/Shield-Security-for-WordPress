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
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- WP-CLI error output, plain text: IpRuleAdd, IpRuleRemove and IpRulesEnumerate pass it to WP_CLI::error().
			throw new \Exception( sprintf( '%s %s',
				/* translators: %s: name of the IP list */
				sprintf( __( "'%s' is an unsupported IP list.", 'wp-simple-firewall' ), $list ),
				/* translators: %s: list of supported IP list names */
				sprintf( __( 'Please use one of %s.', 'wp-simple-firewall' ), "'bypass' or 'white'; 'block' or 'black'" )
			) );
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}
}