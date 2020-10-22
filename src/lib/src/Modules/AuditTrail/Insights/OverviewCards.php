<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;

class OverviewCards extends Shield\Modules\Base\Insights\OverviewCards {

	public function build() :array {
		/** @var \ICWP_WPSF_FeatureHandler_AuditTrail $mod */
		$mod = $this->getMod();
		/** @var Shield\Modules\AuditTrail\Options $opts */
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
			$aAudit = [];
			$aNonAudit = [];
			$opts->isAuditShield() ? $aAudit[] = 'Shield' : $aNonAudit[] = 'Shield';
			$opts->isAuditUsers() ? $aAudit[] = __( 'users', 'wp-simple-firewall' ) : $aNonAudit[] = __( 'users', 'wp-simple-firewall' );
			$opts->isAuditPlugins() ? $aAudit[] = __( 'plugins', 'wp-simple-firewall' ) : $aNonAudit[] = __( 'plugins', 'wp-simple-firewall' );
			$opts->isAuditThemes() ? $aAudit[] = __( 'themes', 'wp-simple-firewall' ) : $aNonAudit[] = __( 'themes', 'wp-simple-firewall' );
			$opts->isAuditPosts() ? $aAudit[] = __( 'posts', 'wp-simple-firewall' ) : $aNonAudit[] = __( 'posts', 'wp-simple-firewall' );
			$opts->isAuditEmails() ? $aAudit[] = __( 'emails', 'wp-simple-firewall' ) : $aNonAudit[] = __( 'emails', 'wp-simple-firewall' );
			$opts->isAuditWp() ? $aAudit[] = 'WP' : $aNonAudit[] = 'WP';

			if ( empty( $aNonAudit ) ) {
				$cards[ 'audit' ] = [
					'name'    => __( 'Audit Areas', 'wp-simple-firewall' ),
					'state'   => 1,
					'summary' => __( 'All important events on your site are being logged', 'wp-simple-firewall' ),
					'href'    => $mod->getUrl_DirectLinkToOption( 'section_enable_audit_contexts' ),
				];
			}
			elseif ( empty( $aAudit ) ) {
				$cards[ 'audit' ] = [
					'name'    => __( 'Audit Areas', 'wp-simple-firewall' ),
					'state'   => -1,
					'summary' => sprintf( __( 'No areas are set to be audited: %s', 'wp-simple-firewall' ), implode( ', ', $aAudit ) ),
					'href'    => $mod->getUrl_DirectLinkToSection( 'section_enable_audit_contexts' ),
				];
			}
			else {
				$cards[ 'nonaudit' ] = [
					'name'    => __( 'Audit Events', 'wp-simple-firewall' ),
					'state'   => 0,
					'summary' => sprintf( __( "Important events aren't being audited: %s", 'wp-simple-firewall' ), implode( ', ', $aNonAudit ) ),
					'href'    => $mod->getUrl_DirectLinkToSection( 'section_enable_audit_contexts' ),
				];
			}

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