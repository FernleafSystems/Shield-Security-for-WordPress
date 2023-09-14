<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;

class AdminNotices extends Shield\Modules\Base\AdminNotices {

	protected function processNotice( NoticeVO $notice ) {

		switch ( $notice->id ) {
			case 'override-forceoff':
				$this->buildNotice_OverrideForceoff( $notice );
				break;
			case 'allow-tracking':
				$this->buildNotice_AllowTracking( $notice );
				break;
			case 'rate-plugin':
				$this->buildNotice_RatePlugin( $notice );
				break;
			default:
				parent::processNotice( $notice );
				break;
		}
	}

	private function buildNotice_OverrideForceoff( NoticeVO $notice ) {
		$name = self::con()->getHumanName();

		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'   => sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), sprintf( __( '%s is not protecting your site', 'wp-simple-firewall' ), $name ) ),
				'message' => sprintf(
					__( 'Please delete the "%s" file to reactivate %s protection', 'wp-simple-firewall' ),
					'forceOff',
					$name
				),
				'delete'  => __( 'Click here to automatically delete the file', 'wp-simple-firewall' )
			],
		];
	}

	private function buildNotice_AllowTracking( NoticeVO $notice ) {
		$name = self::con()->getHumanName();

		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'           => sprintf( __( "Make %s even better by sharing usage info?", 'wp-simple-firewall' ), $name ),
				'want_to_track'   => sprintf( __( "We're hoping to understand how %s is configured and used.", 'wp-simple-firewall' ), $name ),
				'what_we_collect' => __( "We'd like to understand how effective it is on a global scale.", 'wp-simple-firewall' ),
				'data_anon'       => __( 'The data sent is always completely anonymous and we can never track you or your site.', 'wp-simple-firewall' ),
				'can_turn_off'    => __( 'It can be turned-off at any time within the plugin options.', 'wp-simple-firewall' ),
				'click_to_see'    => __( 'Click to see the RAW data that would be sent', 'wp-simple-firewall' ),
				'learn_more'      => __( 'Learn More.', 'wp-simple-firewall' ),
				'site_url'        => 'translate.fernleafsystems.com',
				'yes'             => __( 'Absolutely', 'wp-simple-firewall' ),
				'yes_i_share'     => __( "Yes, I'd be happy share this info", 'wp-simple-firewall' ),
				'hmm_learn_more'  => __( "I'd like to learn more, please", 'wp-simple-firewall' ),
				'no_help'         => __( "No, I don't want to help", 'wp-simple-firewall' ),
			],
			'ajax'              => [
				'set_plugin_tracking' => ActionData::BuildJson( Actions\PluginSetTracking::class ),
			],
			'hrefs'             => [
				'learn_more'       => 'https://translate.fernleafsystems.com',
				'link_to_see'      => self::con()->getModule_Plugin()->getLinkToTrackingDataDump(),
				'link_to_moreinfo' => 'https://shsec.io/shieldtrackinginfo',

			]
		];
	}

	private function buildNotice_RatePlugin( NoticeVO $notice ) {
		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'   => __( 'Can You Help Us With A Quick Review?', 'wp-simple-firewall' ),
				'dismiss' => __( "I'd rather not show this support", 'wp-simple-firewall' ).' / '.__( "I've done this already", 'wp-simple-firewall' ).' :D',
			],
			'hrefs'             => [
				'forums' => 'https://wordpress.org/support/plugin/wp-simple-firewall',
			]
		];
	}

	protected function isDisplayNeeded( NoticeVO $notice ) :bool {
		/** @var Options $opts */
		$opts = $this->opts();

		switch ( $notice->id ) {
			case 'override-forceoff':
				$needed = self::con()->this_req->is_force_off && !self::con()->isPluginAdminPageRequest();
				break;
			case 'allow-tracking':
				$needed = !$opts->isTrackingPermissionSet();
				break;
			default:
				$needed = parent::isDisplayNeeded( $notice );
				break;
		}
		return $needed;
	}
}