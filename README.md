# think-xunsearch

ThinkPHP 风格的迅搜（xunsearch）全文检索封装包。在官方 PHP SDK（`hightman/xunsearch`）之上做一层薄封装，提供索引增删改、刷新、检索、分页、排序、高亮、相关词 / 热词 / 搜索建议等能力；核心类通过工厂接口隔离，不强耦合框架容器，可独立使用，也便于在 ThinkPHP 8 中接入服务注册与 Facade。

## 能力清单

- 索引：新增 `add`、更新 `update`、删除 `delete`、清空 `clear`、强制刷新 `flush`，支持写入后自动刷新开关。
- 检索：`query` 检索（分页 limit/offset）、`count` 命中估算、`highlight` 结果高亮。
- 词项：`related` 相关搜索词、`hot` 热门搜索词、`suggest` 搜索建议（前缀展开）。
- 多项目：`default_project` 默认项目，`projects` 映射表支持按标识切换不同 ini 项目。
- TP8 接入：提供 `think\Xunsearch` 门面、容器服务自动绑定与 `xunsearch:clear` / `xunsearch:reindex` 命令骨架。
- 模型同步：提供框架无关的索引同步器与 `ModelSyncTrait`，模型保存 / 删除后可自动或手动同步索引。
- 可测试：底层客户端经 `XsFactory` 接口创建，离线测试可注入 mock，无需连接真实后端。

## 环境要求

- PHP >= 8.0（基线 8.0）。
- PHP 扩展：`mbstring`（官方 SDK 声明的必需扩展）；字符集转换在 `mbstring` / `iconv` 中二选一即可，非 UTF-8 数据环境需确保至少开启其一；`pcre` 为 PHP 内置默认扩展，无需额外安装。
- 官方 SDK：`hightman/xunsearch`（composer 依赖，版本约束 `*@beta`）。
- **xunsearch 后端服务需单独安装并启动**：后端为 C/C++ 实现（Xapian + SCWS 分词），本包只是 PHP 客户端封装，**不能替代后端**。每个搜索项目需一份 `.ini` 配置（字段、分词、权重等），可由官方 `util/IniWizzaard` 生成。

## 安装

```bash
composer require hulang/think-xunsearch
```

在 ThinkPHP 8 中安装后，`extra.think.config.xunsearch` 会把 `config/config.php` 发布为应用内 `xunsearch` 配置。

## 配置

| 键 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `default_project` | string | `demo` | 默认搜索项目名（与 ini 文件名对应，不含扩展名）。 |
| `ini_dir` | string | `''` | 项目 ini 目录；留空用 SDK 默认目录，对应官方常量 `XS_APP_ROOT`。 |
| `charset` | string | `utf-8` | 索引与检索结果编码。 |
| `projects` | array | `[]` | 多项目映射：项目标识 => ini 项目名或绝对路径。 |
| `index.auto_flush` | bool | `false` | 提交文档后是否自动 `flushIndex`；`false` 时由调用方显式 `flush`。 |
| `search.limit` | int | `10` | 检索默认返回条数。 |
| `search.offset` | int | `0` | 检索默认偏移量。 |
| `search.order` | string | `''` | 排序字段；`''` 为相关性排序。 |

## 快速上手

### 独立使用

```php
use think\xunsearch\factory\SdkFactory;
use think\xunsearch\service\XunsearchService;

$config = require __DIR__ . '/config/config.php';

$factory = new SdkFactory($config);
$service = new XunsearchService($factory, $config);

// 写入索引（fields 须包含项目主键字段）
$service->add(['id' => 1, 'title' => '示例标题', 'content' => '正文内容']);
$service->update(['id' => 1, 'title' => '更新后的标题']);

// 检索
$docs = $service->query('关键词', 10, 0);
$total = $service->count('关键词');

// 搜索建议 / 相关词 / 热词
$suggestions = $service->suggest('小米', 10);
$related     = $service->related('关键词', 6);
$hot         = $service->hot(10, 'total');

// 高亮
$highlighted = $service->highlight('命中的标题片段');
```

### 多项目

```php
// projects 中配置 'cms' => 'cms.ini' 后，显式传入项目标识
$service->query('关键词', 10, 0, 'cms');
$service->add(['id' => 1, 'title' => 'CMS 文档'], 'cms');
```

### ThinkPHP 8 接入

安装后 `extra.think.services` 会自动注册 `think\xunsearch\Service`，把 `XsFactory`、`XunsearchService` 绑定到容器，并提供 `xunsearch` 别名。可直接通过门面静态调用：

```php
use think\Xunsearch;

// 检索 / 写入
$docs = Xunsearch::query('关键词', 10, 0);
Xunsearch::add(['id' => 1, 'title' => '示例标题']);

// 搜索建议 / 高亮
$suggestions = Xunsearch::suggest('小米', 10);
$highlighted = Xunsearch::highlight('命中片段');
```

命令行指令（在应用根目录执行）：

```bash
# 清空指定项目索引
php think xunsearch:clear --project=cms

# 重建索引骨架（数据导入需业务侧接入，见命令内 TODO 注释）
php think xunsearch:reindex --project=cms
```

### 模型自动同步索引

在业务模型中引入 `ModelSyncTrait`，覆盖 `$xunsearch` 配置，保存 / 删除后调用同步方法：

```php
use think\xunsearch\sync\ModelSyncTrait;

class Article extends \think\Model
{
    use ModelSyncTrait;

    protected $xunsearch = [
        'project' => 'cms',                 // 项目标识，空取默认项目
        'key'     => 'id',                  // 主键字段
        'fields'  => ['title', 'content'], // 写入索引的字段
    ];

    // 示例：在模型自己的保存 / 删除钩子里触发
    public function afterWrite()
    {
        $this->syncToIndex();
    }

    public function afterDelete()
    {
        $this->removeFromIndex();
    }
}
```

