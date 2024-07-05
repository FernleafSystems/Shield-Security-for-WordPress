<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\ProviderNotActiveForUserException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\MfaController;

abstract class AbstractProvider implements Provider2faInterface {

	protected const SLUG = '';

	/**
	 * @var \WP_User
	 */
	private $user;

	/**
	 * @var string
	 */
	protected $workingHashedLoginNonce;

	public function __construct( \WP_User $user ) {
		$this->user = $user;
	}

	public static function ProviderSlug() :string {
		return \strtolower( static::SLUG );
	}

	public static function ProviderName() :string {
		return static::ProviderSlug();
	}

	public function getProviderName() :string {
		return static::ProviderName();
	}

	public function getUser() :\WP_User {
		return $this->user;
	}

	public function isProviderStandalone() :bool {
		return true;
	}

	public function isEnforced() :bool {
		return false;
	}

	public function isProviderAvailableToUser() :bool {
		return $this->isProviderEnabled();
	}

	abstract public function getLoginIntentFormParameter() :string;

	public function renderLoginIntentFormField( string $format ) :string {
		switch ( $format ) {
			case MfaController::LOGIN_INTENT_PAGE_FORMAT_WP:
				$field = $this->renderLoginIntentFormFieldForWpLoginReplica();
				break;
			case MfaController::LOGIN_INTENT_PAGE_FORMAT_SHIELD:
			default:
				$field = $this->renderLoginIntentFormFieldForShield();
				break;
		}
		return $field;
	}

	abstract protected function renderLoginIntentFormFieldForShield() :string;

	abstract protected function renderLoginIntentFormFieldForWpLoginReplica() :string;

	public function setUser( \WP_User $user ) {
		$this->user = $user;
	}

	public function validateLoginIntent( string $hashedLoginNonce ) :bool {
		$this->workingHashedLoginNonce = $hashedLoginNonce;

		if ( !$this->isProfileActive() ) {
			throw new ProviderNotActiveForUserException();
		}

		return false;
	}
}