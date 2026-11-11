<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\MainWP\ClientActions\MainwpEnable,
	Actions\PluginSetOpt,
	Exceptions\ActionException,
	Utility\ExternalEntryGuard
};

/**
 * Runs a Shield action requested by a MainWP dashboard through MainWP Child's extra_execution, which MainWP
 * authenticates before Shield sees the request. Only actions declared for the `mainwp` entry can run.
 */
class ClientActionRunner {

	/**
	 * @throws ActionException
	 */
	public function run( string $slug, array $params ) :array {
		// Older dashboards send override metadata. It has no meaning here.
		unset( $params[ 'action_overrides' ] );
		[ $slug, $params ] = $this->normaliseLegacyRequest( $slug, $params );

		$guard = new ExternalEntryGuard();
		if ( !$guard->isAdmitted( $slug, ExternalEntryGuard::ENTRY_MAINWP ) ) {
			$guard->recordRejection( $slug, ExternalEntryGuard::ENTRY_MAINWP );
			throw new ActionException( 'This action is not available to MainWP.' );
		}

		$action = ( new ActionProcessor() )->getAction( $slug, $params );
		$action->process();
		return $action->response()->payload();
	}

	/**
	 * Dashboards that predate MainwpEnable switch the integration on with a generic option write. Accept exactly
	 * that request and nothing else.
	 */
	private function normaliseLegacyRequest( string $slug, array $params ) :array {
		if ( $slug === PluginSetOpt::SLUG
			 && ( $params[ 'opt_key' ] ?? null ) === 'enable_mainwp'
			 && ( $params[ 'opt_value' ] ?? null ) === 'Y' ) {
			$slug = MainwpEnable::SLUG;
			$params = [];
		}
		return [ $slug, $params ];
	}
}
