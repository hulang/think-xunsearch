<?php

declare(strict_types=1);

/**
 * think-xunsearch 配置
 *
 * 说明：
 * - xunsearch 后端服务需单独安装并启动，本包仅为 PHP 客户端封装，不替代后端。
 * - 每个搜索项目对应一份 .ini 配置（字段、分词、权重等），可由官方 util/IniWizzaard 生成。
 */

return [
    // 默认搜索项目名（与 ini 文件名对应，不含扩展名）
    'default_project' => 'demo',

    // 项目 ini 配置目录；留空则使用官方 SDK 默认目录（vendor/hightman/xunsearch/app）。
    // 对应官方常量 XS_APP_ROOT。
    'ini_dir' => '',

    // 索引与检索结果编码
    'charset' => 'utf-8',

    // 多项目配置：project 标识 => ini 项目名或绝对路径
    'projects' => [
        // 'cms'  => 'cms',
        // 'ask'  => 'ask',
    ],

    // 索引写入默认行为
    'index' => [
        // 提交文档后是否自动 flushIndex 刷新到后端；false 时由调用方显式 flush。
        'auto_flush' => false,
    ],

    // 检索默认行为
    'search' => [
        'limit' => 10,
        'offset' => 0,
        // 排序：'' 为相关性排序；其余字段名配合方向。
        'order' => '',
    ],
];
