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
            echo "aorinex-new 1.1.0\n";

            return 0;
        }

        $projectName = null;
        $type = null;
        $backendFrom = Config::defaultBackendFrom();
        $frontendFrom = Config::defaultFrontendFrom();
        $backendRepo = Config::defaultBackendRepo();
        $frontendRepo = Config::defaultFrontendRepo();
        $ref = Config::defaultTemplateRef();
        $imageNamespace = Config::defaultImageNamespace();
        $backendOldName = Config::DEFAULT_BACKEND_OLD_NAME;
        $frontendOldName = Config::DEFAULT_FRONTEND_OLD_NAME;
        $directory = null;
        $keepGit = false;
        $withComposer = false;
        $withPnpm = false;

        // 兼容旧参数
        $legacyFrom = null;
        $legacyRepo = null;
        $legacyOldName = null;

        $i = 0;
        $count = count($args);
        while ($i < $count) {
            $arg = $args[$i];
            if (!str_starts_with($arg, '-')) {
                if ($projectName === null) {
                    $projectName = $arg;
                } elseif ($type === null && in_array($arg, Config::TYPES, true)) {
                    $type = $arg;
                } else {
                    fwrite(STDERR, "多余参数: {$arg}\n");

                    return 1;
                }
                $i++;
                continue;
            }

            switch ($arg) {
                case '--type':
                    $type = $this->requireValue($args, $i, $arg);
                    break;
                case '--backend-from':
                    $backendFrom = $this->requireValue($args, $i, $arg);
                    break;
                case '--frontend-from':
                    $frontendFrom = $this->requireValue($args, $i, $arg);
                    break;
                case '--from':
                    $legacyFrom = $this->requireValue($args, $i, $arg);
                    break;
                case '--backend-repo':
                    $backendRepo = $this->requireValue($args, $i, $arg);
                    break;
                case '--frontend-repo':
                    $frontendRepo = $this->requireValue($args, $i, $arg);
                    break;
                case '--repo':
                    $legacyRepo = $this->requireValue($args, $i, $arg);
                    break;
                case '--ref':
                case '--branch':
                    $ref = $this->requireValue($args, $i, $arg);
                    break;
                case '--image-namespace':
                    $imageNamespace = rtrim($this->requireValue($args, $i, $arg), '/');
                    break;
                case '--backend-old-name':
                    $backendOldName = $this->requireValue($args, $i, $arg);
                    break;
                case '--frontend-old-name':
                    $frontendOldName = $this->requireValue($args, $i, $arg);
                    break;
                case '--old-name':
                    $legacyOldName = $this->requireValue($args, $i, $arg);
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
                case '--with-pnpm':
                    $withPnpm = true;
                    break;
                default:
                    fwrite(STDERR, "未知选项: {$arg}\n");
                    $this->printHelp();

                    return 1;
            }
            $i++;
        }

        if ($projectName === null || $projectName === '') {
            fwrite(STDERR, "请指定项目名，例如: aorinex-new my-app all\n");
            $this->printHelp();

            return 1;
        }

        $type = $type ?? Config::TYPE_ALL;
        if (!in_array($type, Config::TYPES, true)) {
            fwrite(STDERR, "类型无效: {$type}（可选: backend、frontend、all）\n");

            return 1;
        }

        // 旧参数映射到对应侧
        if ($legacyFrom !== null) {
            if ($type === Config::TYPE_FRONTEND) {
                $frontendFrom = $legacyFrom;
            } elseif ($type === Config::TYPE_BACKEND) {
                $backendFrom = $legacyFrom;
            } else {
                fwrite(STDERR, "all 模式下请使用 --backend-from / --frontend-from，不要使用 --from\n");

                return 1;
            }
        }
        if ($legacyRepo !== null) {
            if ($type === Config::TYPE_FRONTEND) {
                $frontendRepo = $legacyRepo;
            } else {
                $backendRepo = $legacyRepo;
            }
        }
        if ($legacyOldName !== null) {
            if ($type === Config::TYPE_FRONTEND) {
                $frontendOldName = $legacyOldName;
            } else {
                $backendOldName = $legacyOldName;
            }
        }

        $rootDir = $this->resolveRootDir($projectName, $type, $directory);

        try {
            $creator = new WorkspaceCreator(
                type: $type,
                baseName: $projectName,
                rootDir: $rootDir,
                backendFrom: $backendFrom,
                frontendFrom: $frontendFrom,
                backendRepo: $backendRepo,
                frontendRepo: $frontendRepo,
                templateRef: $ref,
                imageNamespace: $imageNamespace,
                backendOldName: $backendOldName,
                frontendOldName: $frontendOldName,
                keepGit: $keepGit,
                withComposer: $withComposer,
                withPnpm: $withPnpm,
            );
            $creator->run();
        } catch (Throwable $e) {
            fwrite(STDERR, '错误: ' . $e->getMessage() . "\n");

            return 1;
        }

        return 0;
    }

    private function resolveRootDir(string $projectName, string $type, ?string $directory): string
    {
        if ($directory !== null) {
            $target = $directory;
        } else {
            $target = match ($type) {
                Config::TYPE_BACKEND => $projectName . '-backend',
                Config::TYPE_FRONTEND => $projectName . '-frontend',
                Config::TYPE_ALL => $projectName,
            };
        }

        if (!str_starts_with($target, '/')) {
            $target = getcwd() . DIRECTORY_SEPARATOR . $target;
        }

        return $target;
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
        $backendRepo = Config::defaultBackendRepo();
        $frontendRepo = Config::defaultFrontendRepo();
        echo <<<HELP
aorinex-new — 从 aorinex 后端/前端模板创建新项目

用法:
  aorinex-new <project-name> [backend|frontend|all] [选项]
  aorinex-new <project-name> --type all [选项]

参数:
  project-name              项目名（kebab-case），如 my-app
  type                      backend | frontend | all（默认 all）

目录规则:
  backend   → <name>-backend/
  frontend  → <name>-frontend/
  all       → <name>/<name>-backend 与 <name>/<name>-frontend

选项:
  --type <type>             同上
  --backend-from <path>     本地后端模板目录
  --frontend-from <path>    本地前端模板目录
  --from <path>             仅 backend/frontend 单侧时可用（兼容旧用法）
  --backend-repo <url>      后端 Git（默认: {$backendRepo}）
  --frontend-repo <url>     前端 Git（默认: {$frontendRepo}）
  --repo <url>              单侧模式下的模板仓库（兼容）
  --ref, --branch <ref>     分支或 tag（默认: main）
  --image-namespace <n>     后端镜像命名空间前缀
  -d, --directory <dir>     自定义输出根目录
  --keep-git                保留模板 .git / 不重新 git init
  --with-composer           后端创建后执行 composer install
  --with-pnpm               前端创建后执行 pnpm install
  -h, --help                显示帮助
  -V, --version             显示版本

环境变量:
  AORINEX_NEW_BACKEND_REPO / AORINEX_NEW_FRONTEND_REPO
  AORINEX_NEW_BACKEND_FROM / AORINEX_NEW_FRONTEND_FROM
  AORINEX_NEW_TEMPLATE_REF / AORINEX_NEW_IMAGE_NAMESPACE

示例:
  aorinex-new my-app all \\
    --backend-from /www/wwwroot/aorinex-backend \\
    --frontend-from /www/wwwroot/aorinex-admin

  aorinex-new my-app backend --backend-from /www/wwwroot/aorinex-backend
  aorinex-new my-app frontend --frontend-repo https://github.com/ximengyi/aorinex-admin.git

HELP;
    }
}
