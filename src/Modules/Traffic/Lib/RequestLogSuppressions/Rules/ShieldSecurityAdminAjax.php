<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogSuppressions\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\AjaxBatchRequests,
	Actions\AjaxRender,
	Actions\CrowdsecResetEnrollment,
	Actions\MainWP\ServerActions\BaseSiteMwpAction,
	Actions\MainWP\ServerActions\MainwpServerClientActionHandler,
	Actions\MfaEmailDisable,
	Actions\MfaEmailSendVerification,
	Actions\MfaUserConfigBase,
	Actions\PluginAutoDbRepair,
	Actions\PluginDeleteForceOff,
	Actions\RuleBuilderAction,
	Actions\ScansStart,
	Actions\SecurityAdminAuthClear,
	Actions\SecurityAdminRemove,
	Actions\SecurityAdminRequestRemoveByEmail,
	Actions\ToolPurgeProviderIPs,
	Utility\ActionsMap,
	Utility\RenderActionTarget
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\UserMfa\UserMfaBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogSuppressions\{
	BaseRule,
	Context
};

class ShieldSecurityAdminAjax extends BaseRule {

	private const READ_ONLY_SUB_ACTIONS = [
		'retrieve_table_data',
		'get_request_meta',
	];

	private const ALWAYS_LOGGABLE_ACTIONS = [
		CrowdsecResetEnrollment::class,
		MainwpServerClientActionHandler::class,
		MfaEmailDisable::class,
		MfaEmailSendVerification::class,
		PluginAutoDbRepair::class,
		PluginDeleteForceOff::class,
		ScansStart::class,
		SecurityAdminAuthClear::class,
		SecurityAdminRemove::class,
		SecurityAdminRequestRemoveByEmail::class,
		ToolPurgeProviderIPs::class,
	];

	public function matches( Context $context ) :bool {
		if ( !$context->isAjax()
			 || !$context->isSecurityAdmin()
			 || $context->method() !== 'POST'
			 || $context->path() !== '/wp-admin/admin-ajax.php'
		) {
			return false;
		}

		return $this->isSuppressiblePayload( $context->postData() );
	}

	/**
	 * @param array<string,mixed> $payload
	 */
	private function isSuppressiblePayload( array $payload, bool $allowBatch = true ) :bool {
		if ( (string)( $payload[ ActionData::FIELD_ACTION ] ?? '' ) !== ActionData::FIELD_SHIELD ) {
			return false;
		}

		$actionSlug = (string)( $payload[ ActionData::FIELD_EXECUTE ] ?? '' );
		$action = ActionsMap::ActionFromSlug( $actionSlug );
		if ( empty( $action ) ) {
			return false;
		}

		if ( $actionSlug === AjaxBatchRequests::SLUG ) {
			return $allowBatch && $this->batchContainsOnlySuppressibleRequests( $payload[ 'requests' ] ?? null );
		}

		if ( $actionSlug === AjaxRender::SLUG ) {
			$renderAction = RenderActionTarget::resolve( (string)( $payload[ 'render_slug' ] ?? '' ) );
			return !empty( $renderAction ) && !$this->isLoggableRenderAction( $renderAction );
		}

		return !$this->isLoggableAction( $action, $payload );
	}

	/**
	 * @param mixed $batchRequests
	 */
	private function batchContainsOnlySuppressibleRequests( $batchRequests ) :bool {
		if ( !\is_array( $batchRequests ) || empty( $batchRequests ) ) {
			return false;
		}

		foreach ( $batchRequests as $requestItem ) {
			if ( !\is_array( $requestItem ) ) {
				return false;
			}

			$subRequest = $requestItem[ 'request' ] ?? null;
			if ( !\is_array( $subRequest ) ) {
				return false;
			}

			if ( !$this->isSuppressiblePayload( $subRequest, false ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string,mixed> $payload
	 */
	private function isLoggableAction( string $action, array $payload ) :bool {
		return \in_array( $action, self::ALWAYS_LOGGABLE_ACTIONS, true )
			   || \is_a( $action, MfaUserConfigBase::class, true )
			   || \is_a( $action, BaseSiteMwpAction::class, true )
			   || $this->isLoggableSubAction( $payload )
			   || (
				   $action === RuleBuilderAction::class
				   && (string)( $payload[ 'builder_action' ] ?? '' ) === 'create_rule'
			   );
	}

	private function isLoggableRenderAction( string $action ) :bool {
		return \is_a( $action, UserMfaBase::class, true );
	}

	/**
	 * @param array<string,mixed> $payload
	 */
	private function isLoggableSubAction( array $payload ) :bool {
		$subAction = (string)( $payload[ 'sub_action' ] ?? '' );
		return $subAction !== '' && !\in_array( $subAction, self::READ_ONLY_SUB_ACTIONS, true );
	}
}
