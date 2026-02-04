<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class SecurityOverviewViewAs extends SecurityAdminBase {

	public const SLUG = 'security_overview_view_as';

	protected function exec() {
		$secOverviewPrefs = self::con()->opts->optGet( 'sec_overview_prefs' );

		$viewAs = $this->action_data[ 'view_as' ] ?? '';
		if ( \in_array( $viewAs, [ '', 'pro', 'free', true ] ) ) {
			$secOverviewPrefs[ 'view_as' ] = $viewAs;
			self::con()->opts->optSet( 'sec_overview_prefs', $secOverviewPrefs );
		}

		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}