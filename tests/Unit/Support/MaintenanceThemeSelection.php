<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class MaintenanceThemeSelection {

	public function __construct(
		private string $stylesheet,
		private string $template
	) {
	}

	public function get_stylesheet() :string {
		return $this->stylesheet;
	}

	public function get_template() :string {
		return $this->template !== '' ? $this->template : $this->stylesheet;
	}
}
