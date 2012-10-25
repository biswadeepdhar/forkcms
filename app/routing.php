<?php

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Symfony\Component\HttpFoundation\Request;

/**
 * Application routing
 *
 * @author Tijs Verkoyen <tijs@sumocoders.be>
 * @author Davy Hellemans <davy@netlash.com>
 * @author Dieter Vanden Eynde <dieter@netlash.com>
 * @author Jelmer Snoeck <jelmer@siphoc.com>
 * @author Dave Lens <dave.lens@wijs.be>
 */
class ApplicationRouting
{
	const DEFAULT_APPLICATION = 'frontend';

	/**
	 * Virtual folders mappings
	 *
	 * @var	array
	 */
	private static $routes = array(
		'' => self::DEFAULT_APPLICATION,
		'private' => 'backend',
		'backend' => 'backend',
		'api' => 'api'
	);

	/**
	 * @var Kernel
	 */
	private $kernel;

	/**
	 * The actual request, formatted as a Symfony object.
	 *
	 * @var Request
	 */
	private $request;

	/**
	 * @param Request $request
	 * @param Kernel $kernel
	 */
	public function __construct(Request $request, Kernel $kernel)
	{
		$this->request = $request;
		$this->kernel = $kernel;

		$this->processQueryString();
	}

	/**
	 * Get the possible routes
	 *
	 * @return array
	 */
	public static function getRoutes()
	{
		return self::$routes;
	}

	/**
	 * Handle the actual request and deligate it to other parts of Fork.
	 *
	 * @return Response
	 */
	public function handleRequest()
	{
		$applicationName = APPLICATION;

		/**
		 * Our ajax and cronjobs don't go trough the index.php file at the
		 * moment. Because of this we need to add some extra validation.
		 */
		if(strpos($this->request->getRequestUri(), 'ajax.php') !== false)
		{
			$applicationName .= '_ajax';
		}
		elseif(strpos($this->request->getRequestUri(), 'cronjob.php') !== false)
		{
			$applicationName .= '_cronjob';
		}

		switch($applicationName)
		{
			case 'frontend':
			case 'frontend_ajax':
				require_once __DIR__ . '/../frontend/init.php';

				new FrontendInit($applicationName);

				if($applicationName == 'frontend') $application = new Frontend();
				else $application = new FrontendAJAX();

				break;
			case 'backend':
			case 'backend_ajax':
			case 'backend_cronjob':
				require_once __DIR__ . '/../backend/init.php';

				new BackendInit($applicationName);

				if($applicationName == 'backend') $application = new Backend();
				elseif($applicationName == 'backend_ajax')
				{
					$application = new BackendAJAX();
				}
				else $application = new BackendCronjob();

				break;
			case 'api':
				require_once __DIR__ . '/../api/1.0/init.php';

				new APIInit($applicationName);
				$application = new API();
				break;
		}

		// Load the page and pass along the application kernel
		// @todo this is backwards. The kernel IS our application's core.
		// This step is needed to bubble our container all the way to the action.
		// Once we switch to bundles, the kernel will boot those bundles and pass the container.
		// The kernel object itself is stored as a singleton in said container.
		$application->setKernel($this->kernel);
		$application->initialize();

		return $application->display();
	}

	/**
	 * Process the querystring to define the application
	 */
	private function processQueryString()
	{
		// get querystring
		$queryString = trim($this->request->getRequestUri(), '/');

		// split into chunks
		$chunks = explode('/', $queryString);

		// is there a application specified
		if(isset($chunks[0]))
		{
			// cleanup
			$proposedApplication = (string) $chunks[0];

			// set real application
			$application = (isset(self::$routes[$proposedApplication])) ? self::$routes[$proposedApplication] : self::DEFAULT_APPLICATION;
		}

		// no application
		else $application = self::DEFAULT_APPLICATION;

		// define APP
		if(!defined('APPLICATION')) define('APPLICATION', $application);
		if(!defined('NAMED_APPLICATION')) define('NAMED_APPLICATION', $proposedApplication);
	}
}
