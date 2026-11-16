<?php

declare(strict_types=1);

namespace think\xunsearch\service;

use think\xunsearch\contract\XsFactory;

/**
 * Xunsearch 检索服务门面
 *
 * 对外提供索引增删改、刷新与常用检索能力；底层官方 \XS / \XSIndex / \XSSearch
 * 对象通过工厂接口获取，便于替换与测试。
 *
 * @package think\xunsearch\service
 */
class XunsearchService
{
    /**
     * @var XsFactory
     */
    private $factory;

    /**
     * @var array<string,mixed>
     */
    private $config;

    /**
     * 按项目复用的 \XS 实例池
     *
     * @var array<string,object>
     */
    private $xsPool = [];

    /**
     * @param XsFactory           $factory 客户端工厂
     * @param array<string,mixed> $config   包配置
     */
    public function __construct(XsFactory $factory, array $config = [])
    {
        $this->factory = $factory;
        $this->config = array_merge($this->defaultConfig(), $config);
    }

    /**
     * @return array<string,mixed>
     */
    private function defaultConfig(): array
    {
        return [
            'default_project' => 'demo',
            'ini_dir'         => '',
            'charset'         => 'utf-8',
            'projects'        => [],
            'index'           => ['auto_flush' => false],
            'search'          => ['limit' => 10, 'offset' => 0, 'order' => ''],
        ];
    }

    /**
     * 解析项目标识为 ini 项目名或路径
     *
     * @param string|null $project 项目标识，空则取默认项目
     * @return string
     */
    public function resolveProject(?string $project): string
    {
        $project = $project ?? (string) $this->config['default_project'];
        $projects = is_array($this->config['projects']) ? $this->config['projects'] : [];

        return $projects[$project] ?? $project;
    }

    /**
     * 获取（并按项目复用）官方 \XS 实例
     *
     * @param string|null $project
     * @return object
     */
    public function xs(?string $project = null): object
    {
        $key = $this->resolveProject($project);
        if (!isset($this->xsPool[$key])) {
            $this->xsPool[$key] = $this->factory->make($key);
        }

        return $this->xsPool[$key];
    }

    /**
     * 获取索引管理对象（XSIndex）
     *
     * @param string|null $project
     * @return object
     */
    public function index(?string $project = null): object
    {
        return $this->xs($project)->index;
    }

    /**
     * 获取检索对象（XSSearch）
     *
     * @param string|null $project
     * @return object
     */
    public function search(?string $project = null): object
    {
        return $this->xs($project)->search;
    }

    /**
     * 新增文档索引
     *
     * @param array<string,mixed> $fields  文档字段（须含主键字段）
     * @param string|null        $project
     * @return self
     */
    public function add(array $fields, ?string $project = null): self
    {
        $doc = $this->factory->makeDocument($fields);
        $this->index($project)->add($doc);
        $this->autoFlush($project);

        return $this;
    }

    /**
     * 更新（或新增）文档索引，按主键覆盖旧文档
     *
     * @param array<string,mixed> $fields  文档字段（须含主键字段）
     * @param string|null        $project
     * @return self
     */
    public function update(array $fields, ?string $project = null): self
    {
        $doc = $this->factory->makeDocument($fields);
        $this->index($project)->update($doc);
        $this->autoFlush($project);

        return $this;
    }

    /**
     * 删除索引文档
     *
     * @param mixed       $term  主键、索引词或其数组
     * @param string|null $field 索引词所属字段，默认为主键字段
     * @param string|null $project
     * @return self
     */
    public function delete($term, ?string $field = null, ?string $project = null): self
    {
        $this->index($project)->del($term, $field);
        $this->autoFlush($project);

        return $this;
    }

    /**
     * 完全清空当前项目索引
     *
     * @param string|null $project
     * @return self
     */
    public function clear(?string $project = null): self
    {
        $this->index($project)->clean();

        return $this;
    }

    /**
     * 强制刷新索引缓存到后端
     *
     * @param string|null $project
     * @return bool
     */
    public function flush(?string $project = null): bool
    {
        return (bool) $this->index($project)->flushIndex();
    }

    /**
     * 执行检索
     *
     * @param string      $query  搜索语句，支持字段检索与布尔语法
     * @param int|null    $limit  返回条数，空取配置默认值
     * @param int|null    $offset 偏移量，空取配置默认值
     * @param string|null $project
     * @return array<int,object> XSDocument 列表
     */
    public function query(string $query, ?int $limit = null, ?int $offset = null, ?string $project = null): array
    {
        $search = $this->search($project);
        $limit = $limit ?? (int) $this->config['search']['limit'];
        $offset = $offset ?? (int) $this->config['search']['offset'];

        $search->setQuery($query)->setLimit($limit, $offset);

        return $search->search($query);
    }

    /**
     * 估算匹配文档数量
     *
     * @param string|null $query 搜索语句，空使用默认语句
     * @param string|null $project
     * @return int
     */
    public function count(?string $query = null, ?string $project = null): int
    {
        return (int) $this->search($project)->count($query);
    }

    /**
     * 获取相关搜索词
     *
     * @param string|null $query  搜索词，空使用当前默认语句
     * @param int         $limit  数量上限
     * @param string|null $project
     * @return array<int,string>
     */
    public function related(?string $query = null, int $limit = 6, ?string $project = null): array
    {
        return $this->search($project)->getRelatedQuery($query, $limit);
    }

    /**
     * 获取热门搜索词
     *
     * @param int         $limit 数量上限（1~50）
     * @param string      $type  total | lastnum | currnum
     * @param string|null $project
     * @return array<string,int>
     */
    public function hot(int $limit = 6, string $type = 'total', ?string $project = null): array
    {
        return $this->search($project)->getHotQuery($limit, $type);
    }

    /**
     * 获取搜索建议（前缀展开词，可用于输入框下拉提示）
     *
     * @param string      $query 前缀词（拼音 / 英文 / 中文）
     * @param int         $limit 返回数量上限（1~20）
     * @param string|null $project
     * @return array<int,string>
     */
    public function suggest(string $query, int $limit = 10, ?string $project = null): array
    {
        return $this->search($project)->getExpandedQuery($query, $limit);
    }

    /**
     * 对结果字段做高亮飘红处理
     *
     * @param string      $value 原始字段值
     * @param string|null $project
     * @return string
     */
    public function highlight(string $value, ?string $project = null): string
    {
        return $this->search($project)->highlight($value);
    }

    /**
     * 按配置决定是否在索引变动后自动刷新
     *
     * @param string|null $project
     * @return void
     */
    private function autoFlush(?string $project): void
    {
        $auto = is_array($this->config['index']) && !empty($this->config['index']['auto_flush']);
        if ($auto) {
            $this->flush($project);
        }
    }
}
