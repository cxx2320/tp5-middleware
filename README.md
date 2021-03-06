基于tp5版本的中间件

## 主要特性

* 代码无侵入性
* 更细粒度的操作

## 安装使用

`composer require cxx/tp5-middleware`

1. 行为绑定，在 `application\tags.php` 中添加
```php
return [
    // 应用初始化
    'app_init'     => [],
    // 应用开始
    'app_begin'    => [],
    // 模块初始化
    'module_init'  => [
        // !! 此行代码
        'Cxx\Tp5Middleware\MiddlewareHandle::moduleInit'
    ],
    // 操作开始执行
    'action_begin' => [],
    // 视图内容过滤
    'view_filter'  => [],
    // 日志写入
    'log_write'    => [],
    // 应用结束
    'app_end'      => [
        // !! 此行代码
        'Cxx\Tp5Middleware\MiddlewareHandle::appEnd'
    ],
    'response_end' => [
        // !! 此行代码
        'Cxx\Tp5Middleware\MiddlewareHandle::responseEnd'
    ]
];

```
2. 创建中间件(三个方法都是可选的,类写在哪里都无所谓，只要能被加载)


 * 命令行
 
 在 `applocation\command.php` 添加
```php
    return [
        Cxx\Tp5Middleware\MiddlewareCommand::class
    ];
```
  使用命令行直接创建 `php think make:middleware Test` ,会生成 `application/middleware/Test.php` 文件

 * 手动创建
```php
class Middle1
{
    /**
     * 前置方法
     * @param \think\Request $request
     */
    public function before($request){}

    /**
     * 后置方法
     * @param \think\Response $response
     */
    public function after($response){}

    /**
     * 响应结束方法
     * 在此的任何输出都不会响应给浏览器，参考 thinkphp/library/think/Response.php 128行代码
     * @param \think\Request $request
     * @param \think\Response $response
     */
    public function end($request, $response){}
}
```
3. 使用(创建 `application\extra\middleware.php` 文件)
```php

return [
    // 全局中间件
    'global' => [
        Middle1::class
    ],
    // 模块中间件
    'module' => [
        // 模块名称
        'index' => [
            Middle1::class
        ]
    ],
    // 控制器中间件
    'controller' => [
        // 相应的控制器
        Index::class => [
            Middle1::class
        ]
    ],
];
```

控制器中间件的另一种定义方式
```php
protected $middleware = [
    Middle1::class => ['except' => ['hello'] ],
    Middle2::class => ['only' => ['hello'] ],
];

```

## 调用顺序
前置方法->后置方法

`global -> module -> controller -> (核心代码) -> controller -> module -> global`

响应结束方法

`global -> module -> controller`
