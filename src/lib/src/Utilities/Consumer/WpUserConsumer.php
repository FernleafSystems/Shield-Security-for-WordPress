<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities;

trait WpUserConsumer {

	/**
	 * @var \WP_User
	 */
	private $oWpUser;

	/**
	 * @return \WP_User
	 */
	public function getWpUser() {
		return $this->oWpUser;
	}

	/**
	 * @param \WP_User $user
	 * @return $this
	 */
	public function setWpUser( \WP_User $user ) {
		$this->oWpUser = $user;
		return $this;
	}
}