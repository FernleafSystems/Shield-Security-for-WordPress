<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property ModCon $primary_mod
 * @property string $primary_mod_slug
 */
abstract class BaseAction extends DynPropertiesClass {

	use ModConsumer;

	const SLUG = '';
	const PATTERN = '';

	public static function GetPattern() :string {
		return sprintf( '#^%s$#', empty( static::PATTERN ) ? static::SLUG : static::PATTERN );
	}

	/**
	 * @var array
	 */
	protected $data;

	protected $response;

	/**
	 * @param ActionResponse|null $response
	 */
	public function __construct( array $data = [], $response = null ) {
		$this->data = $data;
		$this->response = $response instanceof ActionResponse ? $response : new ActionResponse();
		$this->response->action_slug = static::SLUG;
		$this->response->action_data = $this->data;
		$this->applyFromArray( Services::DataManipulation()->mergeArraysRecursive( $this->getDefaults(), $data ) );
	}

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {
			case 'primary_mod':
				$value = $this->getCon()->getModule( $this->primary_mod_slug );
				if ( empty( $value ) ) {
					error_log( 'primary_mod_slug not provided. Defaulting...' );
					$value = $this->getMod();
				}
				break;

			default:
				break;
		}

		return $value;
	}

	/**
	 * @return $this
	 * @throws ActionException
	 */
	public function process() {
		$this->preExec();
		$this->exec();
		$this->postExec();
		return $this;
	}

	protected function preExec() {
		$this->response()->action_slug = static::SLUG;
		$this->response()->action_data = $this->data;
	}

	protected function postExec() {
	}

	/**
	 * @throws ActionException
	 */
	abstract protected function exec();

	public function response() :ActionResponse {
		return $this->response;
	}

	protected function getDefaults() :array {
		return [];
	}

	/**
	 * TODO: perhaps replace the separate nonce verification on AJAX with this?
	 */
	public function isNonceVerifyRequired() :bool {
		return $this->getCon()->this_req->wp_is_ajax;
	}

	public function isUserAuthRequired() :bool {
		return true;
	}

	public function isSecurityAdminRestricted() :bool {
		return $this->isUserAuthRequired();
	}
}