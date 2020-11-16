<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;
use FernleafSystems\Wordpress\Services\Services;

class AdminNotices extends Shield\Modules\Base\AdminNotices {

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $notice
	 * @throws \Exception
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

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 */
	private function buildNotice_VisitorWhitelisted( $oNotice ) {
		$oNotice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'             => sprintf(
					__( '%s is ignoring you', 'wp-simple-firewall' ),
					$this->getCon()->getHumanName()
				),
				'your_ip'           => sprintf(
					__( 'Your IP address is: %s', 'wp-simple-firewall' ),
					Services::IP()->getRequestIp()
				),
				'notice_message'    => __( 'Your IP address is whitelisted and NO features you activate apply to you.', 'wp-simple-firewall' ),
				'including_message' => __( 'Including the hiding the WP Login page.', 'wp-simple-firewall' )
			]
		];
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $notice
	 * @return bool
	 */
	protected function isDisplayNeeded( Shield\Utilities\AdminNotices\NoticeVO $notice ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		switch ( $notice->id ) {

			case 'visitor-whitelisted':
				$needed = $mod->isVisitorWhitelisted();
				break;

			default:
				$needed = parent::isDisplayNeeded( $notice );
				break;
		}
		return $needed;
	}
}