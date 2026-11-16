<?php

declare(strict_types=1);

namespace think\xunsearch\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;
use think\xunsearch\service\XunsearchService;

/**
 * 重建项目索引骨架
 *
 * 仅封装 beginRebuild / endRebuild 生命周期；实际的数据批量读取与写入
 * 需在业务侧接入（按数据源循环调用 XunsearchService::add / update）。
 *
 * @package think\xunsearch\command
 */
class ReindexCommand extends Command
{
    /**
     * 命令配置
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('xunsearch:reindex')
            ->addOption('project', 'p', Option::VALUE_OPTIONAL, '项目标识，空取默认项目', null)
            ->setDescription('重建 xunsearch 项目索引（数据导入由业务侧实现）');
    }

    /**
     * 执行命令
     *
     * @param Input  $input
     * @param Output $output
     * @return int
     */
    protected function execute(Input $input, Output $output): int
    {
        $project = $input->getOption('project');
        $service = $this->app->make(XunsearchService::class);
        $index = $service->index($project);

        $index->beginRebuild();

        // TODO: 业务侧在此分批读取数据源，并循环调用：
        //       $service->add(['id' => ..., 'title' => ..., 'content' => ...], $project);
        $output->writeln('<comment>请在业务侧接入数据导入逻辑后再执行重建；未接入前不要在生产环境运行。</comment>');

        $index->endRebuild();
        $output->writeln('<info>索引重建完成：' . ($project ?? '默认项目') . '</info>');

        return 0;
    }
}
