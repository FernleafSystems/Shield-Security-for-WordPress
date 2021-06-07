<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

interface ProviderInterface {

	/**
	 * Fired by Login Intent controller when capturing a login intent
	 */
	public function captureLoginAttempt();

	/**
	 * @return array
	 */
	public function getFormField();

	/**
	 * @return bool
	 */
	public function validateLoginIntent();

	/**
	 * @return bool
	 */
	public function isEnforced();

	/**
	 * @return bool
	 */
	public function isProfileActive();

	/**
	 * @return bool
	 */
	public function isProviderAvailableToUser();

	/**
	 * @return bool
	 */
	public function isProviderEnabled();

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile,
	 * so we can use "current user" functions.  Otherwise we need to be careful of mixing up users.
	 * @return string
	 */
	public function handleUserProfileSubmit();

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 * @return string
	 */
	public function renderUserProfileOptions();
}