<?php

declare(strict_types=1);

namespace Aorinex\AorinexNew;

use Throwable;

final class Application
{
    public function run(array $argv): int
    {
        $args = array_slice($argv, 1);
        if ($args === [] || in_array($args[0], ['-h', '--help', 'help'], true)) {
            $this->printHelp();
            return 0;
        }

        if (in_array($args[0], ['-V', '--version', 'version'], true)) {
            echo "aorinex-new 1.0.0\n";
            return 0;
        }

        $projectName = null;
        $from = null;
        $repo = Config::defaultTemplateRepo();
        $ref = Config::defaultTemplateRef();
        $imageNamespace = Config::defaultImageNamespace();
        $oldName = Config::DEFAULT_TEMPLATE_NAME;
        $directory = null;
        $keepGit = false;
        $withComposer = false;

        $i = 0;
        $count = count($args);
        while ($i < $count) {
            $arg = $args[$i];
            if (!str_starts_with($arg, '-')) {
                if ($projectName !== null) {
                    fwrite(STDERR, "多余参数: {$arg}\n");
                    return 1;
                }
                $projectName = $arg;
                $i++;
                continue;
            }

            switch ($arg) {
                case '--from':
                    $from = $this->requireValue($args, $i, $arg);
                    break;
                case '--repo':
                    $repo = $this->requireValue($args, $i, $arg);
                    break;
                case '--ref':
                case '--branch':
                    $ref = $this->requireValue($args, $i, $arg);
                    break;
                case '--image-namespace':
                    $imageNamespace = rtrim($this->requireValue($args, $i, $arg), '/');
                    break;
                case '--old-name':
                    $oldName = $this->requireValue($args, $i, $arg);
                    break;
                case '--directory':
                case '-d':
                    $directory = $this->requireValue($args, $i, $arg);
                    break;
                case '--keep-git':
                    $keepGit = true;
                    break;
                case '--with-composer':
                    $withComposer = true;
                    break;
                default:
                    fwrite(STDERR, "未知选项: {$arg}\n");
                    $this->printHelp();
                    return 1;
            }
            $i++;
        }

        if ($projectName === null || $projectName === '') {
            fwrite(STDERR, "请指定项目名，例如: aorinex-new my-backend\n");
            $this->printHelp();
            return 1;
        }

        $targetDir = $directory ?? (getcwd() . DIRECTORY_SEPARATOR . $projectName);
        if (!str_starts_with($targetDir, '/')) {
            $targetDir = getcwd() . DIRECTORY_SEPARATOR . $targetDir;
        }

        try {
            $creator = new ProjectCreator(
                projectName: $projectName,
                targetDir: $targetDir,
                fromPath: $from,
                templateRepo: $repo,
                templateRef: $ref,
                imageNamespace: $imageNamespace,
                oldName: $oldName,
                keepGit: $keepGit,
                withComposer: $withComposer,
            );
            $creator->run();
        } catch (Throwable $e) {
            fwrite(STDERR, '错误: ' . $e->getMessage() . "\n");
            return 1;
        }

        return 0;
    }

    /**
     * @param list<string> $args
     */
    private function requireValue(array $args, int &$i, string $option): string
    {
        if (!isset($args[$i + 1]) || str_starts_with($args[$i + 1], '-')) {
            throw new \InvalidArgumentException("选项 {$option} 需要参数");
        }
        $i++;
        return $args[$i];
    }

    private function printHelp(): void
    {
        $repo = Config::defaultTemplateRepo();
        echo <<<HELP
aorinex-new — 从 aorinex Webman 后台模板创建新项目

用法:
  aorinex-new <project-name> [选项]

参数:
  project-name          新项目名（kebab-case），如 my-backend

选项:
  --from <path>         从本地模板目录复制（不走 git clone，适合内网调试）
  --repo <url>          模板 Git 地址（默认: {$repo}）
  --ref, --branch <ref> 模板分支或 tag（默认: main）
  --image-namespace <n> 镜像仓库命名空间前缀（不含项目名）
  --old-name <name>     模板内旧项目名（默认: aorinex-backend）
  -d, --directory <dir> 输出目录（默认: 当前目录/<project-name>）
  --keep-git            不删除模板 .git / 不重新 git init
  --with-composer       创建后自动执行 composer install
  -h, --help            显示帮助
  -V, --version         显示版本

环境变量:
  AORINEX_NEW_TEMPLATE_REPO      默认模板仓库
  AORINEX_NEW_TEMPLATE_REF       默认分支/tag
  AORINEX_NEW_IMAGE_NAMESPACE    默认镜像命名空间

示例:
  aorinex-new my-backend --from /www/wwwroot/aorinex-backend
  aorinex-new my-backend --repo git@gitlab.example.com:group/aorinex-backend.git
  aorinex-new my-backend --with-composer

HELP;
    }
}