也可以不挂事件，在业务代码里手动调用：

```php
$article->save();
$article->syncToIndex();   // 写入 / 更新索引

$article->delete();
$article->removeFromIndex(); // 删除索引文档
```

底层 `IndexSynchronizer` 框架无关，可脱离模型直接使用：

```php
$synchronizer = new \think\xunsearch\sync\IndexSynchronizer($service);
$synchronizer->sync($model, ['title', 'content'], 'id');
$synchronizer->remove($id, 'id');
```

> 后台搜索词 / 热词管理界面、前台检索结果模板属于后续规划项，本包当前不含。

## 索引操作

| 方法 | 说明 |
| --- | --- |
| `add(array $fields, ?string $project = null)` | 新增文档索引。 |
| `update(array $fields, ?string $project = null)` | 按主键更新（或新增）文档。 |
| `delete($term, ?string $field = null, ?string $project = null)` | 按主键 / 索引词删除文档。 |
| `clear(?string $project = null)` | 完全清空当前项目索引。 |
| `flush(?string $project = null)` | 强制刷新索引缓存到后端，返回是否成功。 |

## 检索操作

| 方法 | 说明 |
| --- | --- |
| `query(string $query, ?int $limit = null, ?int $offset = null, ?string $project = null)` | 执行检索，返回 `XSDocument` 列表。 |
| `count(?string $query = null, ?string $project = null)` | 估算匹配文档数量。 |
| `related(?string $query = null, int $limit = 6, ?string $project = null)` | 相关搜索词。 |
| `hot(int $limit = 6, string $type = 'total', ?string $project = null)` | 热门搜索词，`type`：`total` / `lastnum` / `currnum`。 |
| `suggest(string $query, int $limit = 10, ?string $project = null)` | 搜索建议（前缀展开词）。 |
| `highlight(string $value, ?string $project = null)` | 对结果字段做高亮飘红处理。 |

## 测试

本包提供**离线 mock 契约测试**：通过手写 stub 工厂隔离官方 SDK，校验服务门面把索引 / 检索动作路由到正确的底层对象与方法，**不连接真实 xunsearch 后端，也不依赖已安装的官方 SDK**。

```bash
composer test
# 等价于
php test/run.php
```

当前测试结果（本机 PHP 8.3，离线环境）：

```text
[PASS] 项目标识经 projects 映射到 ini 名
[PASS] 未映射项目原样返回
[PASS] 空项目回退默认项目
[PASS] update 调用索引 update 并透传文档
[PASS] auto_flush=false 时不自动刷新索引
[PASS] delete 调用索引 del 并透传主键/字段
[PASS] clear 调用索引 clean
[PASS] flush 调用 flushIndex 并返回 true
[PASS] add 调用索引 add
[PASS] auto_flush=true 时 add 后自动刷新
[PASS] query 设置查询词
[PASS] query 设置分页 limit/offset
[PASS] query 执行检索并返回文档
[PASS] suggest 调用 getExpandedQuery 并透传前缀/条数
[PASS] related 调用 getRelatedQuery 并透传词/条数
[PASS] hot 调用 getHotQuery 并透传条数/类型
[PASS] highlight 调用 search->highlight 并返回处理结果
[PASS] 同步器 sync 调用索引 update 并透传主键与字段
[PASS] 同步器 remove 调用索引 delete 并透传主键/字段
[PASS] document 提取主键与指定字段

契约测试共 20 项，失败 0 项。
```

> 说明：以上为封装层路由契约校验，**不代表真实后端的检索命中结果**。在装有 xunsearch 后端并已建索引的环境中，命中条数、高亮词、建议词等以实际后端返回为准。

## 目录结构

```
think-xunsearch/
├── config/config.php                     # 包配置（发布为应用 xunsearch 配置）
├── src/
│   ├── Xunsearch.php                     # think\Xunsearch 门面
│   └── xunsearch/
│       ├── Service.php                   # TP8 容器服务注册
│       ├── contract/XsFactory.php         # 客户端工厂接口
│       ├── factory/SdkFactory.php         # 官方 SDK 默认工厂
│       ├── service/XunsearchService.php   # 索引 / 检索门面服务
│       ├── sync/
│       │   ├── IndexSynchronizer.php     # 模型索引同步器
│       │   └── ModelSyncTrait.php        # 模型同步 Trait
│       └── command/
│           ├── ClearCommand.php          # 清空索引命令骨架
│           └── ReindexCommand.php         # 重建索引命令骨架
└── test/run.php                          # 离线契约测试
```

## 协议与合规提示

- 本包上层代码采用 `GPL-2.0-or-later`。所依赖的官方 SDK `hightman/xunsearch` 同样为 **GPL** 协议；若将本包随闭源应用**公开发布或分发**，需注意 GPL 的传染性约束。**私有内部使用**不受此限。
- xunsearch 后端程序、分词词库及业务数据由使用方自备并遵守相应许可；本包不提供任何影视 / 受版权保护内容的索引、种子或下载资源。

## 部署前提（使用前检查）

1. 安装并启动 xunsearch 后端服务（`xs-ctl` / `xunsearch` 服务进程），确认 PHP 客户端可连通。
2. 为每个搜索项目准备 `.ini` 配置并放入 `ini_dir` 指定目录。
3. 通过 `composer require` 安装官方 SDK，再在业务中注入 `SdkFactory` 与 `XunsearchService`。
