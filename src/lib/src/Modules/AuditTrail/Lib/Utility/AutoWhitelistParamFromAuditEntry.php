<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Utility;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class AutoWhitelistParamFromAuditEntry {

	use ModConsumer;

	/**
	 * @param int $entryID
	 * @return string
	 * @throws \Exception
	 */
	public function run( int $entryID ) :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		/** @var Select $selector */
		$selector = $mod->getDbHandler_AuditTrail()->getQuerySelector();

		/** @var EntryVO $entry */
		$entry = $selector->byId( $entryID );
		if ( !$entry instanceof EntryVO ) {
			throw new \Exception( __( 'Audit entry could not be loaded.', 'wp-simple-firewall' ) );
		}

		$uri = '';
		foreach ( $selector->filterByRequestID( (int)$entry->rid )->all() as $entry ) {
			$param = $this->extractParameter( $entry );
			if ( !empty( $param ) ) {
				$uri = $entry->meta[ 'uri' ] ?? '*';
				break;
			}
		}

		if ( empty( $param ) ) {
			throw new \Exception( __( 'Parameter associated with this audit entry could not be found.', 'wp-simple-firewall' ) );
		}

		/** @var Shield\Modules\Firewall\ModCon $modFW */
		$modFW = $this->getCon()->modules[ 'firewall' ];
		$modFW->addParamToWhitelist( $param, $uri );
		return sprintf( __( 'Parameter "%s" whitelisted successfully', 'wp-simple-firewall' ), $param );
	}

	private function extractParameter( EntryVO $entry ) :string {
		return $entry->meta[ 'param' ] ?? '';
	}
}