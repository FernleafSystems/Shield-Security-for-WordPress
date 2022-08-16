<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpAnalyse\FindAllPluginIps;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;
use IPLib\Factory;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function getAjaxActionCallbackMap( bool $isAuth ) :array {
		$map = array_merge( parent::getAjaxActionCallbackMap( $isAuth ), [
			'not_bot' => [ $this, 'ajaxExec_CaptureNotBot' ],
		] );
		if ( $isAuth ) {
			$map = array_merge( $map, [
				'iprulestable_action' => [ $this, 'ajaxExec_IpRulesTableAction' ],
				'ip_analyse_build'    => [ $this, 'ajaxExec_BuildIpAnalyse' ],
				'ip_analyse_action'   => [ $this, 'ajaxExec_IpAnalyseAction' ],
				'ip_review_select'    => [ $this, 'ajaxExec_IpReviewSelect' ],
				'render_ip_analysis'  => [ $this, 'ajaxExec_RenderIpAnalysis' ],
				'render_ip_rule_add'  => [ $this, 'ajaxExec_RenderIpRuleAdd' ],
				'ip_rule_add_form'    => [ $this, 'ajaxExec_ProcessIpRuleAdd' ],
				'ip_rule_delete'      => [ $this, 'ajaxExec_ProcessIpRuleDelete' ],
			] );
		}
		return $map;
	}

	public function ajaxExec_CaptureNotBot() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return [
			'success' => $mod->getBotSignalsController()
							 ->getHandlerNotBot()
							 ->registerAsNotBot()
		];
	}

	public function ajaxExec_ProcessIpRuleDelete() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$ID = (int)Services::Request()->post( 'rid', -1 );

		if ( $ID < 0 ) {
			$success = false;
			$msg = __( 'Invalid entry selected', 'wp-simple-firewall' );
		}
		else {
			$success = $mod->getDbH_IPRules()
						   ->getQueryDeleter()
						   ->deleteById( $ID );
			$msg = $success ? __( 'IP Rule deleted', 'wp-simple-firewall' ) : __( "IP Rule couldn't be deleted from the list", 'wp-simple-firewall' );
		}

		return [
			'success'     => $success,
			'page_reload' => $success,
			'message'     => $msg,
		];
	}

	public function ajaxExec_IpReviewSelect() :array {
		$req = Services::Request();

		$filter = preg_replace( '#[^\da-f:.]#', '', strtolower( (string)$req->post( 'search' ) ) );
		$ips = ( new FindAllPluginIps() )
			->setCon( $this->getCon() )
			->run( $filter );

		return [
			'success'     => true,
			'ips'         => array_map( function ( $ip ) {
				return [
					'id'   => $ip,
					'text' => $ip
				];
			}, $ips ),
			'message'     => '',
			'page_reload' => false,
		];
	}

	public function ajaxExec_RenderIpRuleAdd() :array {
		/** @var UI $UI */
		$UI = $this->getMod()->getUIHandler();
		return [
			'success'      => true,
			'title'        => __( "Add New IP Rule", 'wp-simple-firewall' ),
			'body'         => $UI->renderForm_IpAdd(),
			'modal_class'  => ' ',
			'modal_static' => true,
		];
	}

	public function ajaxExec_ProcessIpRuleAdd() :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbH_IPRules();
		$form = Services::Request()->post( 'form_data' );
		try {
			if ( empty( $form ) || !is_array( $form ) ) {
				throw new \Exception( 'No data. Please retry' );
			}
			$label = trim( $form[ 'label' ] ?? '' );
			if ( !empty( $label ) && preg_match( '#[^a-z\d\s_-]#i', $label ) ) {
				throw new \Exception( 'The label must be alphanumeric with no special characters' );
			}
			if ( empty( $form[ 'type' ] ) ) {
				throw new \Exception( 'Please select one of the IP Rule Types - Block or Bypass' );
			}
			if ( ( $form[ 'confirm' ] ?? '' ) !== 'Y' ) {
				throw new \Exception( 'Please check the box to confirm this action' );
			}
			if ( empty( $form[ 'ip' ] ) ) {
				throw new \Exception( 'Please provide an IP Address' );
			}

			$range = Factory::parseRangeString( trim( $form[ 'ip' ] ) );
			$iBypass = ( new Lib\IpRules\IpRuleStatus( $con->this_req->ip ) )
				->setMod( $this->getMod() )
				->isBypass();

			// You can't manually block your own IP if your IP isn't whitelisted.
			if ( $form[ 'type' ] === $dbh::T_MANUAL_BLOCK
				 && !empty( $range )
				 && !$iBypass
				 && Factory::parseAddressString( $con->this_req->ip )->matches( $range ) ) {
				throw new \Exception( "Manually blocking your own IP address isn't supported." );
			}

			$ipAdder = ( new Lib\IpRules\AddRule() )
				->setMod( $mod )
				->setIP( $form[ 'ip' ] );
			switch ( $form[ 'type' ] ) {
				case $dbh::T_MANUAL_BYPASS:
					$IP = $ipAdder->toManualWhitelist( $label );
					break;

				case $dbh::T_MANUAL_BLOCK:
					$IP = $ipAdder->toManualBlacklist( $label );
					break;

				default:
					throw new \Exception( 'Please select one of the IP Rule Types - Block or Bypass' );
			}

			if ( empty( $IP ) ) {
				throw new \Exception( 'There appears to have been a problem adding the IP rule' );
			}

			$msg = __( 'IP address added successfully', 'wp-simple-firewall' );
			$success = true;
		}
		catch ( \Exception $e ) {
			$success = false;
			$msg = $e->getMessage();
		}

		return [
			'success'     => $success,
			'page_reload' => $success,
			'message'     => $msg,
		];
	}

	public function ajaxExec_RenderIpAnalysis() :array {
		try {
			$ip = Services::Request()->post( 'ip', '' );
			$data = [
				'success' => true,
				'title'   => sprintf( '%s: %s', __( 'IP Analysis', 'wp-simple-firewall' ), $ip ),
				'body'    => ( new Shield\Modules\IPs\Lib\IpAnalyse\BuildDisplay() )
					->setMod( $this->getMod() )
					->setIP( $ip )
					->run(),
			];
		}
		catch ( \Exception $e ) {
			$data = [
				'success' => false,
				'title'   => __( "Couldn't Build IP Analysis", 'wp-simple-firewall' ),
				'body'    => $e->getMessage(),
			];
		}
		return $data;
	}

	public function ajaxExec_IpAnalyseAction() :array {
		$req = Services::Request();

		$ip = $req->post( 'ip' );

		try {
			list( $ipKey, $ipName ) = ( new IpID( $ip ) )->run();
			$validIP = true;
		}
		catch ( \Exception $e ) {
			$ipKey = IpID::UNKNOWN;
			$ipName = 'Unknown';
			$validIP = false;
		}

		$success = false;

		if ( !$validIP ) {
			$msg = __( "IP provided was invalid.", 'wp-simple-firewall' );
		}
		else {
			$ruleStatus = ( new Lib\IpRules\IpRuleStatus( $ip ) )->setMod( $this->getMod() );

			switch ( $req->post( 'ip_action' ) ) {

				case 'block':
					try {
						if ( !in_array( $ipKey, [ IpID::UNKNOWN, IpID::VISITOR ] ) ) {
							throw new \Exception( sprintf( __( "IP can't be blocked from this page as it's a known service IP: %s" ), $ipName ) );
						}
						( new Lib\IpRules\AddRule() )
							->setMod( $this->getMod() )
							->setIP( $ip )
							->toManualBlacklist();
						$success = true;
						$msg = __( 'IP address blocked.', 'wp-simple-firewall' );
					}
					catch ( \Exception $e ) {
						$msg = $e->getMessage();
					}
					break;

				case 'unblock':
					foreach ( $ruleStatus->getRulesForShieldBlock() as $record ) {
						$success = ( new Lib\IpRules\DeleteRule() )
									   ->setMod( $this->getMod() )
									   ->byRecord( $record );
					}
					$msg = $success ? __( 'IP address unblocked.', 'wp-simple-firewall' )
						: __( "IP address couldn't be unblocked at this time.", 'wp-simple-firewall' );
					break;

				case 'bypass':
					try {
						( new Lib\IpRules\AddRule() )
							->setMod( $this->getMod() )
							->setIP( $ip )
							->toManualWhitelist();
						$success = true;
						$msg = __( 'IP address added to Bypass list.', 'wp-simple-firewall' );
					}
					catch ( \Exception $e ) {
						$msg = $e->getMessage();
					}
					break;

				case 'unbypass':
					foreach ( $ruleStatus->getRulesForBypass() as $record ) {
						$success = ( new Lib\IpRules\DeleteRule() )
									   ->setMod( $this->getMod() )
									   ->byRecord( $record );
					}
					$msg = $success ? __( 'IP address removed from Bypass list.', 'wp-simple-firewall' )
						: __( "IP address couldn't be removed from Bypass list at this time.", 'wp-simple-firewall' );
					break;

				case 'delete_notbot':
					foreach ( $ruleStatus->getRulesForShieldBlock() as $record ) {
						( new Lib\IpRules\DeleteRule() )
							->setMod( $this->getMod() )
							->byRecord( $record );
					}
					$success = ( new Lib\Bots\BotSignalsRecord() )
						->setMod( $this->getMod() )
						->setIP( $ip )
						->delete();
					$msg = $success ? __( 'IP NotBot Score Reset.', 'wp-simple-firewall' )
						: __( "IP NotBot Score couldn't be reset at this time.", 'wp-simple-firewall' );
					break;

				default:
					$msg = __( 'Unsupported Action.', 'wp-simple-firewall' );
					break;
			}
		}

		return [
			'success'     => $success,
			'message'     => $msg,
			'page_reload' => true,
		];
	}

	public function ajaxExec_BuildIpAnalyse() :array {
		try {
			$ip = Services::Request()->post( 'fIp', '' );
			$response = ( new Shield\Modules\IPs\Lib\IpAnalyse\BuildDisplay() )
				->setMod( $this->getMod() )
				->setIP( $ip )
				->run();

			$msg = '';
			$success = true;
		}
		catch ( \Exception $e ) {
			$msg = $e->getMessage();
			$success = false;
			$response = $msg;
		}

		return [
			'success' => $success,
			'message' => $msg,
			'html'    => $response,
		];
	}

	public function ajaxExec_IpRulesTableAction() :array {
		try {
			$action = Services::Request()->post( 'sub_action' );
			switch ( $action ) {

				case 'retrieve_table_data':
					$builder = ( new Lib\Table\BuildIpRulesTableData() )->setMod( $this->getMod() );
					$builder->table_data = Services::Request()->post( 'table_data', [] );
					$response = [
						'success'        => true,
						'datatable_data' => $builder->build(),
					];
					break;

				default:
					throw new \Exception( 'Not a supported Activity Log table sub_action: '.$action );
			}
		}
		catch ( \Exception $e ) {
			$response = [
				'success'     => false,
				'page_reload' => true,
				'message'     => $e->getMessage(),
			];
		}

		return $response;
	}
}