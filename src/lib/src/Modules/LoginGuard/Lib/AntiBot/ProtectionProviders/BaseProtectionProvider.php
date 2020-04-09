<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseProtectionProvider {

	use ModConsumer;

	/**
	 * @var bool
	 */
	private $bFactorTested;

	public function __construct() {
		if ( Services::Request()->query( 'wp_service_worker', 0 ) != 1 ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'onWpEnqueueJs' ] );
			add_action( 'login_enqueue_scripts', [ $this, 'onWpEnqueueJs' ] );
		}
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

	public function onWpEnqueueJs() {
	}

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