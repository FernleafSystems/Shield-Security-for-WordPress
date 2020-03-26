<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

abstract class ICWP_WPSF_Processor_LoginProtect_Base extends Modules\BaseShield\ShieldProcessor {

	/**
	 * @var string
	 */
	private $sActionToAudit;

	/**
	 * @var string
	 */
	private $sUserToAudit;

	/**
	 * @var bool
	 */
	private $bFactorTested;

	/**
	 */
	public function run() {
		$this->setFactorTested( false );
		add_action( 'init', [ $this, 'addHooks' ], -100 );
	}

	public function addHooks() {
		return;
	}

	/**
	 * @throws \Exception
	 */
	abstract protected function performCheckWithException();

	/**
	 * @return string
	 */
	protected function buildFormItems() {
		return '';
	}

	/**
	 * @return string
	 */
	protected function buildLoginFormItems() {
		$sItems = $this->buildFormItems();
		if ( !empty( $sItems ) ) {
			$this->incrementLoginFormPrintCount();
		}
		return $sItems;
	}

	/**
	 * @return void
	 */
	public function printFormItems() {
		echo $this->buildFormItems();
	}

	/**
	 * @return void
	 */
	public function printLoginFormItems() {
		echo $this->buildLoginFormItems();
	}

	/**
	 * @return void
	 */
	public function printLoginFormItems_Woo() {
		$this->printLoginFormItems();
	}

	/**
	 * @return void
	 */
	public function printRegisterFormItems_Woo() {
		$this->printFormItems();
	}

	/**
	 * @return string
	 */
	public function provideLoginFormItems() {
		return $this->buildLoginFormItems();
	}

	/**
	 * @return $this
	 */
	protected function processFailure() {
		remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );  // wp-includes/user.php
		remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );  // wp-includes/user.php
		$this->getCon()->fireEvent( 'login_block' );
		return $this;
	}

	/**
	 * @param \WP_Error $oMaybeWpError
	 * @return \WP_Error
	 */
	protected function giveMeWpError( $oMaybeWpError ) {
		return is_wp_error( $oMaybeWpError ) ? $oMaybeWpError : new \WP_Error();
	}

	/**
	 * @return string
	 */
	protected function getActionToAudit() {
		return empty( $this->sActionToAudit ) ? 'unknown-action' : $this->sActionToAudit;
	}

	/**
	 * @return string
	 */
	protected function getUserToAudit() {
		return empty( $this->sUserToAudit ) ? 'unknown' : $this->sUserToAudit;
	}

	/**
	 * @return bool
	 */
	public function isFactorTested() {
		return (bool)$this->bFactorTested;
	}

	/**
	 * @param string $sActionToAudit
	 * @return $this
	 */
	protected function setActionToAudit( $sActionToAudit ) {
		$this->sActionToAudit = $sActionToAudit;
		return $this;
	}

	/**
	 * @param bool $bFactorTested
	 * @return $this
	 */
	public function setFactorTested( $bFactorTested ) {
		$this->bFactorTested = $bFactorTested;
		return $this;
	}

	/**
	 * @param string $sUserToAudit
	 * @return $this
	 */
	protected function setUserToAudit( $sUserToAudit ) {
		$this->sUserToAudit = sanitize_user( $sUserToAudit );
		return $this;
	}

	/**
	 * @return bool
	 * @deprecated 9.0
	 */
	protected function canPrintLoginFormElement() {
		return true;
	}

	/**
	 * @return $this
	 * @deprecated 9.0
	 */
	public function incrementLoginFormPrintCount() {
		return $this;
	}

	/**
	 * @return int
	 * @deprecated 9.0
	 */
	protected function getLoginFormCountMax() {
		return 1;
	}

	/**
	 * @return int
	 * @deprecated 9.0
	 */
	protected function getLoginFormPrintCount() {
		return 0;
	}
}