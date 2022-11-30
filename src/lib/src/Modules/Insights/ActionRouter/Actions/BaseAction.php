<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

/**
 * @property ModCon $primary_mod
 * @property string $primary_mod_slug
 * @property array  $action_data
 */
abstract class BaseAction extends DynPropertiesClass {

	use ModConsumer;

	public const SLUG = '';
	public const PATTERN = '';
	public const PRIMARY_MOD = 'insights';

	private $response;

	/**
	 * @throws ActionException
	 */
	public function __construct( array $data = [], ?ActionResponse $response = null ) {
		$this->action_data = $data;
		$this->checkAvailableData();
		$this->response = $response instanceof ActionResponse ? $response : new ActionResponse();
	}

	public static function Pattern() :string {
		return sprintf( '#^%s$#', empty( static::PATTERN ) ? static::SLUG : static::PATTERN );
	}

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {
			case 'action_data':
				$value = array_merge( $this->getDefaults(), is_array( $value ) ? $value : [] );
				break;

			case 'primary_mod':
				$value = $this->getCon()->getModule( $this->primary_mod_slug );
				if ( empty( $value ) ) {
					$value = $this->getMod();
				}
				break;

			case 'primary_mod_slug':
				if ( empty( $value ) ) {
					if ( empty( static::PRIMARY_MOD ) ) {
						throw new \Exception( 'Empty primary mod: '.get_class( $this ) );
					}
					$value = static::PRIMARY_MOD;
				}
				break;

			default:
				break;
		}

		return $value;
	}

	/**
	 * @throws ActionException
	 */
	public function process() {
		$this->preExec();
		$this->exec();
		$this->postExec();
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

	public function isNonceVerifyRequired() :bool {
		return $this->getCon()->this_req->wp_is_ajax;
	}

	public function isUserAuthRequired() :bool {
		return true;
	}

	public function isSecurityAdminRestricted() :bool {
		return $this->isUserAuthRequired();
	}

	/**
	 * @throws ActionException
	 */
	protected function checkAvailableData() {
		$missing = array_diff( array_unique( $this->getRequiredDataKeys() ), array_keys( $this->action_data ) );
		if ( !empty( $missing ) ) {
			throw new ActionException( sprintf( 'Missing action (%s) data for the following keys: %s', static::SLUG, implode( ', ', $missing ) ) );
		}
	}

	protected function getRequiredDataKeys() :array {
		return [];
	}
}