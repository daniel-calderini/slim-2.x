<?php
/**
 * Slim - a micro PHP 5 framework
 *
 * @author      Josh Lockhart <info@joshlockhart.com>
 * @copyright   2011-2017 Josh Lockhart
 * @link        http://www.slimframework.com
 * @license     http://www.slimframework.com/license
 * @version     2.6.4
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

//Mock custom view
class CustomView extends \Slim\View
{
    public function render($template, $data = null) { echo "Custom view"; }
}

//Echo Logger
class EchoErrorLogger
{
   public function error($object) { echo get_class($object) .':'.$object->getMessage(); }
}

//Mock extending class
class Derived extends \Slim\Slim
{
	public static function getDefaultSettings()
	{
        return array_merge(
            array("late-static-binding" => true)
        , parent::getDefaultSettings());
	}
}

//Mock middleware
class CustomMiddleware extends \Slim\Middleware
{
    public function call()
    {
        $env = $this->app->environment();
        $res = $this->app->response();
        $env['slim.test'] = 'Hello';
        $this->next->call();
        $res->header('X-Slim-Test', 'Hello');
        $res->write('Hello');
    }
}

class SlimTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        //Remove environment mode if set
        unset($_ENV['SLIM_MODE']);

        //Reset session
        $_SESSION = array();

        //Prepare default environment variables
        \Slim\Environment::mock(array(
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
            'QUERY_STRING' => 'one=foo&two=bar',
            'SERVER_NAME' => 'slimframework.com',
        ));
    }

    /************************************************
     * INSTANTIATION
     ************************************************/

    /**
     * Test version constant is string
     */
    public function testHasVersionConstant(): void
    {
        $this->assertTrue(is_string(\Slim\Slim::VERSION));
    }

    /**
     * Test default instance properties
     */
    public function testDefaultInstanceProperties(): void
    {
        $s = new \Slim\Slim();
        $this->assertInstanceOf('\Slim\Http\Request', $s->request());
        $this->assertInstanceOf('\Slim\Http\Response', $s->response());
        $this->assertInstanceOf('\Slim\Router', $s->router());
        $this->assertInstanceOf('\Slim\View', $s->view());
        $this->assertInstanceOf('\Slim\Log', $s->getLog());
        $this->assertEquals(\Slim\Log::DEBUG, $s->getLog()->getLevel());
        $this->assertTrue($s->getLog()->getEnabled());
        $this->assertInstanceOf('\Slim\Environment', $s->environment());
    }

    /**
     * Test get default instance
     */
    public function testGetDefaultInstance(): void
    {
        $s = new \Slim\Slim();
        $s->setName('default'); //We must do this manually since a default app is already set in prev tests
        $this->assertEquals('default', $s->getName());
        $this->assertInstanceOf('\Slim\Slim', \Slim\Slim::getInstance());
        $this->assertSame($s, \Slim\Slim::getInstance());
    }

    /**
     * Test get named instance
     */
    public function testGetNamedInstance(): void
    {
        $s = new \Slim\Slim();
        $s->setName('foo');
        $this->assertSame($s, \Slim\Slim::getInstance('foo'));
    }

    /**
     * Test Slim autoloader ignores non-Slim classes
     *
     * Pre-conditions:
     * Instantiate a non-Slim class;
     *
     * Post-conditions:
     * Slim autoloader returns without requiring a class file;
     */
    public function testSlimAutoloaderIgnoresNonSlimClass(): void
    {
        $foo = new Foo();

        $this->addToAssertionCount(1);
    }

    /************************************************
     * SETTINGS
     ************************************************/

    /**
     * Test get setting that exists
     */
    public function testGetSettingThatExists(): void
    {
        $s = new \Slim\Slim();
        $this->assertEquals('./templates', $s->config('templates.path'));
    }

    /**
     * Test get setting that does not exist
     */
    public function testGetSettingThatDoesNotExist(): void
    {
        $s = new \Slim\Slim();
        $this->assertNull($s->config('foo'));
    }

    /**
     * Test set setting
     */
    public function testSetSetting(): void
    {
        $s = new \Slim\Slim();
        $this->assertEquals('./templates', $s->config('templates.path'));
        $s->config('templates.path', './tmpl');
        $this->assertEquals('./tmpl', $s->config('templates.path'));
    }

    /**
     * Test batch set settings
     */
    public function testBatchSetSettings(): void
    {
        $s = new \Slim\Slim();
        $this->assertEquals('./templates', $s->config('templates.path'));
        $this->assertTrue($s->config('debug'));
        $s->config(array(
            'templates.path' => './tmpl',
            'debug' => false
        ));
        $this->assertEquals('./tmpl', $s->config('templates.path'));
        $this->assertFalse($s->config('debug'));
    }

    /**
     * Test set settings recursively
     */
    public function testSetSettingsRecursively(): void
    {
        $config = array(
            'my_module' => array(
                'paths'  => array(
                    './my_module/path/1',
                ),
            )
        );

        $s = new \Slim\Slim($config);

        $override = array(
            'my_module' => array(
                'paths'  => array(
                    './my_module/path/2',
                    './my_module/path/3',
                ),
            )
        );

        // Test recursive batch behaviour
        $s->config($override, true);

        $expected =  array(
            'paths'  => array(
                './my_module/path/1',
                './my_module/path/2',
                './my_module/path/3',
            ),
        );

        $this->assertEquals($expected, $s->config('my_module'));

        // Test default batch behaviour
        $s = new \Slim\Slim($config);
        $s->config($override);

        $this->assertNotEquals($expected, $s->config('my_module'));
    }

    /************************************************
     * MODES
     ************************************************/

    /**
     * Test default mode
     */
    public function testGetDefaultMode(): void
    {
        $s = new \Slim\Slim();
        $this->assertEquals('development', $s->getMode());
    }

    /**
     * Test custom mode from environment
     */
    public function testGetModeFromEnvironment(): void
    {
        $_ENV['SLIM_MODE'] = 'production';
        $s = new \Slim\Slim();
        $this->assertEquals('production', $s->getMode());
    }

    /**
     * Test custom mode from app settings
     */
    public function testGetModeFromSettings(): void
    {
        $s = new \Slim\Slim(array(
            'mode' => 'test'
        ));
        $this->assertEquals('test', $s->getMode());
        }

    /**
     * Test mode configuration
     */
    public function testModeConfiguration(): void
    {
        $flag = 0;
        $configureTest = function () use (&$flag) {
            $flag = 'test';
        };
        $configureProduction = function () use (&$flag) {
            $flag = 'production';
        };
        $s = new \Slim\Slim(array('mode' => 'test'));
        $s->configureMode('test', $configureTest);
        $s->configureMode('production', $configureProduction);
        $this->assertEquals('test', $flag);
    }

    /**
     * Test mode configuration when mode does not match
     */
    public function testModeConfigurationWhenModeDoesNotMatch(): void
    {
        $flag = 0;
        $configureTest = function () use (&$flag) {
            $flag = 'test';
        };
        $s = new \Slim\Slim(array('mode' => 'production'));
        $s->configureMode('test', $configureTest);
        $this->assertEquals(0, $flag);
    }

    /**
     * Test mode configuration when not callable
     */
    public function testModeConfigurationWhenNotCallable(): void
    {
        $flag = 0;
        $s = new \Slim\Slim(array('mode' => 'production'));
        $s->configureMode('production', 'foo');
        $this->assertEquals(0, $flag);
    }

    /**
     * Test custom mode from getenv()
     */
    public function testGetModeFromGetEnv(): void
    {
        putenv('SLIM_MODE=production');
        $s = new \Slim\Slim();
        $this->assertEquals('production', $s->getMode());
    }

    /************************************************
     * ROUTING
     ************************************************/

    /**
     * Test GENERIC route
     */
    public function testGenericRoute(): void
    {
        $s = new \Slim\Slim();
        $callable = function () { echo "foo"; };
        $route = $s->map('/bar', $callable);
        $this->assertInstanceOf('\Slim\Route', $route);
        $this->assertEmpty($route->getHttpMethods());
    }

    /**
     * Test GET routes also get mapped as a HEAD route
     */
    public function testGetRouteIsAlsoMappedAsHead(): void
    {
        $s = new \Slim\Slim();
        $route = $s->get('/foo', function () {});
        $this->assertTrue($route->supportsHttpMethod(\Slim\Http\Request::METHOD_GET));
        $this->assertTrue($route->supportsHttpMethod(\Slim\Http\Request::METHOD_HEAD));
    }

    /**
     * Test GET route
     */
    public function testGetRoute(): void
    {
        $s = new \Slim\Slim();
        $mw1 = function () { echo "foo"; };
        $mw2 = function () { echo "bar"; };
        $callable = function () { echo "xyz"; };
        $route = $s->get('/bar', $mw1, $mw2, $callable);
        $s->call();
        $this->assertEquals('foobarxyz', $s->response()->body());
        $this->assertEquals('/bar', $route->getPattern());
        $this->assertSame($callable, $route->getCallable());
    }

    /**
     * Test POST route
     */
    public function testPostRoute(): void
    {
        \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
        ));
        $s = new \Slim\Slim();
        $mw1 = function () { echo "foo"; };
        $mw2 = function () { echo "bar"; };
        $callable = function () { echo "xyz"; };
        $route = $s->post('/bar', $mw1, $mw2, $callable);
        $s->call();
        $this->assertEquals('foobarxyz', $s->response()->body());
        $this->assertEquals('/bar', $route->getPattern());
        $this->assertSame($callable, $route->getCallable());
    }

    /**
     * Test PUT route
     */
    public function testPutRoute(): void
    {
        \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'PUT',
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
        ));
        $s = new \Slim\Slim();
        $mw1 = function () { echo "foo"; };
        $mw2 = function () { echo "bar"; };
        $callable = function () { echo "xyz"; };
        $route = $s->put('/bar', $mw1, $mw2, $callable);
        $s->call();
        $this->assertEquals('foobarxyz', $s->response()->body());
        $this->assertEquals('/bar', $route->getPattern());
        $this->assertSame($callable, $route->getCallable());
    }

    /**
     * Test PATCH route
     */
    public function testPatchRoute(): void
    {
        \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'PATCH',
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
        ));
        $s = new \Slim\Slim();
        $mw1 = function () { echo "foo"; };
        $mw2 = function () { echo "bar"; };
        $callable = function () { echo "xyz"; };
        $route = $s->patch('/bar', $mw1, $mw2, $callable);
        $s->call();
        $this->assertEquals('foobarxyz', $s->response()->body());
        $this->assertEquals('/bar', $route->getPattern());
        $this->assertSame($callable, $route->getCallable());
    }

    /**
     * Test DELETE route
     */
    public function testDeleteRoute(): void
    {
        \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'DELETE',
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
        ));
        $s = new \Slim\Slim();
        $mw1 = function () { echo "foo"; };
        $mw2 = function () { echo "bar"; };
        $callable = function () { echo "xyz"; };
        $route = $s->delete('/bar', $mw1, $mw2, $callable);
        $s->call();
        $this->assertEquals('foobarxyz', $s->response()->body());
        $this->assertEquals('/bar', $route->getPattern());
        $this->assertSame($callable, $route->getCallable());
    }

    /**
     * Test OPTIONS route
     */
    public function testOptionsRoute(): void
    {
        \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'OPTIONS',
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
        ));
        $s = new \Slim\Slim();
        $mw1 = function () { echo "foo"; };
        $mw2 = function () { echo "bar"; };
        $callable = function () { echo "xyz"; };
        $route = $s->options('/bar', $mw1, $mw2, $callable);
        $s->call();
        $this->assertEquals('foobarxyz', $s->response()->body());
        $this->assertEquals('/bar', $route->getPattern());
        $this->assertSame($callable, $route->getCallable());
    }

    /**
    * Test route groups
    */
    public function testRouteGroups(): void
    {
        \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar/baz', //<-- Virtual'
        ));
        $s = new \Slim\Slim();
        $mw1 = function () { echo "foo"; };
        $mw2 = function () { echo "bar"; };
        $callable = function () { echo "xyz"; };
        $s->group('/bar', $mw1, function () use ($s, $mw2, $callable) {
            $s->get('/baz', $mw2, $callable);
        });
        $s->call();
        $this->assertEquals('foobarxyz', $s->response()->body());
    }

    /*
     * Test ANY route
     */
    public function testAnyRoute(): void
    {
        $mw1 = function () { echo "foo"; };
        $mw2 = function () { echo "bar"; };
        $callable = function () { echo "xyz"; };
        $methods = array('GET', 'POST', 'PUT', 'DELETE', 'OPTIONS');
        foreach ($methods as $i => $method) {
            \Slim\Environment::mock(array(
                'REQUEST_METHOD' => $method,
                'SCRIPT_NAME' => '/foo', //<-- Physical
                'PATH_INFO' => '/bar', //<-- Virtual
            ));
            $s = new \Slim\Slim();
            $route = $s->any('/bar', $mw1, $mw2, $callable);
            $s->call();
            $this->assertEquals('foobarxyz', $s->response()->body());
            $this->assertEquals('/bar', $route->getPattern());
            $this->assertSame($callable, $route->getCallable());
        }
    }

    /**
     * Test if route does NOT expect trailing slash and URL has one
     */
    public function testRouteWithoutSlashAndUrlWithOne(): void
    {
        \Slim\Environment::mock(array(
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar/', //<-- Virtual
        ));
        $s = new \Slim\Slim();
        $s->get('/bar', function () { echo "xyz"; });
        $s->call();
        $this->assertEquals(404, $s->response()->status());
    }

    /**
     * Tests if route will match in case-insensitive manner if configured to do so
     */
    public function testRouteMatchesInCaseInsensitiveMannerIfConfigured(): void
    {
        \Slim\Environment::mock(array(
            'PATH_INFO' => '/BaR', // Does not match route case
        ));
        $s = new \Slim\Slim(array('routes.case_sensitive' => false));
        $route = $s->get('/bar', function () { echo "xyz"; });
        $s->call();
        $this->assertEquals(200, $s->response()->status());
        $this->assertEquals('xyz', $s->response()->body());
        $this->assertEquals('/bar', $route->getPattern());
    }

    /**
     * Test if route contains URL encoded characters
     */
    public function testRouteWithUrlEncodedCharacters(): void
    {
        \Slim\Environment::mock(array(
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar/jo%20hn/smi%20th', //<-- Virtual
        ));
        $s = new \Slim\Slim();
        $s->get('/bar/:one/:two', function ($one, $two) { echo $one . $two; });
        $s->call();
        $this->assertEquals('jo hnsmi th', $s->response()->body());
    }

    /************************************************
     * VIEW
     ************************************************/

    /**
     * Test set view with string class name
     */
    public function testSetSlimViewFromString(): void
    {
        $s = new \Slim\Slim();
        $this->assertInstanceOf('\Slim\View', $s->view());
        $s->view('CustomView');
        $this->assertInstanceOf('CustomView', $s->view());
    }

    /**
     * Test set view with object instance
     */
    public function testSetSlimViewFromInstance(): void
    {
        $s = new \Slim\Slim();
        $this->assertInstanceOf('\Slim\View', $s->view());
        $s->view(new CustomView());
        $this->assertInstanceOf('CustomView', $s->view());
    }

    /**
     * Test view data is transferred to newer view
     */
    public function testViewDataTransfer(): void
    {
        $data = array('foo' => 'bar');
        $s = new \Slim\Slim();
        $s->view()->setData($data);
        $s->view('CustomView');
        $this->assertSame($data, $s->view()->getData());
    }

    /************************************************
     * RENDERING
     ************************************************/

    /**
     * Test template path is passed to view
     */
    public function testViewGetsTemplatesPath(): void
    {
        $path = dirname(__FILE__) . '/templates';
        $s = new \Slim\Slim(array('templates.path' => $path));
        $this->assertEquals($s->view->getTemplatesDirectory(), $path);
    }

    /**
     * Test render with template and data
     */
    public function testRenderTemplateWithData(): void
    {
        $s = new \Slim\Slim(array('templates.path' => dirname(__FILE__) . '/templates'));
        $s->get('/bar', function () use ($s) {
            $s->render('test.php', array('foo' => 'bar'));
        });
        $s->call();
        list($status, $header, $body) = $s->response()->finalize();
        $this->assertEquals(200, $status);
        $this->assertEquals('test output bar', $body);
    }

    /**
     * Test render with template and data and status
     */
    public function testRenderTemplateWithDataAndStatus(): void
    {
        $s = new \Slim\Slim(array('templates.path' => dirname(__FILE__) . '/templates'));
        $s->get('/bar', function () use ($s) {
            $s->render('test.php', array('foo' => 'bar'), 500);
        });
        $s->call();
        list($status, $header, $body) = $s->response()->finalize();
        $this->assertEquals(500, $status);
        $this->assertEquals('test output bar', $body);
    }

    /************************************************
     * LOG
     ************************************************/

    /**
     * Test get log
     *
     * This asserts that a Slim app has a default Log
     * upon instantiation. The Log itself is tested
     * separately in another file.
     */
    public function testGetLog(): void
    {
        $s = new \Slim\Slim();
        $this->assertInstanceOf('\Slim\Log', $s->getLog());
    }

    /************************************************
     * HTTP CACHING
     ************************************************/

    /**
     * Test Last-Modified match
     */
    public function testLastModifiedMatch(): void
    {
        \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
            'HTTP_IF_MODIFIED_SINCE' => 'Sun, 03 Oct 2010 21:00:52 GMT',
        ));
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            $s->lastModified(1286139652);
        });
        $s->call();
        $this->assertEquals(304, $s->response()->status());
    }

    /**
     * Test Last-Modified match
     */
    public function testLastModifiedDoesNotMatch(): void
    {
        \Slim\Environment::mock(array(
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
            'IF_MODIFIED_SINCE' => 'Sun, 03 Oct 2010 21:00:52 GMT',
        ));
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            $s->lastModified(1286139250);
        });
        $s->call();
        $this->assertEquals(200, $s->response()->status());
    }

    public function testLastModifiedOnlyAcceptsIntegers(): void
    {
        self::expectException(\InvalidArgumentException::class);
        \Slim\Environment::mock(array(
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
        ));
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            $s->lastModified('Test');
        });
        $s->call();
    }

    /**
     * Test Last Modified header format
     */
    public function testLastModifiedHeaderFormat(): void
    {
        \Slim\Environment::mock(array(
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
        ));
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            $s->lastModified(1286139652);
        });
        $s->call();
        list($status, $header, $body) = $s->response()->finalize();
        $this->assertTrue(isset($header['Last-Modified']));
        $this->assertEquals('Sun, 03 Oct 2010 21:00:52 GMT', $header['Last-Modified']);
    }

    /**
     * Test ETag matches
     */
    public function testEtagMatches(): void
    {
        \Slim\Environment::mock(array(
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
            'HTTP_IF_NONE_MATCH' => '"abc123"',
        ));
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            $s->etag('abc123');
        });
        $s->call();
        $this->assertEquals(304, $s->response()->status());
    }

    /**
     * Test ETag does not match
     */
    public function testEtagDoesNotMatch(): void
    {
        \Slim\Environment::mock(array(
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
            'IF_NONE_MATCH' => '"abc1234"',
        ));
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            $s->etag('abc123');
        });
        $s->call();
        $this->assertEquals(200, $s->response()->status());
    }

    /**
     * Test ETag with invalid type
     */
    public function testETagWithInvalidType(): void
    {
        self::expectException(\InvalidArgumentException::class);
        \Slim\Environment::mock(array(
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
            'IF_NONE_MATCH' => '"abc1234"',
        ));
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            $s->etag('123','foo');
        });
        $s->call();
    }

    /**
     * Test Expires
     */
    public function testExpiresAsString(): void
    {
        \Slim\Environment::mock(array(
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
        ));
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            $s->expires('5 days');
        });
        $s->call();
        list($status, $header, $body) = $s->response()->finalize();
        $this->assertTrue(isset($header['Expires']));

        $this->assertEquals(
          strtotime('5 days'),
          strtotime($header['Expires']),
          1 // delta
        );
    }

    /**
     * Test Expires
     */
    public function testExpiresAsInteger(): void
    {
        \Slim\Environment::mock(array(
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
        ));
        $fiveDaysFromNow = time() + (60 * 60 * 24 * 5);
        $expectedDate = gmdate('D, d M Y H:i:s T', $fiveDaysFromNow);
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s, $fiveDaysFromNow) {
            $s->expires($fiveDaysFromNow);
        });
        $s->call();
        list($status, $header, $body) = $s->response()->finalize();
        $this->assertTrue(isset($header['Expires']));
        $this->assertEquals($header['Expires'], $expectedDate);
    }

    /************************************************
     * COOKIES
     ************************************************/

    /**
     * Set cookie
     *
     * This tests that the Slim application instance sets
     * a cookie in the HTTP response header. This does NOT
     * test the implementation of setting the cookie; that is
     * tested in a separate file.
     */
    public function testSetCookie(): void
    {
        \Slim\Environment::mock(array(
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
        ));
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            $s->setCookie('foo', 'bar', '2 days');
            $s->setCookie('foo1', 'bar1', '2 days');
        });
        $s->call();
        $cookie1 = $s->response->cookies->get('foo');
        $cookie2 = $s->response->cookies->get('foo1');
        $this->assertEquals(2, count($s->response->cookies));
        $this->assertEquals('bar', $cookie1['value']);
        $this->assertEquals('bar1', $cookie2['value']);
    }

    /**
     * Test get cookie
     *
     * This method ensures that the `Cookie:` HTTP request
     * header is parsed if present, and made accessible via the
     * Request object.
     */
    public function testGetCookie(): void
    {
        \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '127.0.0.1',
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
            'QUERY_STRING' => 'one=foo&two=bar',
            'SERVER_NAME' => 'slimframework.com',
            'SERVER_PORT' => 80,
            'HTTP_COOKIE' => 'foo=bar; foo2=bar2',
            'slim.url_scheme' => 'http',
            'slim.input' => '',
            'slim.errors' => @fopen('php://stderr', 'w')
        ));
        $s = new \Slim\Slim();
        $this->assertEquals('bar', $s->getCookie('foo'));
        $this->assertEquals('bar2', $s->getCookie('foo2'));
    }

    /**
     * Test get cookie when cookie does not exist
     */
    public function testGetCookieThatDoesNotExist(): void
    {
        \Slim\Environment::mock(array(
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
        ));
        $s = new \Slim\Slim();
        $this->assertNull($s->getCookie('foo'));
    }

    /**
     * Test delete cookie
     *
     * This method ensures that the `Set-Cookie:` HTTP response
     * header is set. The implementation of setting the response
     * cookie is tested separately in another file.
     */
    public function testDeleteCookie(): void
    {
        \Slim\Environment::mock(array(
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/bar', //<-- Virtual
            'COOKIE' => 'foo=bar; foo2=bar2',
        ));
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            $s->setCookie('foo', 'bar');
            $s->deleteCookie('foo');
        });
        $s->call();
        $cookie = $s->response->cookies->get('foo');
        $this->assertEquals(1, count($s->response->cookies));
        $this->assertEquals('', $cookie['value']);
        $this->assertLessThan(time(), $cookie['expires']);
    }

    /************************************************
     * HELPERS
     ************************************************/

    /**
     * Test get filesystem path to Slim app root directory
     */
    public function testGetRoot(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = dirname(__FILE__); //<-- No trailing slash
        $s = new \Slim\Slim();
        $this->assertEquals($_SERVER['DOCUMENT_ROOT'] . '/foo/', $s->root()); //<-- Appends physical app path with trailing slash
    }

    /**
     * Test stop
     */
    public function testStop(): void
    {
        self::expectException(\Slim\Exception\Stop::class);
        $s = new \Slim\Slim();
        $s->stop();
    }

    /**
     * Test stop with subsequent output
     */
    public function testStopWithSubsequentOutput(): void
    {
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            echo "Foo"; //<-- Should be in response body!
            $s->stop();
            echo "Bar"; //<-- Should not be in response body!
        });
        $s->call();
        $this->assertEquals('Foo', $s->response()->body());
    }

    /**
     * Test stop with output buffer on and pre content
     */
    public function testStopOutputWithOutputBufferingOnAndPreContent(): void
    {
        $this->expectOutputString('1.2.Foo.3'); //<-- PHP unit uses OB here
        echo "1.";
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            echo "Foo";
            $s->stop();
        });
        echo "2.";
        $s->run();      //<-- Needs to be run to actually echo body
        echo ".3";
    }

    /**
     * Test stop does not leave output buffers open
     */
    public function testStopDoesNotLeaveOutputBuffersOpen(): void
    {
        $level_start = ob_get_level();
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            $s->stop();
        });
        $s->run();
        $this->assertEquals($level_start, ob_get_level());
    }

    /**
     * Test halt
     */
    public function testHalt(): void
    {
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            echo "Foo!"; //<-- Should not be in response body!
            $s->halt(500, 'Something broke');
        });
        $s->call();
        list($status, $header, $body) = $s->response()->finalize();
        $this->assertEquals(500, $status);
        $this->assertEquals('Something broke', $body);
    }

    /**
     * Test halt with output buffering and pre content
     */
    public function testHaltOutputWithOutputBufferingOnAndPreContent(): void
    {
        $this->expectOutputString('1.2.Something broke.3'); //<-- PHP unit uses OB here
        echo "1.";
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            echo "Foo!"; //<-- Should not be in response body!
            $s->halt(500, 'Something broke');
        });
        echo "2.";
        $s->run();
        echo ".3";
    }

    /**
     * Test halt does not leave output buffers open
     */
    public function testHaltDoesNotLeaveOutputBuffersOpen(): void
    {
        $level_start = ob_get_level();
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            $s->halt(500, '');
        });
        $s->run();
        $this->assertEquals($level_start, ob_get_level());
    }

    /**
     * Test pass cleans buffer and throws exception
     */
    public function testPass(): void
    {
        ob_start();
        $s = new \Slim\Slim();
        echo "Foo";
        try {
            $s->pass();
            $this->fail('Did not catch Slim_Exception_Pass');
        } catch ( \Slim\Exception\Pass $e ) {}
        $output = ob_get_clean();
        $this->assertEquals('', $output);
    }

    /**
     * Test pass when there is a subsequent fallback route
     */
    public function testPassWithSubsequentRoute(): void
    {
        \Slim\Environment::mock(array(
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/name/Frank', //<-- Virtual
        ));
        $s = new \Slim\Slim();
        $s->get('/name/Frank', function () use ($s) {
            echo "Fail"; //<-- Should not be in response body!
            $s->pass();
        });
        $s->get('/name/:name', function ($name) {
            echo $name; //<-- Should be in response body!
        });
        $s->call();
        $this->assertEquals('Frank', $s->response()->body());
    }

    /**
     * Test pass when there is not a subsequent fallback route
     */
    public function testPassWithoutSubsequentRoute(): void
    {
        \Slim\Environment::mock(array(
            'SCRIPT_NAME' => '/foo', //<-- Physical
            'PATH_INFO' => '/name/Frank', //<-- Virtual
        ));
        $s = new \Slim\Slim();
        $s->get('/name/Frank', function () use ($s) {
            echo "Fail"; //<-- Should not be in response body!
            $s->pass();
        });
        $s->call();
        $this->assertEquals(404, $s->response()->status());
    }

    /**
     * Test content type
     */
    public function testContentType(): void
    {
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            $s->contentType('application/json');
        });
        $s->call();
        list($status, $header, $body) = $s->response()->finalize();
        $this->assertEquals('application/json', $header['Content-Type']);
    }

    /**
     * Test status
     */
    public function testStatus(): void
    {
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            $s->status(403);
        });
        $s->call();
        $this->assertEquals(403, $s->response()->status());
    }

    /**
     * Test URL for
     */
    public function testSlimUrlFor(): void
    {
        $s = new \Slim\Slim();
        $s->get('/hello/:name', function () {})->name('hello');
        $this->assertEquals('/foo/hello/Josh', $s->urlFor('hello', array('name' => 'Josh'))); //<-- Prepends physical path!
    }

    /**
     * Test redirect sets status and header
     */
    public function testRedirect(): void
    {
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            echo "Foo"; //<-- Should not be in response body!
            $s->redirect('/somewhere/else', 303);
        });
        $s->call();
        list($status, $header, $body) = $s->response()->finalize();
        $this->assertEquals(303, $status);
        $this->assertEquals('/somewhere/else', $header['Location']);
        $this->assertEquals('', $body);
    }

    /************************************************
     * RUNNER
     ************************************************/

    /**
     * Test that runner sends headers and body
     */
    public function testRun(): void
    {
        $this->expectOutputString('Foo');
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            echo "Foo";
        });
        $s->run();
    }

    /**
     * Test runner output with output buffering on and pre content
     */
    public function testRunOutputWithOutputBufferingOnAndPreContent(): void
    {
      $this->expectOutputString('1.2.Foo.3');  //<-- PHP unit uses OB here
      $s = new \Slim\Slim();
      echo "1.";
      $s->get('/bar', function () use ($s) {
          echo "Foo";
      });
      echo "2.";
      $s->run();
      echo ".3";
    }

    /**
     * Test that runner does not leave output buffers open
     */
    public function testRunDoesNotLeaveAnyOutputBuffersOpen(): void
    {
      $level_start = ob_get_level();
      $s = new \Slim\Slim();
      $s->get('/bar', function () use ($s) {});
      $s->run();
      $this->assertEquals($level_start, ob_get_level());
    }

    /************************************************
     * MIDDLEWARE
     ************************************************/

    /**
     * Test add middleware
     *
     * This asserts that middleware are queued and called
     * in sequence. This also asserts that the environment
     * variables are passed by reference.
     */
    public function testAddMiddleware(): void
    {
        $this->expectOutputString('FooHello');
        $s = new \Slim\Slim();
        $s->add(new CustomMiddleware()); //<-- See top of this file for class definition
        $s->get('/bar', function () {
            echo 'Foo';
        });
        $s->run();
        $this->assertEquals('Hello', $s->response()->header('X-Slim-Test'));
    }

    /**
     * Test exception when adding circular middleware queues
     *
     * This asserts that the same middleware can NOT be queued twice (usually by accident).
     * Circular middleware stack causes a troublesome to debug PHP Fatal error:
     *
     * > Fatal error: Maximum function nesting level of '100' reached. aborting!
     */
    public function testFailureWhenAddingCircularMiddleware(): void
    {
        self::expectException(\RuntimeException::class);
        $middleware = new CustomMiddleware;
        $s = new \Slim\Slim;
        $s->add($middleware);
        $s->add(new CustomMiddleware);
        $s->add($middleware);
        $s->run();
    }

    /************************************************
     * FLASH MESSAGING
     ************************************************/

    public function testSetFlashForNextRequest(): void
    {
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            $s->flash('info', 'bar');
        });
        $this->assertFalse(isset($_SESSION['slim.flash']));
        $s->run();
        $this->assertEquals('bar', $_SESSION['slim.flash']['info']);
    }

    public function testSetFlashForCurrentRequest(): void
    {
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            $s->flashNow('info', 'bar');
        });
        $s->run();
        $env = $s->environment();
        $this->assertEquals('bar', $env['slim.flash']['info']);
    }

    public function testKeepFlashForNextRequest(): void
    {
        $_SESSION['slim.flash'] = array('info' => 'Foo');
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            $s->flashKeep();
        });
        $s->run();
        $this->assertEquals('Foo', $_SESSION['slim.flash']['info']);
    }

    public function testFlashData(): void
    {
        $s = new \Slim\Slim();
        $s->get('/bar', function () use ($s) {
            $s->flashNow('info', 'bar');
        });
        $s->run();
        $this->assertEquals(array('info' => 'bar'), $s->flashData());
    }

    /************************************************
     * NOT FOUND HANDLING
     ************************************************/

    /**
     * Test custom Not Found handler
     */
    public function testNotFound(): void
    {
        $s = new \Slim\Slim();
        $s->notFound(function () {
            echo "Not Found";
        });
        $s->get('/foo', function () {});
        $s->call();
        list($status, $header, $body) = $s->response()->finalize();
        $this->assertEquals(404, $status);
        $this->assertEquals('Not Found', $body);
    }

    /************************************************
     * ERROR HANDLING
     ************************************************/

    /**
     * Test default and custom error handlers
     *
     * Pre-conditions:
     * Invoked app route calls default error handler;
     *
     * Post-conditions:
     * Response status code is 500;
     */
    public function testSlimError(): void
    {
        $s = new \Slim\Slim(array(
            "log.enabled" => false
        ));
        $s->get('/bar', function () use ($s) {
            $s->error();
        });
        $s->call();
        $this->assertEquals(500, $s->response()->status());
    }

    /**
     * Test default error handler logs the error when debug is false.
     *
     * Pre-conditions:
     * Invoked app route calls default error handler;
     *
     * Post-conditions:
     * Error log is called
     */
    public function testDefaultHandlerLogsTheErrorWhenDebugIsFalse(): void
    {
        $s = new \Slim\Slim(array('debug' => false));
        $s->container->singleton('log', function ($c) {
            return new EchoErrorLogger();
        });
        $s->get('/bar', function () use ($s) {
            throw new \InvalidArgumentException('my specific error message');
        });

        ob_start();
        $s->run();
        $output = ob_get_clean();
        $this->assertTrue(strpos($output, 'InvalidArgumentException:my specific error message') !== false);
    }

    /**
     * Test triggered errors are converted to ErrorExceptions
     *
     * Pre-conditions:
     * Custom error handler defined;
     * Invoked app route triggers error;
     *
     * Post-conditions:
     * Response status is 500;
     * Response body is equal to triggered error message;
     * Error handler's argument is ErrorException instance;
     */
    public function testTriggeredErrorsAreConvertedToErrorExceptions(): void
    {
        $this->markTestSkipped();

        $s = new \Slim\Slim(array(
            'debug' => false
        ));
        $s->error(function ( $e ) {
            if ($e instanceof \ErrorException) {
                echo $e->getMessage();
            }
        });
        $s->get('/bar', function () {
            trigger_error('Foo I say!');
        });
        $s->call();
        list($status, $header, $body) = $s->response()->finalize();
        $this->assertEquals(500, $status);
        $this->assertEquals('Foo I say!', $body);
    }

    /**
     * Test error triggered with multiple applications
     *
     * Pre-conditions:
     * Multiple Slim apps are instantiated;
     * Both apps are run;
     * One app returns 200 OK;
     * One app triggers an error;
     *
     * Post-conditions:
     * One app returns 200 OK with no Exceptions;
     * One app returns 500 Error;
     * Error triggered does not affect other app;
     */
    public function testErrorWithMultipleApps(): void
    {
        $s1 = new \Slim\Slim(array(
            'debug' => false,
            'log.enabled' => false
        ));
        $s2 = new \Slim\Slim();
        $s1->get('/bar', function () use ($s1) {
            $s1->error();
        });
        $s2->get('/bar', function () {
            echo 'success';
        });
        $s1->call();
        $s2->call();
        $this->assertEquals(500, $s1->response()->status());
        $this->assertEquals(200, $s2->response()->status());
    }

    /**
     * Test custom error handler uses existing Response object
     */
    public function testErrorHandlerUsesCurrentResponseObject(): void
    {
        $s = new \Slim\Slim(array(
            'debug' => false
        ));
        $s->error(function ( \Exception $e ) use ($s) {
            $r = $s->response();
            $r->status(503);
            $r->write('Foo');
            $r['X-Powered-By'] = 'Slim';
            echo 'Bar';
        });
        $s->get('/bar', function () {
            throw new \Exception('Foo');
        });
        $s->call();
        list($status, $header, $body) = $s->response()->finalize();
        $this->assertEquals(503, $status);
        $this->assertEquals('FooBar', $body);
        $this->assertEquals('Slim', $header['X-Powered-By']);
    }

    /**
     * Test custom global error handler
     */
    public function testHandleErrors(): void
    {
        $defaultErrorReporting = error_reporting();

        // Test 1
        error_reporting(E_ALL ^ E_NOTICE); // <-- Report all errors EXCEPT notices
        try {
            \Slim\Slim::handleErrors(E_NOTICE, 'test error', 'Slim.php', 119);
        } catch (\ErrorException $e) {
            $this->fail('Slim::handleErrors reported a disabled error level.');
        }

        // Test 2
        error_reporting(E_ALL | E_STRICT); // <-- Report all errors, including E_STRICT
        try {
            \Slim\Slim::handleErrors(E_STRICT, 'test error', 'Slim.php', 119);
            $this->fail('Slim::handleErrors didn\'t report a enabled error level');
        } catch (\ErrorException $e) {}

        error_reporting($defaultErrorReporting);

        $this->addToAssertionCount(1);
    }

    /**
     * Slim should keep reference to a callable error callback
     */
    public function testErrorHandler(): void {
        $s = new \Slim\Slim();
        $errCallback = function () { echo "404"; };
        $s->error($errCallback);
        $this->assertSame($errCallback, \PHPUnit\Framework\Assert::readAttribute($s, 'error'));
    }

    /**
     * Slim should throw a Slim_Exception_Stop if error callback is not callable
     */
    public function testErrorHandlerIfNotCallable(): void {
        self::expectException(\Slim\Exception\Stop::class);
        $s = new \Slim\Slim(array("log.enabled" => false));
        $errCallback = 'foo';
        $s->error($errCallback);
    }

    /**
     * Slim should keep reference to a callable NotFound callback
     */
    public function testNotFoundHandler(): void {
        $s = new \Slim\Slim();
        $notFoundCallback = function () { echo "404"; };
        $s->notFound($notFoundCallback);
        $this->assertSame($notFoundCallback, \PHPUnit\Framework\Assert::readAttribute($s, 'notFound'));
    }

    /**
     * Slim should throw a Slim_Exception_Stop if NotFound callback is not callable
     */
    public function testNotFoundHandlerIfNotCallable(): void {
        self::expectException(\Slim\Exception\Stop::class);
        $s = new \Slim\Slim();
        $notFoundCallback = 'foo';
        $s->notFound($notFoundCallback);
    }

    /************************************************
     * HOOKS
     ************************************************/

    /**
     * Test hook listener
     *
     * Pre-conditions:
     * Slim app instantiated;
     * Hook name does not exist;
     * Listeners are callable objects;
     *
     * Post-conditions:
     * Callables are invoked in expected order;
     */
    public function testRegistersAndCallsHooksByPriority(): void
    {
        $this->expectOutputString('barfoo');
        $app = new \Slim\Slim();
        $callable1 = function () { echo "foo"; };
        $callable2 = function () { echo "bar"; };
        $app->hook('test.hook.one', $callable1); //default is 10
        $app->hook('test.hook.one', $callable2, 8);
        /** @var countable $hooks */
        $hooks = $app->getHooks();
        $this->assertEquals(7, count($hooks)); //6 default, 1 custom
        $app->applyHook('test.hook.one');
    }

    /**
     * Test hook listener if listener is not callable
     *
     * Pre-conditions:
     * Slim app instantiated;
     * Hook name does not exist;
     * Listener is NOT a callable object;
     *
     * Post-conditions:
     * Hook is created;
     * Callable is NOT assigned to hook;
     */
    public function testHookInvalidCallable(): void
    {
        $app = new \Slim\Slim();
        $callable = 'test'; //NOT callable
        $app->hook('test.hook.one', $callable);
        $this->assertEquals(array(array()), $app->getHooks('test.hook.one'));
    }

    /**
     * Test hook invocation if hook does not exist
     *
     * Pre-conditions:
     * Slim app instantiated;
     * Hook name does not exist;
     *
     * Post-conditions:
     * Hook is created;
     * Hook initialized with empty array;
     */
    public function testHookInvocationIfNotExists(): void
    {
        $app = new \Slim\Slim();
        $app->applyHook('test.hook.one');
        $this->assertEquals(array(array()), $app->getHooks('test.hook.one'));
    }

    /**
     * Test clear hooks
     *
     * Pre-conditions:
     * Slim app instantiated;
     * Two hooks exist, each with one listener;
     *
     * Post-conditions:
     * Case A: Listeners for 'test.hook.one' are cleared;
     * Case B: Listeners for all hooks are cleared;
     */
    public function testHookClear(): void
    {
        $app = new \Slim\Slim();
        $app->hook('test.hook.one', function () {});
        $app->hook('test.hook.two', function () {});
        $app->clearHooks('test.hook.two');
        $this->assertEquals(array(array()), $app->getHooks('test.hook.two'));
        $hookOne = $app->getHooks('test.hook.one');
        $this->assertTrue(count($hookOne[10]) === 1);
        $app->clearHooks();
        $this->assertEquals(array(array()), $app->getHooks('test.hook.one'));
    }

    /**
     * Test hooks accept multiple arguments
     *
     * Pre-conditions:
     * Slim app instantiated;
     * Hook name does not exist;
     * Listener is a callable object;
     *
     * Post-conditions:
     * Callable invoked with 2 arguments
     */
    public function testHooksMultipleArguments(): void
    {
        $testArgA = 'argumentA';
        $testArgB = 'argumentB';

        $this->expectOutputString($testArgA . $testArgB);

	$app = new \Slim\Slim();

        $app->hook('test.hook.one', function ($argA, $argB) {
                echo $argA . $argB;
        });
        $app->applyHook('test.hook.one', $testArgA, $testArgB);
    }

    /**
     * Test late static binding
     *
     * Pre-conditions:
     * Slim app is extended by Derived class and instantiated;
     * Derived class overrides the 'getDefaultSettings' function and adds an extra default config value
     * Test that the new config value exists
     *
     * Post-conditions:
     * Config value exists and is equal to expected value
     */
    public function testDerivedClassCanOverrideStaticFunction(): void
    {
        $app = new Derived();
        $this->assertEquals($app->config("late-static-binding"), true);
    }
}
