<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class UI extends Base\ShieldUI {

	public function getInsightsOverviewCards() :array {
		/** @var \ICWP_WPSF_FeatureHandler_AuditTrail $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$cardSection = [
			'title'        => __( 'Activity Audit Log', 'wp-simple-firewall' ),
			'subtitle'     => __( 'Track Activity: What, Who, When, Where', 'wp-simple-firewall' ),
			'href_options' => $mod->getUrl_AdminPage()
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
					'state' => 0,
					'summary' => sprintf( __( "Important events aren't being audited: %s", 'wp-simple-firewall' ), implode( ', ', $aNonAudit ) ),
					'href'    => $mod->getUrl_DirectLinkToSection( 'section_enable_audit_contexts' ),
				];
			}

			$cards[ 'audit_length' ] = [
				'name'    => __( 'Audit Trail', 'wp-simple-firewall' ),
				'state' => 0,
				'summary' => sprintf( __( 'Maximum Audit Trail entries limited to %s', 'wp-simple-firewall' ), $opts->getMaxEntries() ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'audit_trail_max_entries' ),
			];
		}

		$cardSection[ 'cards' ] = $cards;
		return [ 'audit_trail' => $cardSection ];
	}

	public function buildInsightsVars() :array {
		$con = $this->getCon();
		/** @var \ICWP_WPSF_FeatureHandler_AuditTrail $mod */
		$mod = $this->getMod();
		/** @var Databases\AuditTrail\Select $dbSel */
		$dbSel = $mod->getDbHandler_AuditTrail()->getQuerySelector();

		/** @var Modules\Events\Strings $oEventStrings */
		$oEventStrings = $con->getModule_Events()->getStrings();
		$aEventsSelect = array_intersect_key( $oEventStrings->getEventNames(), array_flip( $dbSel->getDistinctEvents() ) );
		asort( $aEventsSelect );

		return [
			'ajax'    => [
				'render_table_audittrail' => $mod->getAjaxActionData( 'render_table_audittrail', true ),
				'item_addparamwhite'      => $mod->getAjaxActionData( 'item_addparamwhite', true )
			],
			'flags'   => [],
			'strings' => [
				'table_title'             => __( 'Audit Trail', 'wp-simple-firewall' ),
				'sub_title'               => __( 'Use the Audit Trail Glossary for help interpreting log entries.', 'wp-simple-firewall' ),
				'audit_trail_glossary'    => __( 'Audit Trail Glossary', 'wp-simple-firewall' ),
				'title_filter_form'       => __( 'Audit Trail Filters', 'wp-simple-firewall' ),
				'username_ignores'        => __( "Providing a username will cause the 'logged-in' filter to be ignored.", 'wp-simple-firewall' ),
				'exclude_your_ip'         => __( 'Exclude Your Current IP', 'wp-simple-firewall' ),
				'exclude_your_ip_tooltip' => __( 'Exclude Your IP From Results', 'wp-simple-firewall' ),
				'context'                 => __( 'Context', 'wp-simple-firewall' ),
				'event'                   => __( 'Event', 'wp-simple-firewall' ),
				'show_after'              => __( 'show results that occurred after', 'wp-simple-firewall' ),
				'show_before'             => __( 'show results that occurred before', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'events_for_select' => $aEventsSelect,
				'unique_ips'        => $dbSel->getDistinctIps(),
				'unique_users'      => $dbSel->getDistinctUsernames(),
			],
			'hrefs'   => [
				'audit_trail_glossary' => 'https://shsec.io/audittrailglossary',
			],
		];
	}

	/**
	 * @return array
	 */
	public function getInsightsConfigCardData() :array {
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