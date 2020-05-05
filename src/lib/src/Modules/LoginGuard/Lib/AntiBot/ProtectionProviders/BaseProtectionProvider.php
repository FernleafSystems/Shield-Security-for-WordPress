<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

abstract class BaseProtectionProvider {

	use ModConsumer;

	/**
	 * @var bool
	 */
	private $bFactorTested;

	public function __construct() {
		add_action( 'wp_loaded', [ $this, 'setup' ] );
	}

	public function setup() {
	}

	/**
	 * @return bool
	 */
	public function isFactorTested() {
		return (bool)$this->bFactorTested;
	}

	/**
	 * @param LoginGuard\Lib\AntiBot\FormProviders\BaseFormProvider $oFormProvider
	 * @return string
	 */
	abstract public function buildFormInsert( $oFormProvider );

	/**
	 * @param LoginGuard\Lib\AntiBot\FormProviders\BaseFormProvider $oForm
	 * @throws \Exception
	 */
	abstract public function performCheck( $oForm );

	/**
	 * @param bool $bFactorTested
	 * @return $this
	 */
	public function setFactorTested( $bFactorTested ) {
		$this->bFactorTested = $bFactorTested;
		return $this;
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
}