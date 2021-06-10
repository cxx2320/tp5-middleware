<?php

namespace Cxx\Tp5Middleware;

use think\console\command\Make;

/**
 * 中间件生成器
 */
class MiddlewareCommand extends Make
{
    protected $type = "Middleware";

    protected function configure()
    {
        parent::configure();
        $this->setName('make:middleware')
            ->setDescription('Create a new middleware class');
    }

    protected function getStub()
    {
        return __DIR__ . '/tpl/middleware.tpl';
    }

    protected function getNamespace($appNamespace, $module)
    {
        return 'app\middleware';
    }
}
