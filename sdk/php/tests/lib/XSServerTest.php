<?php
require_once dirname(__FILE__) . '/../../lib/XSServer.class.php';

/**
 * Test class for XSServer
 * Generated by PHPUnit on 2011-09-15 at 19:29:49.
 */
class XSServerTest extends PHPUnit_Framework_TestCase
{
	/**
	 * @var XSServer
	 */
	protected $object;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp()
	{
		$this->object = new XSServer('8384');
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 */
	protected function tearDown()
	{
		$this->object = null;
	}

	public function testOpenClose()
	{
		$this->object->open(8383);
		$this->object->open('localhost:8384');
		$this->object->open(ini_get('pdo_mysql.default_socket'));
		$this->object->close();
	}

	public function testOpenFile()
	{
		$file = '/tmp/xs_test.dat';
		file_exists($file) && unlink($file);
		clearstatcache();
		$this->assertFalse(file_exists($file));

		$this->object->open('file:///tmp/xs_test.dat');
		$this->assertTrue(file_exists($file));

		clearstatcache();
		$this->assertEquals(0, filesize($file));
		$this->object->sendCommand(XS_CMD_NONE);

		clearstatcache();
		$this->assertEquals(8, filesize($file));

		clearstatcache();
		$this->assertTrue($this->object->execCommand(XS_CMD_DOC_INDEX));
		$this->assertEquals(8, filesize($file));

		clearstatcache();
		$this->assertTrue($this->object->execCommand(XS_CMD_DEBUG));
		$this->assertEquals(24, filesize($file));

		clearstatcache();
		$this->assertTrue($this->object->execCommand(XS_CMD_DOC_TERM));
		$this->assertEquals(24, filesize($file));

		clearstatcache();
		$this->object->close();
		$this->assertEquals(32, filesize($file));

		$this->object->reopen();
		clearstatcache();
		$this->assertEquals(0, filesize($file));
		$this->object->close();

		unlink($file);
	}

	public function testOthers()
	{
		$this->assertNull($this->object->project);
		$this->object->setProject('demo');
		$this->assertEquals('demo', $this->object->getProject());
		$this->object->setTimeout(0);

		$this->assertFalse($this->object->hasRespond());
		$this->object->sendCommand(XS_CMD_OK);
		$this->assertFalse($this->object->hasRespond());

		$this->object->sendCommand(XS_CMD_SEARCH_GET_DB);
		usleep(50000);
		$this->assertTrue($this->object->hasRespond());
		$res = $this->object->respond;
		$this->assertEquals(XS_CMD_OK_DB_INFO, $res->arg);
		$this->assertEquals('Database()', $res->buf);

		// read timeout		
		try {
			stream_set_timeout($this->object->socket, 2);
			$this->object->getRespond();
		} catch (XSException $e) {
			
		}
		$this->assertInstanceOf('XSException', $e);
		$this->assertRegExp('/timeout/', $e->getMessage());

		// send cmd
		$this->object->reopen();
		$this->object->project = 'demo';
		$cmd = new XSCommand(array('cmd' => XS_CMD_QUERY_GET_STRING, 'buf' => 'hello'));
		$res = $this->object->execCommand($cmd);
		$this->assertEquals(XS_CMD_OK_QUERY_STRING, $res->arg);
		$this->assertEquals('Xapian::Query(Zhello:(pos=1))', $res->buf);

		// test unimp cmd
		try {
			$e = null;
			$this->object->execCommand(array('cmd' => XS_CMD_INDEX_SUBMIT));
		} catch (XSException $e) {
			
		}
		$this->assertInstanceOf('XSException', $e);
		$this->assertEquals('Command not implemented', $e->getMessage());

		// test io closed
		try {
			$e = null;
			$err = error_reporting(0);
			fclose($this->object->socket);
			$this->object->sendCommand(XS_CMD_INDEX_SUBMIT);
		} catch (XSException $e) {
			
		}
		$this->assertInstanceOf('XSException', $e);
		$this->assertRegExp('/unknown/', $e->getMessage());
		error_reporting($err);
	}
}
