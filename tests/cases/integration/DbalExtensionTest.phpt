<?php

/**
 * @testCase
 */

namespace NextrasTests\Dbal;

use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nextras\Dbal\Bridges\NetteDI\DbalExtension;
use Nextras\Dbal\Bridges\NetteTracy\ConnectionPanel;
use Nextras\Dbal\Connection;
use Tester\Assert;
use Tracy\Bridges\Nette\TracyExtension;
use Tracy\Debugger;


require_once __DIR__ . '/../../bootstrap.php';


class DbalExtensionTest extends IntegrationTestCase
{

	/**
	 * @dataProvider provideData
	 */
	public function testExtension($config, $debug, $expectTracyPanel)
	{
		$dic = $this->buildDic($config, $debug);

		Assert::type('Nette\DI\Container', $dic);
		Assert::count(1, $dic->findByType(Connection::class));

		/** @var Connection $connection */
		$connection = $dic->getByType(Connection::class);

		$conf = $connection->getConfig();
		Assert::same('mysqli', $conf['driver']);
		Assert::same('bar', $conf['username']);
		Assert::same('foo', $conf['password']);

		if ($expectTracyPanel) {
			Assert::type(
				ConnectionPanel::class,
				Debugger::getBar()->getPanel('Nextras\Dbal\Bridges\NetteTracy\ConnectionPanel')
			);
		}
	}


	private function buildDic($config, $debug, callable $compilerCb = NULL)
	{
		$loader = new ContainerLoader(TEMP_DIR);
		$key = __FILE__ . ':' . __LINE__ . ':' . $config;
		$className = $loader->load($key, function (Compiler $compiler) use ($config, $debug, $compilerCb) {
			if ($debug) {
				Debugger::enable(Debugger::DEVELOPMENT);
			}
			if ($compilerCb) {
				$compilerCb($compiler);
			}
			$compiler->addExtension('tracy', new TracyExtension($debug));
			$compiler->addExtension('dbal', new DbalExtension());
			$compiler->loadConfig(__DIR__ . "/DbalExtensionTest.$config.neon");
		});

		/** @var Container $dic */
		$dic = new $className;
		return $dic;
	}


	public function provideData()
	{
		return [
			['configA', TRUE, TRUE],
			['configB', FALSE, TRUE],
			['configC', FALSE, FALSE],
		];
	}


	public function testAutowired()
	{
		$dic = $this->buildDic('configD', FALSE, function (Compiler $compiler) {
			$compiler->addExtension('dbal2', new DbalExtension());
		});

		Assert::count(2, $dic->findByType(Connection::class));
		$connection = $dic->getByType(Connection::class);
		Assert::type(Connection::class, $connection);
		Assert::equal('bar', $connection->getConfig()['username']);

		$connection = $dic->getService('dbal2.connection');
		Assert::type(Connection::class, $connection);
		Assert::equal('bar2', $connection->getConfig()['username']);
	}
}


$test = new DbalExtensionTest();
$test->run();
