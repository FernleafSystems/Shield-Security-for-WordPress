<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\LicenseLookup,
	Actions\MainWP\MainwpBase,
	Exceptions\ActionException,
	Utility\ExternalEntryGuard
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions\ClientActionRunner;

/**
 * Runs one of a fixed set of operations against a MainWP client site. The request names the operation; the
 * action it runs and that action's parameters come only from Operations().
 */
class MainwpServerClientActionHandler extends MainwpBase {

	public const SLUG = 'mwp_server_site_client_action_handler';
	public const FIELD_OPERATION = 'client_site_action';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	/**
	 * @return array<string,array{site_action_slug:string,site_action_params?:array}>
	 */
	public static function Operations() :array {
		return [
			'sync'       => [
				'site_action_slug' => SiteActionSync::SLUG,
			],
			'activate'   => [
				'site_action_slug' => SiteActionActivate::SLUG,
			],
			'deactivate' => [
				'site_action_slug' => SiteActionDeactivate::SLUG,
			],
			'install'    => [
				'site_action_slug' => SiteActionInstall::SLUG,
			],
			'update'     => [
				'site_action_slug' => SiteActionUpdate::SLUG,
			],
			'license'    => [
				'site_action_slug'   => SiteCustomAction::SLUG,
				'site_action_params' => [
					'sub_action_slug' => LicenseLookup::SLUG,
				],
			],
			'mwp_on'     => [
				'site_action_slug'   => SiteCustomAction::SLUG,
				'site_action_params' => [
					// Sent in its legacy form so that child sites older than MainwpEnable also understand it.
					'sub_action_slug'   => ClientActionRunner::LEGACY_ENABLE_SLUG,
					'sub_action_params' => [
						'opt_key'   => 'enable_mainwp',
						'opt_value' => 'Y',
					],
				],
			],
		];
	}

	protected function exec() {
		try {
			$response = $this->fireActionOnClientSite();
		}
		catch ( \Exception $e ) {
			$response = [
				'success' => false,
				'message' => $e->getMessage()
			];
		}
		$payloadSuccess = (bool)( $response[ 'success' ] ?? false );
		unset( $response[ 'success' ] );

		$this->response()
			->setPayload( $response )
			->setPayloadSuccess( $payloadSuccess );
	}

	/**
	 * @throws ActionException
	 */
	protected function fireActionOnClientSite() :array {
		$operation = self::Operations()[ (string)$this->action_data[ self::FIELD_OPERATION ] ] ?? null;
		if ( empty( $operation ) ) {
			throw new ActionException( __( 'Unsupported site action.', 'wp-simple-firewall' ) );
		}

		$params = $operation[ 'site_action_params' ] ?? [];
		$params[ 'client_site_id' ] = $this->action_data[ 'client_site_id' ];

		$action = ( new ActionProcessor() )->getAction( $operation[ 'site_action_slug' ], $params );
		$action->process();
		return $action->response()->payload();
	}

	protected function getRequiredDataKeys() :array {
		return [
			'client_site_id',
			self::FIELD_OPERATION,
		];
	}
}
