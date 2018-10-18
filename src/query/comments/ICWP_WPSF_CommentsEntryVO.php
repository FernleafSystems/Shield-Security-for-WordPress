<?php

require_once( dirname( dirname( __FILE__ ) ).'/base/ICWP_WPSF_BaseEntryVO.php' );

/**
 * Class ICWP_WPSF_CommentsEntryVO
 * @property int    post_id
 * @property string unique_token
 * @property string ip
 */
class ICWP_WPSF_CommentsEntryVO extends ICWP_WPSF_BaseEntryVO {

	/**
	 * @return string
	 */
	public function getIp() {
		return (string)$this->ip;
	}

	/**
	 * @return int
	 */
	public function getPostId() {
		return (int)$this->post_id;
	}

	/**
	 * @return string
	 */
	public function getToken() {
		return (string)$this->unique_token;
	}
}