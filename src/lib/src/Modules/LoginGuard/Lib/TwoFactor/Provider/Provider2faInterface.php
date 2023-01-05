<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\{
	OtpNotPresentException,
	OtpVerificationFailedException,
	ProviderNotActiveForUserException
};

interface Provider2faInterface {

	/**
	 * Must Match: /^[a-z]+$/
	 */
	public static function ProviderSlug() :string;

	public function getUser() :\WP_User;

	public function getProviderName() :string;

	public function isProviderStandalone() :bool;

	/**
	 * @return true - always returns true if successful. Exception otherwise.
	 * @throws OtpNotPresentException
	 * @throws OtpVerificationFailedException
	 * @throws ProviderNotActiveForUserException
	 */
	public function validateLoginIntent( string $hashedLoginNonce ) :bool;

	public function isProfileActive() :bool;

	public function isProviderAvailableToUser() :bool;

	public function isProviderEnabled() :bool;

	public function isEnforced() :bool;

	public function removeFromProfile();

	public function renderUserProfileConfigFormField() :string;

	public function renderLoginIntentFormField( string $format ) :string;

	public function setUser( \WP_User $user );
}