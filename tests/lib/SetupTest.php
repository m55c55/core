<?php
/**
 * Copyright (c) 2014 Lukas Reschke <lukas@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test;

use OCP\IConfig;

class SetupTest extends \Test\TestCase {

	/** @var IConfig | \PHPUnit_Framework_MockObject_MockObject */
	protected $config;
	/** @var \bantu\IniGetWrapper\IniGetWrapper | \PHPUnit_Framework_MockObject_MockObject */
	private $iniWrapper;
	/** @var \OCP\IL10N | \PHPUnit_Framework_MockObject_MockObject */
	private $l10n;
	/** @var \OC_Defaults | \PHPUnit_Framework_MockObject_MockObject */
	private $defaults;
	/** @var \OC\Setup | \PHPUnit_Framework_MockObject_MockObject */
	protected $setupClass;
	/** @var \OCP\ILogger | \PHPUnit_Framework_MockObject_MockObject */
	protected $logger;
	/** @var \OCP\Security\ISecureRandom | \PHPUnit_Framework_MockObject_MockObject */
	protected $random;

	protected function setUp() {
		parent::setUp();

		$this->config = $this->createMock('\OCP\IConfig');
		$this->iniWrapper = $this->createMock('\bantu\IniGetWrapper\IniGetWrapper');
		$this->l10n = $this->createMock('\OCP\IL10N');
		$this->defaults = $this->createMock('\OC_Defaults');
		$this->logger = $this->createMock('\OCP\ILogger');
		$this->random = $this->createMock('\OCP\Security\ISecureRandom');
		$this->setupClass = $this->getMockBuilder('\OC\Setup')
			->setMethods(['IsClassExisting', 'is_callable', 'getAvailableDbDriversForPdo'])
			->setConstructorArgs([$this->config, $this->iniWrapper, $this->l10n, $this->defaults, $this->logger, $this->random])
			->getMock();
	}

	public function testGetSupportedDatabasesWithOneWorking() {
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->will($this->returnValue(
				['sqlite', 'mysql', 'oci']
			));
		$this->setupClass
			->expects($this->once())
			->method('IsClassExisting')
			->will($this->returnValue(true));
		$this->setupClass
			->expects($this->once())
			->method('is_callable')
			->will($this->returnValue(false));
		$this->setupClass
			->expects($this->once())
			->method('getAvailableDbDriversForPdo')
			->will($this->returnValue([]));
		$result = $this->setupClass->getSupportedDatabases();
		$expectedResult = [
			'sqlite' => 'SQLite'
		];

		$this->assertSame($expectedResult, $result);
	}

	public function testGetSupportedDatabasesWithNoWorking() {
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->will($this->returnValue(
				['sqlite', 'mysql', 'oci', 'pgsql']
			));
		$this->setupClass
			->expects($this->once())
			->method('IsClassExisting')
			->will($this->returnValue(false));
		$this->setupClass
			->expects($this->exactly(2))
			->method('is_callable')
			->will($this->returnValue(false));
		$this->setupClass
			->expects($this->once())
			->method('getAvailableDbDriversForPdo')
			->will($this->returnValue([]));
		$result = $this->setupClass->getSupportedDatabases();

		$this->assertSame([], $result);
	}

	public function testGetSupportedDatabasesWithAllWorking() {
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->will($this->returnValue(
				['sqlite', 'mysql', 'pgsql', 'oci']
			));
		$this->setupClass
			->expects($this->once())
			->method('IsClassExisting')
			->will($this->returnValue(true));
		$this->setupClass
			->expects($this->exactly(2))
			->method('is_callable')
			->will($this->returnValue(true));
		$this->setupClass
			->expects($this->once())
			->method('getAvailableDbDriversForPdo')
			->will($this->returnValue(['mysql']));
		$result = $this->setupClass->getSupportedDatabases();
		$expectedResult = [
			'sqlite' => 'SQLite',
			'mysql' => 'MySQL/MariaDB',
			'pgsql' => 'PostgreSQL',
			'oci' => 'Oracle'
		];
		$this->assertSame($expectedResult, $result);
	}

	/**
	 * @expectedException \Exception
	 * @expectedExceptionMessage Supported databases are not properly configured.
	 */
	public function testGetSupportedDatabaseException() {
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->will($this->returnValue('NotAnArray'));
		$this->setupClass->getSupportedDatabases();
	}

	/**
	 * @expectedException \Exception
	 * @expectedExceptionMessage Can't update
	 */
	public function testCannotUpdateHtaccess() {
		if ($this->getCurrentUser() === 'root') {
			$this->markTestSkipped(
				'You are running tests as root - this test will not work in this case.'
			);
		}
		$origServerRoot = \OC::$SERVERROOT;
		$htaccessFile = \OC::$SERVERROOT . '/tests/data/.htaccess';
		\touch($htaccessFile);
		\chmod($htaccessFile, 0400);
		\OC::$SERVERROOT = \OC::$SERVERROOT . '/tests/data';
		try {
			$this->setupClass->updateHtaccess();
		} catch (\Exception $e) {
			throw $e;
		} finally {
			\OC::$SERVERROOT = $origServerRoot;
			@\unlink($htaccessFile);
		}
	}

	/**
	 * @expectedException \Exception
	 * @expectedExceptionMessage Can't update
	 */
	public function testHtaccessIsFolder() {
		$origServerRoot = \OC::$SERVERROOT;
		$htaccessFile = \OC::$SERVERROOT . '/tests/data/.htaccess';
		@\unlink($htaccessFile);
		@\mkdir($htaccessFile);
		
		\OC::$SERVERROOT = \OC::$SERVERROOT . '/tests/data';
		try {
			$this->setupClass->updateHtaccess();
		} catch (\Exception $e) {
			throw $e;
		} finally {
			\OC::$SERVERROOT = $origServerRoot;
			@\rmdir($htaccessFile);
		}
	}

	public function testUpdateHtaccess() {
		$origServerRoot = \OC::$SERVERROOT;
		$htaccessFile = \OC::$SERVERROOT . '/tests/data/.htaccess';
		\touch($htaccessFile);
		\chmod($htaccessFile, 0700);
		\OC::$SERVERROOT = \OC::$SERVERROOT . '/tests/data';
		try {
			$this->setupClass->updateHtaccess();
		} catch (\Exception $e) {
			throw $e;
		} finally {
			\OC::$SERVERROOT = $origServerRoot;
		}
		$content = \file_get_contents($htaccessFile);
		@\unlink($htaccessFile);
		$this->assertContains('#### DO NOT CHANGE ANYTHING ABOVE THIS LINE ####', $content);
	}
}
