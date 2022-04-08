<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;

class AdminNotices extends Shield\Modules\Base\AdminNotices {

	/**
	 * @inheritDoc
	 */
	protected function processNotice( NoticeVO $notice ) {

		switch ( $notice->id ) {

			case 'new-audit-trail':
				$this->buildNotice_NewAuditTrail( $notice );
				break;

			default:
				parent::processNotice( $notice );
				break;
		}
	}

	protected function isDisplayNeeded( NoticeVO $notice ) :bool {
		switch ( $notice->id ) {

			case 'new-audit-trail':
				$needed = $this->getMod()->isPage_InsightsThisModule();
				break;

			default:
				$needed = parent::isDisplayNeeded( $notice );
				break;
		}
		return $needed;
	}

	private function buildNotice_NewAuditTrail( NoticeVO $notice ) {
		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'     => __( "Your Security Audit Log Is Brand New", 'wp-simple-firewall' ),
				'lines'     => [
					__( "We've completely completely rewritten the security audit log making it more detailed, searchable, faster, and more.", 'wp-simple-firewall' ),
					__( "Every effort has been made to convert old data to the new format, but it wasn't 100% possible.", 'wp-simple-firewall' )
					.' '.__( "Some older events may have missing data, but all new events will populate properly.", 'wp-simple-firewall' ),
				],
				'read_more' => __( 'Click here to read more about the changes', 'wp-simple-firewall' )
			],
			'hrefs'             => [
				'read_more' => 'https://shsec.io/kf'
			]
		];
	}
}