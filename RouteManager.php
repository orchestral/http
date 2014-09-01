<?php namespace Orchestra\Http;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Support\NamespacedItemResolver;
use Orchestra\Extension\RouteGenerator;
use Orchestra\Support\Str;

abstract class RouteManager
{
    /**
     * Application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * List of routes.
     *
     * @var array
     */
    protected $routes = array();

    /**
     * Construct a new instance.
     *
     * @param  \Illuminate\Foundation\Application   $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Start the application.
     *
     * @return object
     */
    abstract public function boot();

    /**
     *  Return locate handles configuration for a package/app.
     *
     * @param  string   $path
     * @return array
     */
    public function locate($path)
    {
        $query = '';

        // split URI and query string, the route resolver should not worry
        // about provided query string.
        if (strpos($path, '?') !== false) {
            list($path, $query) = explode('?', $path, 2);
        }

        list($package, $route, $item) = with(new NamespacedItemResolver)->parseKey($path);

        ! empty($item) && $route = "{$route}.{$item}";

        // Prepare route valid, since we already extract package from route
        // we can re append query string to route value.
        empty($route) && $route = '';
        empty($query) || $route = "{$route}?{$query}";

        // If package is empty, we should consider that the route is using
        // app (or root path), it doesn't matter at this stage if app is
        // an extension or simply handling root path.
        empty($package) && $package = "app";

        return array($package, $route);
    }

    /**
     * Return route group dispatch for a package/app.
     *
     * @param  string           $name
     * @param  string           $default
     * @param  array            $attributes
     * @param  \Closure|null    $callback
     * @return array
     */
    public function group($name, $default, $attributes = array(), Closure $callback = null)
    {
        $route = $this->route($name, $default);

        if ($attributes instanceof Closure) {
            $callback   = $attributes;
            $attributes = array();
        }

        $attributes = array_merge($attributes, array(
            'prefix' => $route->prefix(),
            'domain' => $route->domain(),
        ));

        if (is_callable($callback)) {
            $this->app['router']->group($attributes, $callback);
        }

        return $attributes;
    }

    /**
     *  Return handles URL for a package/app.
     *
     * @param  string   $path
     * @return string
     */
    public function handles($path)
    {
        list($package, $route) = $this->locate($path);

        // Get the path from route configuration, and append route.
        $locate = $this->route($package)->to($route);
        empty($locate) && $locate = '/';

        if (Str::startsWith($locate, 'http')) {
            return $locate;
        }

        return $this->app['url']->to($locate);
    }

    /**
     *  Return if handles URL match given string.
     *
     * @param  string   $path
     * @return bool
     */
    public function is($path)
    {
        list($package, $route) = $this->locate($path);

        return $this->route($package)->is($route);
    }

    /**
     * Register the given Closure with the "group" function namespace set.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public function namespaced(Closure $callback)
    {
        $this->group('orchestra/foundation', 'orchestra', [], $callback);
    }

    /**
     * Get extension route.
     *
     * @param  string   $name
     * @param  string   $default
     * @return \Orchestra\Extension\RouteGenerator
     */
    public function route($name, $default = '/')
    {
        // Boot the application.
        $this->boot();

        if (in_array($name, array('orchestra', 'orchestra/foundation'))) {
            $name = 'orchestra';
        }

        if (! isset($this->routes[$name])) {
            $this->routes[$name] = $this->generateRouteByName($name, $default);
        }

        return $this->routes[$name];
    }

    /**
     * Run the callback when route is matched.
     *
     * @param  string   $path
     * @param  mixed    $listener
     * @return void
     */
    public function when($path, $listener)
    {
        $listener = $this->app['events']->makeListener($listener);

        $this->app->booted(function () use ($listener, $path) {
            if ($this->is($path)) {
                call_user_func($listener);
            }
        });
    }

    /**
     * Generte route by name.
     *
     * @param  string   $name
     * @param  string   $default
     * @return \Orchestra\Extension\RouteGenerator
     */
    protected function generateRouteByName($name, $default)
    {
        // Orchestra Platform routing is managed by `orchestra/foundation::handles`
        // and can be manage using configuration.
        if (in_array($name, array('orchestra'))) {
            return new RouteGenerator(
                $this->app['config']->get('orchestra/foundation::handles', $default),
                $this->app['request']
            );
        }

        return $this->app['orchestra.extension']->route($name, $default);
    }
}
