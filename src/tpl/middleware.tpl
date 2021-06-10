<?php

namespace {%namespace%};

class {%className%}
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