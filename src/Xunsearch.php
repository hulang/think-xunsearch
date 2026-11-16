<?php

declare(strict_types=1);

namespace think;

use think\Facade;

/**
 * Xunsearch 检索门面
 *
 * 在 ThinkPHP 8 中通过容器绑定的 XunsearchService 提供静态调用入口。
 *
 * @see \think\xunsearch\service\XunsearchService
 * @package think
 * @mixin \think\xunsearch\service\XunsearchService
 *
 * @method static self add(array $fields, ?string $project = null) 新增文档索引
 * @method static self update(array $fields, ?string $project = null) 更新（或新增）文档索引
 * @method static self delete($term, ?string $field = null, ?string $project = null) 删除索引文档
 * @method static self clear(?string $project = null) 完全清空当前项目索引
 * @method static bool flush(?string $project = null) 强制刷新索引缓存到后端
 * @method static array query(string $query, ?int $limit = null, ?int $offset = null, ?string $project = null) 执行检索
 * @method static int count(?string $query = null, ?string $project = null) 估算匹配文档数量
 * @method static array related(?string $query = null, int $limit = 6, ?string $project = null) 相关搜索词
 * @method static array hot(int $limit = 6, string $type = 'total', ?string $project = null) 热门搜索词
 * @method static array suggest(string $query, int $limit = 10, ?string $project = null) 搜索建议
 * @method static string highlight(string $value, ?string $project = null) 结果字段高亮
 */
class Xunsearch extends Facade
{
    /**
     * 获取当前 Facade 对应类名（或容器对象标识）
     *
     * @return string
     */
    protected static function getFacadeClass(): string
    {
        return \think\xunsearch\service\XunsearchService::class;
    }
}
