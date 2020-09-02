<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class UI extends Base\ShieldUI {

	/**
	 * @return array
	 */
	public function getInsightsConfigCardData() {
		/** @var \ICWP_WPSF_FeatureHandler_AuditTrail $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$data = [
			'strings'      => [
				'title' => __( 'Activity Audit Log', 'wp-simple-firewall' ),
				'sub'   => __( 'Track Activity: What, Who, When, Where', 'wp-simple-firewall' ),
			],
			'key_opts'     => [],
			'href_options' => $mod->getUrl_AdminPage()
		];

		if ( !$mod->isModOptEnabled() ) {
			$data[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
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
				$data[ 'key_opts' ][ 'audit' ] = [
					'name'    => __( 'Audit Areas', 'wp-simple-firewall' ),
					'enabled' => true,
					'summary' => __( 'All important events on your site are being logged', 'wp-simple-firewall' ),
					'weight'  => 2,
					'href'    => $mod->getUrl_DirectLinkToSection( 'section_enable_audit_contexts' ),
				];
			}
			elseif ( empty( $aAudit ) ) {
				$data[ 'key_opts' ][ 'audit' ] = [
					'name'    => __( 'Audit Areas', 'wp-simple-firewall' ),
					'enabled' => false,
					'summary' => sprintf( __( 'No areas are set to be audited: %s', 'wp-simple-firewall' ), implode( ', ', $aAudit ) ),
					'weight'  => 2,
					'href'    => $mod->getUrl_DirectLinkToSection( 'section_enable_audit_contexts' ),
				];
			}
			else {
				$data[ 'key_opts' ][ 'nonaudit' ] = [
					'name'    => __( 'Audit Events', 'wp-simple-firewall' ),
					'enabled' => false,
					'summary' => sprintf( __( "Important events aren't being audited: %s", 'wp-simple-firewall' ), implode( ', ', $aNonAudit ) ),
					'weight'  => 2,
					'href'    => $mod->getUrl_DirectLinkToSection( 'section_enable_audit_contexts' ),
				];
			}

			$data[ 'key_opts' ][ 'length' ] = [
				'name'    => __( 'Audit Trail', 'wp-simple-firewall' ),
				'enabled' => true,
				'summary' => sprintf( __( 'Maximum Audit Trail entries limited to %s', 'wp-simple-firewall' ), $opts->getMaxEntries() ),
				'weight'  => 0,
				'href'    => $mod->getUrl_DirectLinkToOption( 'audit_trail_max_entries' ),
			];
		}

		return $data;
	}
}