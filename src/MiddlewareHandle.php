<?php

namespace Cxx\Tp5Middleware;

use think\App;
use think\Config;
use think\Loader;
use think\Request;

class MiddlewareHandle
{

    /**
     * 前置方法名
     */
    const BEFORE_ACTION = 'before';

    /**
     * 后置方法名
     */
    const AFTER_ACTION = 'after';

    /**
     * 应用响应后方法名
     */
    const RESPONSE_END_ACTION = 'end';

    /**
     * 当前调度的模块
     * @var string 
     */
    private static $dispatchModule = '';

    /**
     * 当前调度的类
     * @var string 
     */
    private static $dispatchController = '';

    /**
     * 当前调度的类方法
     * @var string 
     */
    private static $dispatchAction = '';

    /**
     * 中间件配置
     * @var array 
     */
    private static $middlewareConfig = [];

    /**
     * 中间件实例
     */
    private static $container = [];

    /**
     * @param \think\Request $request
     */
    public static function moduleInit($request)
    {
        $config = Config::get();
        self::$dispatchModule = $request->module();
        $controller = $config['url_convert'] ? strtolower($request->controller()) : $request->controller();
        $action = $config['url_convert'] ? strtolower($request->action()) : $request->action();
        self::$dispatchController = Loader::parseClass(self::$dispatchModule, $config['url_controller_layer'], $controller, $config['controller_suffix']);
        self::$dispatchAction = $action;
        self::$middlewareConfig = Config::get('middleware');
        self::registerControllerMiddleware(self::$dispatchController);
        
        self::dispatchMiddleware(
            self::getMiddlewares('global'),
            $request,
            self::BEFORE_ACTION
        );

        self::dispatchMiddleware(
            self::getMiddlewares('module', self::$dispatchModule),
            $request,
            self::BEFORE_ACTION
        );
        self::dispatchMiddleware(
            self::getMiddlewares('controller', self::$dispatchController),
            $request,
            self::BEFORE_ACTION
        );
    }

    /**
     * @param \think\Response $response
     */
    public static function appEnd($response)
    {
        self::dispatchMiddleware(
            self::getMiddlewares('controller', self::$dispatchController, true),
            $response,
            self::AFTER_ACTION
        );

        self::dispatchMiddleware(
            self::getMiddlewares('module', self::$dispatchModule, true),
            $response,
            self::AFTER_ACTION
        );

        self::dispatchMiddleware(
            self::getMiddlewares('global', '', true),
            $response,
            self::AFTER_ACTION
        );
    }

    /**
     * @param \think\Response $response
     */
    public static function responseEnd($response)
    {
        $request = Request::instance();
        self::dispatchMiddleware(
            self::getMiddlewares('global'),
            [$request, $response],
            self::RESPONSE_END_ACTION
        );

        self::dispatchMiddleware(
            self::getMiddlewares('module', self::$dispatchModule),
            [$request, $response],
            self::RESPONSE_END_ACTION
        );

        self::dispatchMiddleware(
            self::getMiddlewares('controller', self::$dispatchController),
            [$request, $response],
            self::RESPONSE_END_ACTION
        );

    }

    /**
     * 获取中间件
     * @param string $level
     * @param string $key
     * @param bool $is_reverse 是否反转
     * @return array
     */
    public static function getMiddlewares($level = 'global', $key = '', $is_reverse = false)
    {
        if (!isset(self::$middlewareConfig[$level])) {
            return [];
        }
        $array = self::$middlewareConfig[$level];
        if ($key !== '') {
            $array = isset($array[$key]) ? $array[$key] : [];
        }
        return $is_reverse ? array_reverse($array) : $array;
    }

    /**
     * 调度中间件
     * @param array $middlewares 中间件集合
     * @param mixed $param 传递给中间件的参数
     * @param string $action 调度方法
     */
    private static function dispatchMiddleware($middlewares, $param, $action = self::BEFORE_ACTION)
    {
        if (!is_array($param)) {
            $param = [$param];
        }
        if (!is_array($middlewares)) {
            $middlewares = [$middlewares];
        }
        foreach ($middlewares as $middleware) {
            if (!class_exists($middleware)) {
                throw new \think\exception\ClassNotFoundException("Middleware Not Found : " . $middleware, $middleware);
            }
            if (!method_exists($middleware, $action)) {
                continue;
            }
            if (!isset(self::$container[$middleware])) {
                self::$container[$middleware] = App::invokeClass($middleware);
            }
            $obj = self::$container[$middleware];
            App::invokeMethod([$obj, $action], $param);
        }
    }

    /**
     * 使用反射机制注册控制器中间件
     * @access public
     * @param object $controller 控制器实例
     * @return void
     */
    private static function registerControllerMiddleware($controller): void
    {
        $controller = App::invokeClass($controller);
        $request_action = self::$dispatchAction;
        
        $class = new \ReflectionClass($controller);
        
        if ($class->hasProperty('middleware')) {
            $reflectionProperty = $class->getProperty('middleware');
            $reflectionProperty->setAccessible(true);

            $middlewares = $reflectionProperty->getValue($controller); // 需要实例化控制器,由于框架后面还会实例化控制器,会调用两次,可能照成其他问题
            // $middlewares = $reflectionProperty->getDefaultValue(); // 不需要实例化控制器,无法在控制器构造函数中动态改变中间件配置

            foreach ($middlewares as $key => $val) {
                if (!is_int($key)) {
                    if (isset($val['only']) && !in_array($request_action, array_map(function ($item) {
                        return strtolower($item);
                    }, is_string($val['only']) ? explode(",", $val['only']) : $val['only']))) {
                        continue;
                    } elseif (isset($val['except']) && in_array($request_action, array_map(function ($item) {
                        return strtolower($item);
                    }, is_string($val['except']) ? explode(',', $val['except']) : $val['except']))) {
                        continue;
                    } else {
                        $val = $key;
                    }
                }

                if (is_string($val) && strpos($val, ':')) {
                    $val = explode(':', $val);
                    if (count($val) > 1) {
                        $val = [$val[0], array_slice($val, 1)];
                    }
                }
                if(!isset(self::$middlewareConfig['controller'][$controller])){
                    self::$middlewareConfig['controller'][$controller] = [];
                }
                // @todo 进行别名解析，然后加载进 self::$middlewareConfig 里面
                foreach ($val as $middleware) {
                    self::$middlewareConfig['controller'][$controller][] = $middleware;
                }
            }
        }
    }
}
