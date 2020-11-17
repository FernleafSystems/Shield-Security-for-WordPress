<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

class OverviewCards extends Shield\Modules\Base\Insights\OverviewCards {

	public function build() :array {
		/** @var AuditTrail\ModCon $mod */
		$mod = $this->getMod();
		/** @var AuditTrail\Options $opts */
		$opts = $this->getOptions();

		$cardSection = [
			'title'        => __( 'Activity Audit Log', 'wp-simple-firewall' ),
			'subtitle'     => $mod->getStrings()->getModTagLine(),
			'href_options' => $mod->getUrl_AdminPage(),
		];

		$cards = [];

		if ( !$mod->isModOptEnabled() ) {
			$data[ 'key_opts' ][ 'mod' ] = $this->getModDisabledCard();
		}
		else {
			$cards[ 'audit' ] = [
				'name'    => __( 'Audit Areas', 'wp-simple-firewall' ),
				'state'   => 1,
				'summary' => __( 'All important events on your site are being logged', 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'section_enable_audit_contexts' ),
			];

			$cards[ 'audit_length' ] = [
				'name'    => __( 'Audit Trail', 'wp-simple-firewall' ),
				'state'   => 0,
				'summary' => sprintf( __( 'Maximum Audit Trail entries limited to %s', 'wp-simple-firewall' ), $opts->getMaxEntries() ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'audit_trail_max_entries' ),
			];
		}

		$cardSection[ 'cards' ] = $cards;
		return [ 'audit_trail' => $cardSection ];
	}
}