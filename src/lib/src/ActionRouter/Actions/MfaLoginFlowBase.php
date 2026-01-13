<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AuthNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\LoginWpUserConsumer;

/**
 * Base class for MFA actions that run during the login flow.
 *
 * These actions:
 * - Do NOT require authentication (user is in the middle of logging in)
 * - REQUIRE a valid login_nonce tied to the target user
 * - Use login_wp_user parameter with login_nonce validation
 */
abstract class MfaLoginFlowBase extends BaseAction {

	use AuthNotRequired;
	use LoginWpUserConsumer;
}