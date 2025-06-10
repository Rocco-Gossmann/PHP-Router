<?php

namespace rogoss\router;

/**
 * rogoss\Router
 * =============================================================================
 * @author  Rocco Goßmann <github.com/rocco-gossmann>
 * @license MIT
 * =============================================================================
 *
 * This Router Class uses Attributes.
 * (TBH. This only exists because I wanted to play around with Attributes
 * and im hillariously uncreative when it comes to comming up with other ideas)
 *
 * In general This router does not care about Cases in your route
 * /Office
 * /offiCE
 * /office
 * would all lead to the same Controller->Method
 *
 * Only your Controller filenames must always be lower case
 *
 * the first directory of a path automatically defines a controller.
 * So if you need to keep the systems directory name limitations in mind, when defining your Routes.
 *
 * You can define a Default-Controller, that will handle all requests to controller files, that don't exist.
 *
 * for a none default controller to be invoced, the url must at least contain one, none leading "/"
 *
 * @example:
 *
 * lets say, we have 2 controllers:
 * _.php => as the default/root controller
 * office.php => as a controller to handle all "/office/*" requests
 *
 *in _.php:
 * -----------------------------------------------------------------------------
 * <?php
 *
 * use \rogoss\router\Router
 * use \rogoss\router\RouterController
 * use \rogoss\router\RouterRoute
 *
 * #[RouterController]
 * class DefaultController {
 *   #[
 *     RouterRoute(""), // <-- this is your entry for people just calling the Domain
 *     RouterRoute("index.html"), // <-- notice, that you can bind multiple routes to the same method
 *   ]
 *   public function ThisFunctionNameDoesNotMatter() {
      // do stuff in this route ...
 *   }
 *
 *   #[
 *      RouterRoute("office"), // <-- if office is called without a trailing "/", it will be handled here
 *                             //     we can however redirect it
 *      RouterRoute("potentialothercontroller")
 *   ]
 *   // Name still does not matter -----\/
 *   public static function redirectToControllerRoot(Router $router, string $matches)
 *   {
 *     // you get access to the current router and the $path, that lead here, so you can use this same function
 *     // to redirect other controllers as well
 *
 *     $router->HandleRoute("{$matches[0]}/"); // <- notice the added "/" at the end.
 *                                       // a slash marks that this is a controller, rather than a route
 *   }
 * }
 *
 * in office.php
 * -----------------------------------------------------------------------------
 * <?php
 *
 * use \rogoss\router\Router
 * use \rogoss\router\RouterController
 * use \rogoss\router\RouterRoute
 *
 * #[RouterController]
 * class OfficeController {
 *    #[ RouterRoute( "" ) ] // <-- this is the entry for calls to "/office/"
 *    public function OfficeIndexButNameStillDoesNotMatter() {
 *        echo "welcome to the office"
 *        // do stuff in this route ...
 *    }
 *
 * // routes an also match agains regular expressions, instead of simple strings.
 * // for that, just use the `expression` parametername
 *    #[ RouterRoute( expression: "([0-9]+)/(details|image)" ) ] // <-- this is the entry for calls to,
 *       for example, "/office/10/details"
 *       and          "/office/10/image"
 *    public function OfficeIndexButNameStillDoesNotMatter($matches) {
 *       $officeid = $matches[1];
 *       $action = $matches[2];
 *
 *       switch($action) {
 *           case "details":
 *               echo "<h1>welcome to the office with the id ", $officeId, "</h1>";
 *               // do stuff in this route ...
 *               break;
 *
 *           case "image":
 *               $file = "images/office-" . (int)$officeid . "-" . ".png";
 *               if(file_exists($file)) {
 *                   header("content-type: image/png");
 *                   echo file_get_contents();
 *               }
 *               else RouterController::handle404()
 *
 *               break;
 *
 *        }
 *    }
 * }
 *
 *	calling GET /office => would be handled by "_.php" => RouterRoute("office"), since there is no none-leading "/"
 *	calling GET /office/ => would be handeled by "office.php" => RouterRoute(""), since the trailing "/" marks the controller
 *	calling GET /office/some/other/path => would be handled by "office.php" => RouterRoute("some/other/path")
 *  calling GET /office2/index.html => would be handled by "_.php" => RouterRoute("office2/index.html"), since there is no controllerfile named "office2.php" defined.
 *
 * This is how you make use of the controller Class
 * <?php
 * 	use rogoss\router\Router;
 *
 * (new Router(
 *    __DIR__ . "/controllers", //<-- direcotry containing our router files
 *    __DIR__ . "/controllers/_.php" //<-- default / root Router
 * ))->HandleRoute($_SERVER['REQUEST_URI]);

 *
 */

use Attribute;
use Exception;
use ReflectionClass;

/** Thrown if Router fails to initialize */
class RouterException extends Exception
{
	const CODE_MISSING_CONTROLLERDIR = 1;
	const CODE_ROUTE_WITHOUT_DEFINITION = 2;
}

// Using attributes and Reflections to identify valid Controllers and Routes
// that way, we are not bound to keeping a specific Class or Method Name

/**
 * the #[RouterController] - Attribute will identfy which class is the actual controller.
 * In case the identified file should create multiple classes
 */
#[Attribute(Attribute::TARGET_CLASS)]
class RouterController
{
	public static function handle404() : void
	{
		http_response_code(404);
		exit;
	}
}

/** @internal */
abstract class RouteParser
{
	abstract function hitsRoute(string $route, string $path): bool;

    /** @return array */
    abstract function routeParameters(): array;
}

/** @internal */
class SimpleRouteParser extends RouteParser
{
	private string $route = "";
    public function hitsRoute(string $route, string $path): bool
    {
		$this->route = $path;
		return strtolower($route) == $path;
    }

