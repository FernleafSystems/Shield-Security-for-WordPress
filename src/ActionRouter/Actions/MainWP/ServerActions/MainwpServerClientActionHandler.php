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

	/**
	 * The operations a dashboard can run on a client site. The sites listing offers them and labels them by these
	 * names, and the page sends the chosen name back in FIELD_OPERATION.
	 */
	public const OP_SYNC = 'sync';
	public const OP_ACTIVATE = 'activate';
	public const OP_DEACTIVATE = 'deactivate';
	public const OP_INSTALL = 'install';
	public const OP_UPDATE = 'update';
	public const OP_LICENSE = 'license';
	public const OP_MWP_ON = 'mwp_on';

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
			self::OP_SYNC       => [
				'site_action_slug' => SiteActionSync::SLUG,
			],
			self::OP_ACTIVATE   => [
				'site_action_slug' => SiteActionActivate::SLUG,
			],
			self::OP_DEACTIVATE => [
				'site_action_slug' => SiteActionDeactivate::SLUG,
			],
			self::OP_INSTALL    => [
				'site_action_slug' => SiteActionInstall::SLUG,
			],
			self::OP_UPDATE     => [
				'site_action_slug' => SiteActionUpdate::SLUG,
			],
			self::OP_LICENSE    => [
				'site_action_slug'   => SiteCustomAction::SLUG,
				'site_action_params' => [
					'sub_action_slug' => LicenseLookup::SLUG,
				],
			],
			self::OP_MWP_ON     => [
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
		$operation = self::Operations()[ $this->fields()->string( self::FIELD_OPERATION ) ] ?? null;
		if ( $operation === null ) {
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
