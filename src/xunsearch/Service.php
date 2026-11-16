<?php

declare(strict_types=1);

namespace think\xunsearch;

use think\xunsearch\contract\XsFactory;
use think\xunsearch\factory\SdkFactory;
use think\xunsearch\service\XunsearchService;

/**
 * ThinkPHP 8 服务注册
 *
 * 将工厂与检索服务绑定到容器，并注册 `xunsearch` 别名。
 *
 * @package think\xunsearch
 */
class Service extends \think\Service
{
    /**
     * 注册容器绑定
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(XsFactory::class, function () {
            return new SdkFactory($this->app->config->get('xunsearch', []));
        });

        $this->app->bind(XunsearchService::class, function () {
            return new XunsearchService(
                $this->app->make(XsFactory::class),
                $this->app->config->get('xunsearch', [])
            );
        });

        $this->app->bind('xunsearch', XunsearchService::class);
    }
}
