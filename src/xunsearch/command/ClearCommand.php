<?php

declare(strict_types=1);

namespace think\xunsearch\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;
use think\xunsearch\service\XunsearchService;

/**
 * 清空指定项目索引
 *
 * @package think\xunsearch\command
 */
class ClearCommand extends Command
{
    /**
     * 命令配置
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('xunsearch:clear')
            ->addOption('project', 'p', Option::VALUE_OPTIONAL, '项目标识，空取默认项目', null)
            ->setDescription('清空指定 xunsearch 项目的全部索引');
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

        $service->clear($project);
        $service->flush($project);

        $output->writeln('<info>索引已清空：' . ($project ?? '默认项目') . '</info>');

        return 0;
    }
}
