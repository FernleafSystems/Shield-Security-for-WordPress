<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class MaintenanceThemeSelection {

	private string $stylesheet;

	private string $template;

	public function __construct(
		string $stylesheet,
		string $template
	) {
		$this->stylesheet = $stylesheet;
		$this->template = $template;
	}

	public function get_stylesheet() :string {
		return $this->stylesheet;
	}

	public function get_template() :string {
		return $this->template !== '' ? $this->template : $this->stylesheet;
	}
}
