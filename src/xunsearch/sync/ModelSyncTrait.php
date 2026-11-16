<?php

declare(strict_types=1);

namespace think\xunsearch\sync;

use think\xunsearch\service\XunsearchService;

/**
 * 模型索引同步 Trait
 *
 * 在业务模型中引入本 trait，并按需覆盖 `$xunsearch` 配置：
 *
 * ```php
 * class Article extends \think\Model
 * {
 *     use \think\xunsearch\sync\ModelSyncTrait;
 *
 *     protected $xunsearch = [
 *         'project' => 'cms',        // 项目标识，空取默认项目
 *         'key'     => 'id',         // 主键字段
 *         'fields'  => ['title', 'content'], // 写入索引的字段
 *     ];
 * }
 * ```
 *
 * 模型保存 / 删除后调用 `syncToIndex()` / `removeFromIndex()`；
 * 也可在模型自己的 afterWrite / afterDelete 钩子里调用这两个方法。
 *
 * @package think\xunsearch\sync
 */
trait ModelSyncTrait
{
    /**
     * 同步当前模型文档到索引
     *
     * @return void
     */
    public function syncToIndex(): void
    {
        $cfg = $this->xunsearchConfig();

        (new IndexSynchronizer($this->xunsearchService(), $cfg))
            ->sync($this, $cfg['fields'], $cfg['key'], $cfg['project']);
    }

    /**
     * 从索引删除当前模型对应文档
     *
     * @return void
     */
    public function removeFromIndex(): void
    {
        $cfg = $this->xunsearchConfig();
        $key = $this->xunsearchKeyValue($cfg['key']);

        (new IndexSynchronizer($this->xunsearchService(), $cfg))
            ->remove($key, $cfg['key'], $cfg['project']);
    }

    /**
     * 合并模型覆盖的索引配置
     *
     * @return array<string,mixed>
     */
    protected function xunsearchConfig(): array
    {
        $override = property_exists($this, 'xunsearch') && is_array($this->xunsearch) ? $this->xunsearch : [];

        return array_merge([
            'project' => null,
            'key'     => 'id',
            'fields'  => [],
        ], $override);
    }

    /**
     * 解析检索服务；TP 环境下默认通过容器助手 `app('xunsearch')` 获取，
     * 非 TP 环境可在模型中覆盖本方法返回服务实例。
     *
     * @return XunsearchService
     */
    protected function xunsearchService(): XunsearchService
    {
        if (function_exists('app')) {
            return app('xunsearch');
        }

        throw new \RuntimeException('未能解析 xunsearch 服务：请覆盖 xunsearchService() 注入服务实例。');
    }

    /**
     * 读取模型主键值
     *
     * @param string $keyField
     * @return mixed
     */
    protected function xunsearchKeyValue(string $keyField)
    {
        if (method_exists($this, 'getAttr')) {
            return $this->getAttr($keyField);
        }

        return $this->{$keyField} ?? null;
    }
}