	public function routeParameters(): array { return [ $this->route ]; }
}

/** @internal */
class ExpressionParser extends RouteParser
{
	private $params = [];

    public function hitsRoute(string $route, string $path): bool
    {
		$this->params = [];
		return preg_match("#" . $route . "#i", $path, $this->params);
    }

	public function routeParameters(): array
	{
		return $this->params;
	}
}


/** the #[RouterRoute] Attribute Identifies what route this method will serve */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class RouterRoute
{
	private string $sRoute = "";
	private string $method = "";
	private RouteParser $routeParser;

	private $_emptyMethod = true;

	public function __construct(
		?string $route = null,
		?string $expression = null,
		?string $method = ""
	) {
		if(!is_null($route))
		{
			$this->routeParser = new SimpleRouteParser($route);
			$this->sRoute = $route;
		}
		else if(!is_null($expression))
		{
			(preg_match("#" . $expression . "#", ""));

			$this->routeParser = new ExpressionParser($route);
			$this->sRoute = $expression;
		}
		else throw new RouterException(
			"route without path or expression definition, please set either `route` or `expression` function parameter => set parameter: " . var_export(func_get_args(), true)
		);

		$this->method = trim(strtolower($method));
		$this->_emptyMethod = empty($this->method);
	}

	public function hitsRoute(string $method, string $path) : bool {
		return ($this->_emptyMethod || $this->method == $method) && $this->routeParser->hitsRoute($this->sRoute, $path);
	}

	public function routeParams() : array {
		return $this->routeParser->routeParameters() ?? [];
	}
}


class Router
{
	/**
	 * @param string $controllerDirectory - path to the directory contraining all controller files
	 * @param string $defaultControllerFileName - controllerfile to load, if no suitable controller is found for the first directory of the given path.
	 */
	public function __construct(
		private string $controllerDirectory,
		private string $defaultControllerFileName
	) {
		$this->controllerDirectory = realpath($controllerDirectory);
		if (empty($this->controllerDirectory)) throw new RouterException("Directory {$controllerDirectory} does not exist", RouterException::CODE_MISSING_CONTROLLERDIR);
	}

	/**
	 * figures out what controller and method to call, based on the given full url
	 * @param string $url  - example: $_SERVER['REQUEST_URI'] on apache
	 */
	public function HandleRoute(string $url, ?string $method = null) : never
	{
		$aURL = parse_url($url);

		if(is_null($method)) $method = $_SERVER['REQUEST_METHOD'];

		$method = strtolower($method);

		/** @var array $aClasses - this list will keep track of classes we don't need to scan for RouteControllers */
		$aClasses = array_flip(get_declared_classes());

		/** @var string $controllerFile - find the controller to load.
		 * defined through the first directory in the path
		 * if no first directory exists, load "_.php"
		 */
		$sControllerFile = null;

		/** @var string $sPath - to identify the controller-class and route-methods methods*/
		$sPath = "";

		$sControllerFile = self::_extractControllerFileNameFromPathList($aURL['path'] ?? "", $sPath);
		$sPath = strtolower($sPath);

		// If no suitable controllerfile was found yet, use the default one
		if (is_null($sControllerFile)) $sControllerFile = $this->defaultControllerFileName;
		if (empty($sControllerFile))
		{
			RouterController::handle404();
			exit;
		}

		// Scan the controllerfile and invoce the appropriate route
		require_once $sControllerFile;

		// Prepare for finding the Attributes
		$sControllerClassName = get_class(new RouterController());
		$sRouteAttributeName = get_class(new RouterRoute(""));

		foreach (get_declared_classes() as $sClassName)
		{
			if (isset($aClasses[$sClassName])) continue;

			$oClassReflection = new ReflectionClass($sClassName);

			foreach ($oClassReflection->getAttributes() as $oAttr)
			{
				if ($oAttr->getName() != $sControllerClassName) continue;

				// => check methods
				$aMethods = $oClassReflection->getMethods();
				foreach ($aMethods as $oMethod)
				{
					foreach ($oMethod->getAttributes() as $oAttr)
					{
						if ($oAttr->getName() != $sRouteAttributeName)  continue;

						// Found a Routing Method
						// => check the path it serves
						/** @var RouterRoute $oRoute */
						$oRoute = $oAttr->newInstance();
						if (!$oRoute->hitsRoute($method, $sPath)) continue;

						// Bingo !!!
						$params = [];

						foreach($oMethod->getParameters() as $oParams)
						{
							switch($oParams->name)
							{
								case "router":
									$params["router"] = $this;
									break;

								case "matches":
									$params["matches"] = $oRoute->routeParams();
									break;

							}
						}

						$oMethod->invokeArgs(null, $params);
						exit;
					}
				}
				break 2;
			}
		}

		RouterController::handle404();
		exit;
	}

	private function _extractControllerFileNameFromPathList(string $path, string &$pathExcess) : null|string|bool
	{
		$sControllerFile = null;
		if (empty($path)) return $sControllerFile;


		$pathList = array_filter(explode("/", $path));
		if (str_ends_with($path, "/")) $pathList[] = "";

		if (count($pathList) <= 1) {
			$pathExcess = implode("/", $pathList);
			return $sControllerFile;
		}

		$controllerPath = array_shift($pathList);
		$controllerFileName = strtolower($controllerPath) . ".php";
		$pathExcess = implode("/", $pathList);

		if (!empty($controllerFileName)) {
			$nextFile = realpath($this->controllerDirectory . "/" . $controllerFileName);

			if (!empty($nextFile) && str_starts_with($nextFile, $this->controllerDirectory)) {
				$sControllerFile = $nextFile;
			} else $pathExcess = $controllerPath . "/" . $pathExcess;
		}

		return $sControllerFile;
	}
}
