<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer;

trait WpUserConsumer {

	/**
	 * @var \WP_User
	 */
	private $wpUser;

	/**
	 * @return \WP_User|null
	 */
	public function getWpUser() {
		return $this->wpUser;
	}

	/**
	 * @param \WP_User|null $user
	 * @return $this
	 */
	public function setWpUser( $user ) {
		$this->wpUser = $user;
		return $this;
	}
}