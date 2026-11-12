<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\MainWP\ClientActions\MainwpEnable,
	Exceptions\ActionException,
	Utility\ExternalEntryGuard
};

/**
 * Runs a Shield action requested by a MainWP dashboard through MainWP Child's extra_execution, which MainWP
 * authenticates before Shield sees the request. Only actions declared for the `mainwp` entry can run.
 */
class ClientActionRunner {

	/**
	 * Compatibility: dashboards switch the integration on by sending this generic option-write request, older
	 * ones because they predate MainwpEnable and current ones so that older child sites understand it too. The
	 * action it once named no longer exists, and this exact request is the only form still accepted. Remove when
	 * versions older than MainwpEnable are out of support.
	 */
	public const LEGACY_ENABLE_SLUG = 'plugin_set_opt';

	/**
	 * @throws ActionException
	 */
	public function run( string $slug, array $params ) :array {
		// Older dashboards send override metadata. It has no meaning here.
		unset( $params[ 'action_overrides' ] );
		[ $slug, $params ] = $this->normaliseLegacyRequest( $slug, $params );

		if ( !( new ExternalEntryGuard() )->admit( $slug, ExternalEntryGuard::ENTRY_MAINWP ) ) {
			throw new ActionException( 'This action is not available to MainWP.' );
		}

		$action = ( new ActionProcessor() )->getAction( $slug, ExternalEntryGuard::ActionDataFromTransport( $params ) );
		$action->process();
		return $action->response()->payload();
	}

	/**
	 * Accept exactly the legacy enable request and nothing else.
	 */
	private function normaliseLegacyRequest( string $slug, array $params ) :array {
		if ( $slug === self::LEGACY_ENABLE_SLUG
			 && ( $params[ 'opt_key' ] ?? null ) === 'enable_mainwp'
			 && ( $params[ 'opt_value' ] ?? null ) === 'Y' ) {
			$slug = MainwpEnable::SLUG;
			$params = [];
		}
		return [ $slug, $params ];
	}
}
