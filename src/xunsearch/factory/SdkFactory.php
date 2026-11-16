<?php

declare(strict_types=1);

namespace think\xunsearch\factory;

use think\xunsearch\contract\XsFactory;

/**
 * 基于官方 hightman/xunsearch SDK 的默认工厂
 *
 * 官方类 \XS / \XSDocument 在运行时按类名动态解析，
 * 未安装 SDK 时不会在静态加载阶段报错。
 *
 * @package think\xunsearch\factory
 */
class SdkFactory implements XsFactory
{
    /**
     * @param array<string,mixed> $config 包配置
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * @var array<string,mixed>
     */
    private $config;

    /**
     * {@inheritdoc}
     */
    public function make(string $project): object
    {
        $iniDir = isset($this->config['ini_dir']) ? (string) $this->config['ini_dir'] : '';
        if ($iniDir !== '' && !defined('XS_APP_ROOT')) {
            define('XS_APP_ROOT', $iniDir);
        }

        $class = '\XS';
        return new $class($project);
    }

    /**
     * {@inheritdoc}
     */
    public function makeDocument(array $fields): object
    {
        $class = '\XSDocument';
        return new $class($fields);
    }
}
