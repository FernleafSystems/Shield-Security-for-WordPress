<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\MainWP\ClientActions\MainwpEnable,
	Actions\PluginSetOpt,
	Constants,
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
		// Older dashboards send override metadata; the request never controls security checks.
		unset( $params[ 'action_overrides' ] );
		[ $slug, $params ] = $this->normaliseLegacyRequest( $slug, $params );

		$guard = new ExternalEntryGuard();
		if ( !$guard->isAdmitted( $slug, ExternalEntryGuard::ENTRY_MAINWP ) ) {
			$guard->recordRejection( $slug, ExternalEntryGuard::ENTRY_MAINWP );
			throw new ActionException( 'This action is not available to MainWP.' );
		}

		$action = ( new ActionProcessor() )->getAction( $slug, $params );
		// MainWP has authenticated the request. Until the action-level nonce check is removed, stop the action
		// looking for a nonce of its own.
		$action->setActionOverride( Constants::ACTION_OVERRIDE_IS_NONCE_VERIFY_REQUIRED, false );
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
