<?php
declare(strict_types = 1);
/**
 * /src/Rest/Describer/ApiDocDescriber.php
 *
 * @author  TLe, Tarmo Leppänen <tarmo.leppanen@protacon.com>
 */
namespace App\Rest\Describer;

use App\Annotation\RestApiDoc;
use App\Rest\Doc\RouteModel;
use Closure;
use Doctrine\Common\Annotations\AnnotationReader;
use EXSyst\Component\Swagger\Swagger;
use InvalidArgumentException;
use Nelmio\ApiDocBundle\Describer\DescriberInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use UnexpectedValueException;
use function array_filter;
use function array_map;
use function array_values;
use function count;
use function explode;
use function mb_strrpos;
use function mb_strtolower;

/**
 * Class ApiDocDescriber
 *
 * @package App\Rest\Describer
 * @author  TLe, Tarmo Leppänen <tarmo.leppanen@protacon.com>
 */
class ApiDocDescriber implements DescriberInterface
{
    /**
     * @var RouteCollection
     */
    private $routeCollection;

    /**
     * @var Rest
     */
    private $rest;

    /**
     * @var AnnotationReader
     */
    private $annotationReader;

    /**
     * @var Swagger
     */
    private $api;

    /**
     * @param RouterInterface $router
     * @param Rest            $rest
     *
     * @throws \Doctrine\Common\Annotations\AnnotationException
     */
    public function __construct(RouterInterface $router, Rest $rest)
    {
        $this->routeCollection = $router->getRouteCollection();
        $this->rest = $rest;
        $this->annotationReader = new AnnotationReader();
    }

    /**
     * @param Swagger $api
     *
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws UnexpectedValueException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function describe(Swagger $api): void
    {
        $this->api = $api;

        foreach ($this->getRouteModels() as $routeModel) {
            $path = $api->getPaths()->get($routeModel->getRoute()->getPath());

            if ($path->hasOperation($routeModel->getHttpMethod())) {
                $this->rest->createDocs($path->getOperation($routeModel->getHttpMethod()), $routeModel);
            }
        }
    }

    /**
     * @return RouteModel[]
     *
     * @throws ReflectionException
     */
    private function getRouteModels(): array
    {
        $annotationFilterMethod = $this->getClosureAnnotationFilterMethod();
        $annotationFilterRoute = $this->getClosureAnnotationFilterRoute();

        $iterator = function (Route $route) use ($annotationFilterMethod, $annotationFilterRoute): RouteModel {
            [$controller, $method] = explode('::', $route->getDefault('_controller'));

            $reflection = new ReflectionMethod($controller, $method);
            $methodAnnotations = $this->annotationReader->getMethodAnnotations($reflection);
            $controllerAnnotations = $this->annotationReader->getClassAnnotations($reflection->getDeclaringClass());

            /** @var Method $httpMethodAnnotation */
            $httpMethodAnnotation = array_values(array_filter($methodAnnotations, $annotationFilterMethod))[0];

            /** @var \Sensio\Bundle\FrameworkExtraBundle\Configuration\Route $routeAnnotation */
            $routeAnnotation = array_values(array_filter($controllerAnnotations, $annotationFilterRoute))[0];

            $routeModel = new RouteModel();
            $routeModel->setController($controller);
            $routeModel->setMethod($method);
            $routeModel->setHttpMethod(mb_strtolower($httpMethodAnnotation->getMethods()[0]));
            $routeModel->setBaseRoute($routeAnnotation->getPath());
            $routeModel->setRoute($route);
            $routeModel->setMethodAnnotations($methodAnnotations);
            $routeModel->setControllerAnnotations($controllerAnnotations);

            return $routeModel;
        };

        $filter = function (Route $route): bool {
            return $this->routeFilter($route);
        };

        return array_map($iterator, array_filter($this->routeCollection->all(), $filter));
    }

    /**
     * @param Route $route
     *
     * @return bool
     *
     * @throws ReflectionException
     */
    private function routeFilter(Route $route): bool
    {
        $output = false;

        if (!$route->hasDefault('_controller') || mb_strrpos($route->getDefault('_controller'), '::')) {
            $output = true;
        }

        if ($output) {
            [$controller] = explode('::', $route->getDefault('_controller'));

            $reflection = new ReflectionClass($controller);

            $annotations = $this->annotationReader->getClassAnnotations($reflection);

            $this->isRestApiDocDisabled($route, $annotations, $output);
        }

        return $this->routeFilterMethod($route, $output);
    }

    /**
     * @param Route   $route
     * @param mixed[] $annotations
     * @param bool    $disabled
     */
    private function isRestApiDocDisabled(Route $route, array $annotations, bool &$disabled): void
    {
        foreach ($annotations as $annotation) {
            if ($annotation instanceof RestApiDoc && $annotation->disabled) {
                $disabled = false;

                $this->api->getPaths()->remove($route->getPath());
            }
        }
    }

    /**
     * @param Route $route
     * @param bool  $output
     *
     * @return bool
     *
     * @throws ReflectionException
     */
    private function routeFilterMethod(Route $route, bool $output): bool
    {
        if ($output) {
            [$controller, $method] = explode('::', $route->getDefault('_controller'));

            $reflection = new ReflectionMethod($controller, $method);

            $annotations = $this->annotationReader->getMethodAnnotations($reflection);

            $supported = [];

            array_map($this->isRouteSupported($supported), $annotations);

            $output = count($supported) === 2;
        }

        return $output;
    }

    /**
     * @param mixed[] &$supported
     *
     * @return Closure
     */
    private function isRouteSupported(array &$supported): Closure
    {
        return function ($annotation) use (&$supported): void {
            if ($annotation instanceof RestApiDoc || $annotation instanceof Method) {
                $supported[] = true;
            }
        };
    }

    /**
     * @return Closure
     */
    private function getClosureAnnotationFilterMethod(): Closure
    {
        /**
         * Simple filter lambda function to filter out all but Method class
         *
         * @param $annotation
         *
         * @return bool
         */
        return function ($annotation): bool {
            return $annotation instanceof Method;
        };
    }

    /**
     * @return Closure
     */
    private function getClosureAnnotationFilterRoute(): Closure
    {
        /**
         * Simple filter lambda function to filter out all but Method class
         *
         * @param $annotation
         *
         * @return bool
         */
        return function ($annotation): bool {
            return $annotation instanceof \Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
        };
    }
}
