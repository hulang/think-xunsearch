<?php

declare(strict_types=1);

/**
 * think-xunsearch 离线契约测试
 *
 * 不连接真实 xunsearch 后端，也不依赖官方 SDK；通过手写 stub 工厂隔离，
 * 校验服务门面把索引 / 检索动作路由到正确的底层对象与方法。
 *
 * 用法：php test/run.php   退出码 0 全部通过，1 存在不符。
 */

$root = dirname(__DIR__);
require $root . '/src/xunsearch/contract/XsFactory.php';
require $root . '/src/xunsearch/service/XunsearchService.php';

use think\xunsearch\contract\XsFactory;
use think\xunsearch\service\XunsearchService;

$fail = 0;
$total = 0;

function check(string $label, bool $ok): void
{
    global $fail, $total;
    $total++;
    if ($ok) {
        echo "[PASS] {$label}\n";
    } else {
        echo "[FAIL] {$label}\n";
        $fail++;
    }
}

/* ------------------------------- stub 实现 ------------------------------- */

class StubDoc
{
    public $fields;

    public function __construct(array $fields)
    {
        $this->fields = $fields;
    }
}

class StubIndex
{
    public $updateDocs = [];
    public $addDocs = [];
    public $delCalls = [];
    public $cleanCount = 0;
    public $flushCount = 0;

    public function add($doc)
    {
        $this->addDocs[] = $doc;
        return $this;
    }

    public function update($doc)
    {
        $this->updateDocs[] = $doc;
        return $this;
    }

    public function del($term, $field = null)
    {
        $this->delCalls[] = [$term, $field];
        return $this;
    }

    public function clean()
    {
        $this->cleanCount++;
        return $this;
    }

    public function flushIndex()
    {
        $this->flushCount++;
        return true;
    }
}

class StubSearch
{
    public $query = null;
    public $limit = null;
    public $offset = null;
    public $searchArgs = [];
    public $countResult = 0;
    public $relatedResult = [];
    public $hotResult = [];
    public $suggestResult = [];
    public $highlightResult = '';

    public function setQuery($query)
    {
        $this->query = $query;
        return $this;
    }

    public function setLimit($limit, $offset = 0)
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    public function search($query = null)
    {
        $this->searchArgs[] = $query;
        return [new StubDoc(['id' => 1, 'title' => '结果文档'])];
    }

    public function count($query = null)
    {
        return $this->countResult;
    }

    public function getRelatedQuery($query = null, $limit = 6)
    {
        $this->relatedResult = [$query, $limit];
        return ['相关词一', '相关词二'];
    }

    public function getHotQuery($limit = 6, $type = 'total')
    {
        $this->hotResult = [$limit, $type];
        return ['手机' => 100, '小米' => 50];
    }

    public function getExpandedQuery($query, $limit = 10)
    {
        $this->suggestResult = [$query, $limit];
        return ['小米手机', '小米耳机'];
    }

    public function highlight($value, $strtr = false)
    {
        $this->highlightResult = $value;
        return '<b>' . $value . '</b>';
    }
}

class StubXs
{
    public $index;
    public $search;

    public function __construct()
    {
        $this->index = new StubIndex();
        $this->search = new StubSearch();
    }
}

class StubFactory implements XsFactory
{
    public $xsPool = [];

    public function make(string $project): object
    {
        if (!isset($this->xsPool[$project])) {
            $this->xsPool[$project] = new StubXs();
        }
        return $this->xsPool[$project];
    }

    public function makeDocument(array $fields): object
    {
        return new StubDoc($fields);
    }
}

/* ------------------------------- 测试用例 ------------------------------- */

$config = [
    'default_project' => 'demo',
    'projects'        => ['cms' => 'cms.ini'],
    'index'           => ['auto_flush' => false],
    'search'          => ['limit' => 10, 'offset' => 0],
];

$factory = new StubFactory();
$service = new XunsearchService($factory, $config);

// 1. 项目标识解析：映射表命中 / 回退默认
check('项目标识经 projects 映射到 ini 名', $service->resolveProject('cms') === 'cms.ini');
check('未映射项目原样返回', $service->resolveProject('ask') === 'ask');
check('空项目回退默认项目', $service->resolveProject(null) === 'demo');

// 2. update 路由到 index->update，字段透传，默认不自动刷新
$service->update(['id' => 1, 'title' => '标题一']);
$index = $factory->xsPool['demo']->index;
check('update 调用索引 update 并透传文档', count($index->updateDocs) === 1 && $index->updateDocs[0]->fields === ['id' => 1, 'title' => '标题一']);
check('auto_flush=false 时不自动刷新索引', $index->flushCount === 0);

// 3. delete 路由到 index->del，主键与字段透传
$service->delete(123, 'id');
check('delete 调用索引 del 并透传主键/字段', $index->delCalls === [[123, 'id']]);

// 4. clear 路由到 index->clean
$service->clear();
check('clear 调用索引 clean', $index->cleanCount === 1);

// 5. flush 路由到 index->flushIndex
check('flush 调用 flushIndex 并返回 true', $service->flush() === true && $index->flushCount === 1);

// 6. auto_flush=true 时 add 后自动刷新
$factory2 = new StubFactory();
$service2 = new XunsearchService($factory2, ['index' => ['auto_flush' => true]]);
$service2->add(['id' => 2, 'title' => '标题二']);
$index2 = $factory2->xsPool['demo']->index;
check('add 调用索引 add', count($index2->addDocs) === 1);
check('auto_flush=true 时 add 后自动刷新', $index2->flushCount === 1);

// 7. query 路由到 search 的 setQuery/setLimit/search
$search = $factory->xsPool['demo']->search;
$docs = $service->query('手机', 5, 10);
check('query 设置查询词', $search->query === '手机');
check('query 设置分页 limit/offset', $search->limit === 5 && $search->offset === 10);
check('query 执行检索并返回文档', count($docs) === 1);

// 8. 建议词 / 相关词 / 热词透传参数
$service->suggest('小米', 8);
check('suggest 调用 getExpandedQuery 并透传前缀/条数', $search->suggestResult === ['小米', 8]);

$service->related('手机', 5);
check('related 调用 getRelatedQuery 并透传词/条数', $search->relatedResult === ['手机', 5]);

$service->hot(10, 'lastnum');
check('hot 调用 getHotQuery 并透传条数/类型', $search->hotResult === [10, 'lastnum']);

// 9. 高亮透传字段值
$highlighted = $service->highlight('标题一');
check('highlight 调用 search->highlight 并返回处理结果', $search->highlightResult === '标题一' && strpos($highlighted, '<b>') === 0);

echo "\n契约测试共 {$total} 项，失败 {$fail} 项。\n";
exit($fail > 0 ? 1 : 0);
