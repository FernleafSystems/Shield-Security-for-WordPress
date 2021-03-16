<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class AuditTrail
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class AuditTrail extends BaseBuild {

	/**
	 * Override this to apply table-specific query filters.
	 * @return $this
	 */
	protected function applyCustomQueryFilters() {
		$params = $this->getParams();
		/** @var Shield\Databases\AuditTrail\Select $selector */
		$selector = $this->getWorkingSelector();

		$selector->filterByEvent( $params[ 'fEvent' ] );

		// If an IP is specified, it takes priority
		if ( Services::IP()->isValidIp( $params[ 'fIp' ] ) ) {
			$selector->filterByIp( $params[ 'fIp' ] );
		}
		elseif ( $params[ 'fExcludeYou' ] == 'Y' ) {
			$selector->filterByNotIp( Services::IP()->getRequestIp() );
		}

		/**
		 * put this date stuff in the base so we can filter anything
		 */
		if ( !empty( $params[ 'fDateFrom' ] ) && preg_match( '#^\d{4}-\d{2}-\d{2}$#', $params[ 'fDateFrom' ] ) ) {
			$aParts = explode( '-', $params[ 'fDateFrom' ] );
			$ts = Services::Request()->carbon()
						  ->setDate( $aParts[ 0 ], $aParts[ 1 ], $aParts[ 2 ] )
						  ->setTime( 0, 0 )->timestamp;
			$selector->filterByCreatedAt( $ts, '>' );
		}

		if ( !empty( $params[ 'fDateTo' ] ) && preg_match( '#^\d{4}-\d{2}-\d{2}$#', $params[ 'fDateTo' ] ) ) {
			$aParts = explode( '-', $params[ 'fDateTo' ] );
			$ts = Services::Request()->carbon()
						  ->setDate( $aParts[ 0 ], $aParts[ 1 ], $aParts[ 2 ] )
						  ->setTime( 0, 0 )
						  ->addDay()->timestamp;
			$selector->filterByCreatedAt( $ts, '<' );
		}

		// if username is provided, this takes priority over "logged-in" (even if it's invalid)
		if ( !empty( $params[ 'fUsername' ] ) ) {
			$selector->filterByUsername( $params[ 'fUsername' ] );
		}
		elseif ( $params[ 'fLoggedIn' ] >= 0 ) {
			$selector->filterByIsLoggedIn( $params[ 'fLoggedIn' ] );
		}

		$selector->setOrderBy( 'updated_at', 'DESC', true )
				 ->setOrderBy( 'created_at' );

		return $this;
	}

	protected function getCustomParams() :array {
		return [
			'fIp'         => '',
			'fUsername'   => '',
			'fEvent'      => '',
			'fLoggedIn'   => -1,
			'fExcludeYou' => '',
			'fDateFrom'   => '',
			'fDateTo'     => '',
		];
	}

	/**
	 * @return array[]
	 */
	public function getEntriesFormatted() :array {
		$entries = [];

		$srvIP = Services::IP();
		$you = $srvIP->getRequestIp();
		$con = $this->getCon();
		foreach ( $this->getEntriesRaw() as $nKey => $entry ) {
			/** @var Shield\Databases\AuditTrail\EntryVO $entry */

			$msg = 'Audit message could not be retrieved';
			if ( empty( $entry->message ) ) {
				/**
				 * To cater for the contexts that don't refer to a module, but rather a context
				 * with the Audit Trail module
				 */
				$mod = $con->getModule( $entry->context );
				if ( empty( $mod ) ) {
					$mod = $con->getModule_AuditTrail();
				}
				$strings = $mod->getStrings();

				if ( $strings instanceof Shield\Modules\Base\Strings ) {
					$substitutions = $entry->meta;
					$rawString = implode( "\n", $strings->getAuditMessage( $entry->event ) );
					$missingCount = substr_count( $rawString, '%s' ) - count( $substitutions );
					if ( $missingCount > 0 ) {
						$substitutions = array_merge(
							$substitutions,
							array_fill( 0, $missingCount, 'unavailable' )
						);
					}
					$msg = stripslashes( sanitize_textarea_field(
						vsprintf( $rawString, $substitutions )
					) );
				}
			}
			else {
				$msg = $entry->message;
			}

			if ( !isset( $entries[ $entry->rid ] ) ) {
				$aE = $entry->getRawData();
				$aE[ 'meta' ] = $entry->meta;
				$aE[ 'event' ] = str_replace( '_', ' ', sanitize_text_field( $entry->event ) );
				$aE[ 'message' ] = $msg;
				$aE[ 'created_at' ] = $this->formatTimestampField( $entry->created_at );
				if ( $entry->wp_username == '-' ) {
					$aE[ 'wp_username' ] = __( 'Not logged-in', 'wp-simple-firewall' );
				}

				try {
					$aE[ 'is_you' ] = $srvIP->checkIp( $you, $entry->ip );
				}
				catch ( \Exception $e ) {
					$aE[ 'is_you' ] = false;
				}

				if ( empty( $entry->ip ) ) {
					$aE[ 'ip' ] = '';
				}
				else {
					$aE[ 'ip' ] = sprintf( '%s%s',
						$this->getIpAnalysisLink( $entry->ip ),
						$aE[ 'is_you' ] ? ' <small>('.__( 'You', 'wp-simple-firewall' ).')</small>' : ''
					);
				}
			}
			else {
				$aE = $entries[ $entry->rid ];
				$aE[ 'message' ] .= "\n".$msg;
				$aE[ 'category' ] = max( $aE[ 'category' ], $entry->category );
			}

			if ( $entry->count > 1 ) {
				$aE[ 'message' ] = $msg."\n"
								   .sprintf( __( 'This event repeated %s times in the last 24hrs.', 'wp-simple-firewall' ), $entry->count );
			}

			$entries[ $entry->rid ] = $aE;
		}
		return $entries;
	}

	/**
	 * @return Shield\Tables\Render\WpListTable\AuditTrail
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\WpListTable\AuditTrail();
	}
}