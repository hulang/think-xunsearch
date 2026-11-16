<?php

declare(strict_types=1);

namespace think\xunsearch\contract;

/**
 * Xunsearch 客户端工厂接口
 *
 * 将官方 SDK 对象的创建隔离在此接口之后，便于在测试中注入 mock 实现，
 * 也便于将来替换为其它检索引擎的客户端。
 *
 * @package think\xunsearch\contract
 */
interface XsFactory
{
    /**
     * 创建官方 \XS 项目实例
     *
     * @param string $project 项目标识（ini 项目名或路径）
     * @return object 官方 \XS 实例
     */
    public function make(string $project): object;

    /**
     * 由字段数组创建官方 \XSDocument 实例
     *
     * @param array<string,mixed> $fields 文档字段键值
     * @return object 官方 \XSDocument 实例
     */
    public function makeDocument(array $fields): object;
}
