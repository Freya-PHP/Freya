<?php

/*
 * Freya PHP
 * The extensible PHP micro framework.
 *
 * @author      Alex Priebe <hi@alexpriebe.com>
 * @copyright   2014 Alex Priebe
 * @link        http://freya.alexpriebe.com
 * @version     1.0.0
 * @package     Freya
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

namespace Freya;

class Freya extends \Freya\Helpers\Container
{
    const VERSION = '1.0.0';

    protected   $middleware;       // @var array
    protected   $error;            // @var mixed
    protected   $notFound;         // @var mixed
    
    protected $hooks = array(
        'freya.before'          => array(array()),
        'freya.before.router'   => array(array()),
        'freya.before.dispatch' => array(array()),
        'freya.after.dispatch'  => array(array()),
        'freya.after.router'    => array(array()),
        'freya.after'           => array(array())
    );

    /*
    |--------------------------------------------------------------------------
    | Public Functions
    |--------------------------------------------------------------------------
    */
    
    public function __construct()
    {
    }
	
	public function detectEnvironment($environments)
	{
		if ($environments instanceof Closure) {
			return call_user_func($environments);
		}
		
		if ($env = getenv('ENV')) {
    		return $env;
		}

		foreach ($environments as $environment => $hosts) {
			foreach ((array) $hosts as $host) {
				if ($host === gethostname()) {
				    return $environment;
				}
			}
		}

		return 'production';
	}
	
	public function getConfigLoader()
	{
		return new \Freya\Files\FileLoader(
		    new \Freya\Files\Filesystem,
		    __DIR__ . '/../config'
        );
	}
	
	public function startExceptionHandling()
	{
        //$logger = new \Freya\Logger\Logger($this->settings['path.storage'] . '/logs');

        $whoops = new \Whoops\Run;
        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
        
        $jsonHandler = new \Whoops\Handler\JsonResponseHandler;
        $jsonHandler->onlyForAjaxRequests(true);
        $whoops->pushHandler($jsonHandler);

        // Setup Monolog, for example:
        $logger = new \Monolog\Logger(';
        $logger->pushHandler(new Monolog\Handler\StreamHandler("/my/exception.log"));

        // Place our custom handler in front of the others, capturing exceptions
        // and logging them, then passing the exception on to the other handlers:
        $whoops->pushHandler(function ($exception, $inspector, $run) use($logger) {
            $logger->addError($exception->getMessage());
        });
        
        $whoops->register();
	}
	
	public function run()
	{
    	
	}
	
	/*
    |--------------------------------------------------------------------------
    | Protected Functions
    |--------------------------------------------------------------------------
    */
    
}
