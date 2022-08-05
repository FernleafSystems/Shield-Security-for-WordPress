<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BuildSearchPanesData {

	use ModConsumer;

	public function build() :array {
		return [
			'options' => [
				'type' => $this->buildForIpType(),
				'ip' => $this->buildForIp(),
				//				'status'    => $this->buildForFileStatus(),
			]
		];
	}

	private function buildForIpType() :array {
		return [
			[
				'label' => 'Bypass / Whitelist',
				'value' => 'bypass',
			],
			[
				'label' => 'Block / Blacklist',
				'value' => 'block',
			],
			[
				'label' => 'CrowdSec',
				'value' => 'crowdsec',
			],
		];
	}

	private function buildForIp() :array {
		return [
			[
				'label' => '1.1.1.1',
				'value' => 'bypass',
			],
			[
				'label' => 'Block / Blacklist',
				'value' => 'block',
			],
			[
				'label' => 'CrowdSec',
				'value' => 'crowdsec',
			],
		];
	}

	private function runQueryForFileTypes() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$results = Services::WpDb()->selectCustom(
			sprintf( "SELECT DISTINCT `ri`.`item_id`
						FROM `%s` as `ri`
						WHERE `ri`.`item_type`='f'
							AND `ri`.`ignored_at`=0
							AND `ri`.`auto_filtered_at`!=0
							AND `ri`.`item_repaired_at`=0
							AND `ri`.`item_deleted_at`=0
							AND `ri`.`deleted_at`=0
				",
				$mod->getDbH_ResultItems()->getTableSchema()->table
			)
		);
		return is_array( $results ) ? $results : [];
	}
}