<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\LoadSessions;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use FernleafSystems\Wordpress\Services\Services;

class Sessions {

	use PluginControllerConsumer;

	/**
	 * @var array
	 */
	protected $params;

	private $sessionsLoader;

	public function countTotal() :int {
		return $this->getSessionsLoader()->count();
	}

	private function getSessionsLoader() :LoadSessions {
		if ( !isset( $this->sessionsLoader ) ) {
			$params = $this->getParams();

			$userID = null;
			if ( !empty( $params[ 'fUsername' ] ) ) {
				$user = Services::WpUsers()->getUserByUsername( $params[ 'fUsername' ] );
				if ( !empty( $user ) ) {
					$userID = $user->ID;
				}
			}

			$this->sessionsLoader = new LoadSessions( $userID );
		}
		return $this->sessionsLoader;
	}

	private function loadSessions() :array {
		$params = $this->getParams();
		$page = ( $params[ 'paged' ] ?? 1 ) - 1;
		$length = $params[ 'per_page' ] ?? 25;
		return \array_slice(
			$this->getSessionsLoader()->allOrderedByLastActivityAt(),
			$page*$length,
			(int)$length
		);
	}

	/**
	 * @return array[]
	 */
	protected function getEntriesRaw() :array {
		$WPU = Services::WpUsers();
		$allSessions = [];
		foreach ( $this->loadSessions() as $session ) {
			$session[ 'last_activity_at' ] = $session[ 'shield' ][ 'last_activity_at' ] ?? $session[ 'login' ];
			$session[ 'secadmin_at' ] = $session[ 'shield' ][ 'secadmin_at' ] ?? 0;
			$session[ 'user_id' ] = $session[ 'shield' ][ 'user_id' ];
			$session[ 'wp_username' ] = $WPU->getUserById( $session[ 'shield' ][ 'user_id' ] )->user_login;
			$allSessions[] = $session;
		}
		return $allSessions;
	}

	protected function getCustomParams() :array {
		return [
			'fIp'       => '',
			'fUsername' => '',
		];
	}

	/**
	 * @return array[]
	 */
	public function getEntriesFormatted() :array {
		$entries = [];

		$srvIP = Services::IP();
		$WPU = Services::WpUsers();
		$you = self::con()->this_req->ip;
		foreach ( $this->getEntriesRaw() as $key => $e ) {

			try {
				$isYou = $srvIP::IpIn( $you, [ $e[ 'ip' ] ] );
			}
			catch ( \Exception $ex ) {
				$isYou = false;
			}

			$entries[ $key ] = \array_merge( $e, [
				'is_secadmin'      => $e[ 'secadmin_at' ] ? __( 'Yes' ) : __( 'No' ),
				'last_activity_at' => $this->formatTimestampField( (int)$e[ 'last_activity_at' ] ),
				'logged_in_at'     => $this->formatTimestampField( (int)$e[ 'login' ] ),
				'ip'               => sprintf( '%s <small>%s</small>',
					$this->getIpAnalysisLink( $e[ 'ip' ] ),
					$isYou ? __( 'You', 'wp-simple-firewall' ) : ''
				),
				'is_you'           => $isYou,
				'wp_username'      => sprintf( '<a href="%s">%s</a>',
					$WPU->getAdminUrl_ProfileEdit( $e[ 'user_id' ] ),
					$e[ 'wp_username' ]
				),
			] );
		}
		return $entries;
	}

	protected function getIpAnalysisLink( string $ip ) :string {
		$srvIP = Services::IP();
		$href = $srvIP->isValidIpRange( $ip ) ? $srvIP->getIpWhoisLookup( $ip ) : self::con()->plugin_urls->ipAnalysis( $ip );
		return sprintf(
			'<a href="%s" %s title="%s" class="ip-whois %s" data-ip="%s">%s</a>',
			$href,
			$srvIP->isValidIpRange( $ip ) ? 'target="_blank"' : '',
			__( 'IP Analysis' ),
			$srvIP->isValidIpRange( $ip ) ? '' : 'render_ip_analysis',
			$ip,
			$ip
		);
	}

	public function render() :string {
		if ( $this->countTotal() > 0 ) {
			$table = ( new Tables\Render\WpListTable\Sessions() )
				->setItemEntries( $this->getEntriesFormatted() )
				->setPerPage( $this->getParams()[ 'limit' ] )
				->setTotalRecords( $this->countTotal() )
				->prepare_items();
			\ob_start();
			$table->display();
			$render = \ob_get_clean();
		}
		else {
			$render = $this->buildEmpty();
		}

		return $render;
	}

	protected function buildEmpty() :string {
		return sprintf( '<div class="alert alert-success m-0">%s</div>',
			__( "No entries to display.", 'wp-simple-firewall' ) );
	}

	protected function formatTimestampField( int $ts ) :string {
		return Services::Request()
					   ->carbon()
					   ->setTimestamp( $ts )
					   ->diffForHumans()
			   .'<br/><span class="timestamp-small">'
			   .Services::WpGeneral()->getTimeStringForDisplay( $ts ).'</span>';
	}

	protected function getParams() :array {
		if ( empty( $this->params ) ) {
			$this->params = \array_merge( $this->getParamDefaults(), \array_merge( $_POST, $this->getFormParams() ) );
		}
		return $this->params;
	}

	private function getFormParams() :array {
		\parse_str( Services::Request()->post( 'form_params', '' ), $formParams );
		return Services::DataManipulation()->arrayMapRecursive( $formParams, '\trim' );
	}

	protected function getParamDefaults() :array {
		return \array_merge(
			[
				'paged'   => 1,
				'order'   => 'DESC',
				'orderby' => 'created_at',
				'limit'   => 25,
			],
			$this->getCustomParams()
		);
	}
}