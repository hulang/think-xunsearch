<?php

declare(strict_types=1);

namespace think\xunsearch\sync;

use think\xunsearch\service\XunsearchService;

/**
 * 模型索引同步器（框架无关）
 *
 * 将业务模型的字段映射为迅搜文档，调用检索服务完成写入 / 删除。
 * 不依赖 think-orm 内部事件钩子，可在任何模型或业务代码中使用。
 *
 * @package think\xunsearch\sync
 */
class IndexSynchronizer
{
    /**
     * @var XunsearchService
     */
    private $service;

    /**
     * @var array<string,mixed>
     */
    private $config;

    /**
     * @param XunsearchService   $service 检索服务
     * @param array<string,mixed> $config  默认配置：key 主键字段、fields 索引字段、project 项目标识
     */
    public function __construct(XunsearchService $service, array $config = [])
    {
        $this->service = $service;
        $this->config = $config;
    }

    /**
     * 由模型对象提取索引文档数组
     *
     * @param object              $model    业务模型或普通对象
     * @param array<int,string>  $fields   需要写入索引的字段名列表
     * @param string              $keyField 主键字段名
     * @return array<string,mixed>
     */
    public function document(object $model, array $fields, string $keyField = 'id'): array
    {
        $doc = [$keyField => $this->getValue($model, $keyField)];

        foreach ($fields as $field) {
            $doc[$field] = $this->getValue($model, $field);
        }

        return $doc;
    }

    /**
     * 同步（新增 / 更新）模型文档到索引
     *
     * @param object              $model    业务模型或普通对象
     * @param array<int,string>  $fields   需要写入索引的字段名列表
     * @param string              $keyField 主键字段名
     * @param string|null         $project  项目标识，空取服务默认项目
     * @return self
     */
    public function sync(object $model, array $fields, string $keyField = 'id', ?string $project = null): self
    {
        $doc = $this->document($model, $fields, $keyField);
        $this->service->update($doc, $project);

        return $this;
    }

    /**
     * 从索引删除模型对应文档
     *
     * @param mixed       $key     主键值
     * @param string|null $field   主键字段名，空使用服务默认主键字段
     * @param string|null $project 项目标识
     * @return self
     */
    public function remove($key, ?string $field = null, ?string $project = null): self
    {
        $this->service->delete($key, $field, $project);

        return $this;
    }

    /**
     * 读取模型字段值，兼容 think\Model 的 getAttr 与普通对象属性
     *
     * @param object $model
     * @param string $field
     * @return mixed
     */
    private function getValue(object $model, string $field)
    {
        if (method_exists($model, 'getAttr')) {
            return $model->getAttr($field);
        }

        if (isset($model->{$field})) {
            return $model->{$field};
        }

        return null;
    }
}
