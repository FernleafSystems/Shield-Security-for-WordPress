<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;
use FernleafSystems\Wordpress\Services\Services;

class AdminNotices extends Shield\Modules\Base\AdminNotices {

	/**
	 * @inheritDoc
	 */
	protected function processNotice( NoticeVO $notice ) {

		switch ( $notice->id ) {

			case 'visitor-whitelisted':
				$this->buildNotice_VisitorWhitelisted( $notice );
				break;

			default:
				parent::processNotice( $notice );
				break;
		}
	}

	private function buildNotice_VisitorWhitelisted( NoticeVO $notice ) {
		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'             => sprintf(
					__( '%s is ignoring you', 'wp-simple-firewall' ),
					$this->getCon()->getHumanName()
				),
				'your_ip'           => sprintf(
					__( 'Your IP address is: %s', 'wp-simple-firewall' ),
					$this->getCon()->this_req->ip
				),
				'notice_message'    => __( 'Your IP address is whitelisted and NO features you activate apply to you.', 'wp-simple-firewall' ),
				'including_message' => __( 'Including the hiding the WP Login page.', 'wp-simple-firewall' )
			]
		];
	}

	protected function isDisplayNeeded( NoticeVO $notice ) :bool {
		switch ( $notice->id ) {

			case 'visitor-whitelisted':
				$needed = $this->getCon()->this_req->is_ip_whitelisted;
				break;

			default:
				$needed = parent::isDisplayNeeded( $notice );
				break;
		}
		return $needed;
	}
}