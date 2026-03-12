<?php
declare(strict_types=1);

namespace OCA\CSISProducts\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'csis_products';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		// no-op
	}

	public function boot(IBootContext $context): void {
		// Match @nextcloud/webpack-vue-config output name: <appId>-<entry>.
		Util::addInitScript(self::APP_ID, self::APP_ID . '-init');
	}
}
