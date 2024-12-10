<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @deprecated 20.1
 */
class CrowdSecApi {

	use PluginControllerConsumer;

	public function isReady() :bool {
		return false;
	}

	public function clearEnrollment() :void {
	}

	public function getAuthorizationToken() :string {
		return '';
	}

	public function getMachineID() :string {
		return '';
	}

	public function getAuthStatus() :string {
		return '';
	}

	public function login() :bool {
		return false;
	}

	public function authStart() {
	}

	public function machineRegister() {
	}

	public function machineLogin() {
	}

	public function machineEnroll() {
	}

	private function getScenarios() :array {
		return [];
	}

	private function getCsAuth() :array {
		return [];
	}

	private function getCsAuths() :array {
		return [];
	}

	private function storeCsAuth( array $csAuth ) {
	}

	public function getApiUserAgent() :string {
		return '';
	}

	/**
	 * Length: 32; At least 1 lower, 1 upper, 1 digit.
	 */
	private function generateCrowdsecPassword() :string {
		return '';
	}
}