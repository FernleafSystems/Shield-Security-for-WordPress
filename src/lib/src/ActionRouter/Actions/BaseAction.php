<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionNonce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\{
	InvalidActionNonceException,
	IpBlockedException,
	SecurityAdminRequiredException,
	UserAuthRequiredException
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property array $action_data
 */
abstract class BaseAction extends DynPropertiesClass {

	use PluginControllerConsumer;

	public const SLUG = '';

	private ActionResponse $response;

	public function __construct( array $data = [], ?ActionResponse $response = null ) {
		$this->action_data = $data;
		$this->response = $response instanceof ActionResponse ? $response : new ActionResponse();
	}

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {
			case 'action_data':
				$value = \array_merge( $this->getDefaults(), \is_array( $value ) ? $value : [] );
				break;
			default:
				break;
		}

		return $value;
	}

	/**
	 * @throws ActionException
	 * @throws InvalidActionNonceException
	 * @throws IpBlockedException
	 * @throws SecurityAdminRequiredException
	 * @throws UserAuthRequiredException
	 */
	public function process() {
		$this->checkAccess();
		$this->checkAvailableData();
		$this->preExec();
		$this->exec();
		$this->postExec();
	}

	/**
	 * @throws InvalidActionNonceException
	 * @throws IpBlockedException
	 * @throws SecurityAdminRequiredException
	 * @throws UserAuthRequiredException
	 */
	protected function checkAccess() {
		$con = self::con();
		$thisReq = $con->this_req;
		if ( !$thisReq->request_bypasses_all_restrictions && $thisReq->is_ip_blocked && !$this->canBypassIpAddressBlock() ) {
			throw new IpBlockedException( sprintf( 'IP Address blocked so cannot process action: %s', static::SLUG ) );
		}

		$WPU = Services::WpUsers();
		if ( $this->isUserAuthRequired()
			 && ( !$WPU->isUserLoggedIn() || !user_can( $WPU->getCurrentWpUser(), $this->getMinimumUserAuthCapability() ) ) ) {
			throw new UserAuthRequiredException( sprintf( 'Must be logged-in to execute this action: %s', static::SLUG ) );
		}

		if ( !$thisReq->is_security_admin && $this->isSecurityAdminRequired() ) {
			throw new SecurityAdminRequiredException( sprintf( 'Security admin required for action: %s', static::SLUG ) );
		}

		if ( $this->isNonceVerifyRequired() && !ActionNonce::VerifyFromRequest() ) {
			throw new InvalidActionNonceException( 'Invalid Action Nonce Exception.' );
		}
	}

	protected function preExec() {
	}

	protected function postExec() {
	}

	/**
	 * @throws ActionException
	 */
	abstract protected function exec();

	public function response() :ActionResponse {
		$this->response->action_slug = static::SLUG;
		$this->response->action_data = $this->action_data;
		return $this->response;
	}

	public function setResponse( ActionResponse $response ) {
		$this->response = $response;
	}

	protected function getDefaults() :array {
		return [];
	}

	protected function getMinimumUserAuthCapability() :string {
		return self::con()->cfg->properties[ 'base_permissions' ] ?? 'manage_options';
	}

	protected function canBypassIpAddressBlock() :bool {
		return false;
	}

	protected function isNonceVerifyRequired() :bool {
		return (bool)( $this->getActionOverrides()[ Constants::ACTION_OVERRIDE_IS_NONCE_VERIFY_REQUIRED ] ?? self::con()->this_req->wp_is_ajax );
	}

	protected function isUserAuthRequired() :bool {
		return !empty( $this->getMinimumUserAuthCapability() );
	}

	protected function isSecurityAdminRequired() :bool {
		return $this->getMinimumUserAuthCapability() === 'manage_options';
	}

	protected function getActionOverrides() :array {
		return $this->action_data[ 'action_overrides' ] ?? [];
	}

	/**
	 * @throws ActionException
	 */
	protected function checkAvailableData() {
		$missing = \array_diff( \array_unique( $this->getRequiredDataKeys() ), \array_keys( $this->action_data ) );
		if ( !empty( $missing ) ) {
			throw new ActionException( sprintf( 'Missing action (%s) data for the following keys: %s', static::SLUG, \implode( ', ', $missing ) ) );
		}
	}

	protected function getRequiredDataKeys() :array {
		return [];
	}

	public static function NonceCfg() :array {
		return [
			'ip'  => false,
			'ttl' => 12,
		];
	}
}