<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
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
	 * The form in which a dashboard asks a child site to switch on the MainWP integration: a request for the
	 * generic option-write action, which no longer exists. Only that exact request is accepted, and it runs
	 * MainwpEnable.
	 *
	 * @deprecated 22.4.0 Kept so that dashboards and child sites on different versions keep working together
	 *             while sites update. Child sites before 22.4.0 understand only this form and need the override
	 *             metadata sent with it; dashboards before 22.4.0 send only this form. Once versions before
	 *             22.4.0 are no longer supported, remove all of these together:
	 *             - this constant, normaliseLegacyRequest(), and the action_overrides discard in run();
	 *             - the legacy request in MainwpServerClientActionHandler::Operations(), which then names
	 *               MainwpEnable::SLUG and sends no parameters;
	 *             - the action_overrides metadata, and its discard, in SiteCustomAction::fireClientSiteAction().
	 */
	public const LEGACY_ENABLE_SLUG = 'plugin_set_opt';

	/**
	 * @throws ActionException
	 */
	public function run( string $slug, array $params ) :array {
		// @deprecated 22.4.0 Dashboards before 22.4.0 send override metadata, which has no meaning here. See
		// LEGACY_ENABLE_SLUG for when this goes.
		unset( $params[ 'action_overrides' ] );
		[ $slug, $params ] = $this->normaliseLegacyRequest( $slug, $params );

		return ( new ExternalEntryGuard() )
			->dispatch( ExternalEntryGuard::ENTRY_MAINWP, $slug, $params )
			->payload();
	}

	/**
	 * Accept exactly the legacy enable request and nothing else.
	 * @deprecated 22.4.0 See LEGACY_ENABLE_SLUG for when this goes.
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
