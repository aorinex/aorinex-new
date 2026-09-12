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
            echo "aorinex-new 1.2.0\n";

            return 0;
        }

        $projectName = null;
        $type = null;
        $backendFrom = Config::defaultBackendFrom();
        $frontendFrom = Config::defaultFrontendFrom();
        $websiteFrom = Config::defaultWebsiteFrom();
        $backendRepo = Config::defaultBackendRepo();
        $frontendRepo = Config::defaultFrontendRepo();
        $websiteRepo = Config::defaultWebsiteRepo();
        $ref = Config::defaultTemplateRef();
        $imageNamespace = Config::defaultImageNamespace();
        $backendOldName = Config::DEFAULT_BACKEND_OLD_NAME;
        $frontendOldName = Config::DEFAULT_FRONTEND_OLD_NAME;
        $websiteOldName = Config::DEFAULT_WEBSITE_OLD_NAME;
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
                } elseif ($type === null && $this->isTypeToken($arg)) {
                    $type = Config::normalizeType($arg);
                } else {
                    fwrite(STDERR, "多余参数: {$arg}\n");

                    return 1;
                }
                $i++;
                continue;
            }

            switch ($arg) {
                case '--type':
                    $type = Config::normalizeType($this->requireValue($args, $i, $arg));
                    break;
                case '--backend-from':
                    $backendFrom = $this->requireValue($args, $i, $arg);
                    break;
                case '--frontend-from':
                    $frontendFrom = $this->requireValue($args, $i, $arg);
                    break;
                case '--website-from':
                case '--nuxt-from':
                    $websiteFrom = $this->requireValue($args, $i, $arg);
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
                case '--website-repo':
                case '--nuxt-repo':
                    $websiteRepo = $this->requireValue($args, $i, $arg);
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
                case '--website-old-name':
                case '--nuxt-old-name':
                    $websiteOldName = $this->requireValue($args, $i, $arg);
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
        $type = Config::normalizeType($type);
        if (!in_array($type, Config::TYPES, true)) {
            fwrite(STDERR, "类型无效: {$type}（可选: backend、frontend、website|nuxt、all）\n");

            return 1;
        }

        // 旧参数映射到对应侧
        if ($legacyFrom !== null) {
            if ($type === Config::TYPE_FRONTEND) {
                $frontendFrom = $legacyFrom;
            } elseif ($type === Config::TYPE_BACKEND) {
                $backendFrom = $legacyFrom;
            } elseif ($type === Config::TYPE_WEBSITE) {
                $websiteFrom = $legacyFrom;
            } else {
                fwrite(STDERR, "all 模式下请使用 --backend-from / --frontend-from / --website-from，不要使用 --from\n");

                return 1;
            }
        }
        if ($legacyRepo !== null) {
            if ($type === Config::TYPE_FRONTEND) {
                $frontendRepo = $legacyRepo;
            } elseif ($type === Config::TYPE_WEBSITE) {
                $websiteRepo = $legacyRepo;
            } else {
                $backendRepo = $legacyRepo;
            }
        }
        if ($legacyOldName !== null) {
            if ($type === Config::TYPE_FRONTEND) {
                $frontendOldName = $legacyOldName;
            } elseif ($type === Config::TYPE_WEBSITE) {
                $websiteOldName = $legacyOldName;
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
                websiteFrom: $websiteFrom,
                backendRepo: $backendRepo,
                frontendRepo: $frontendRepo,
                websiteRepo: $websiteRepo,
                templateRef: $ref,
                imageNamespace: $imageNamespace,
                backendOldName: $backendOldName,
                frontendOldName: $frontendOldName,
                websiteOldName: $websiteOldName,
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

    private function isTypeToken(string $arg): bool
    {
        $normalized = Config::normalizeType($arg);

        return in_array($normalized, Config::TYPES, true) || isset(Config::TYPE_ALIASES[$arg]);
    }

    private function resolveRootDir(string $projectName, string $type, ?string $directory): string
    {
        if ($directory !== null) {
            $target = $directory;
        } else {
            $target = match ($type) {
                Config::TYPE_BACKEND => $projectName . '-backend',
                Config::TYPE_FRONTEND => $projectName . '-frontend',
                Config::TYPE_WEBSITE => $projectName . '-website',
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
        $websiteRepo = Config::defaultWebsiteRepo();
        echo <<<HELP
aorinex-new — 从 aorinex 后端/管理端/官网模板创建新项目

用法:
  aorinex-new <project-name> [backend|frontend|website|nuxt|all] [选项]
  aorinex-new <project-name> --type website [选项]

参数:
  project-name              项目名（kebab-case），如 my-app
  type                      backend | frontend | website(nuxt) | all（默认 all）

目录规则（与 backend 同级风格）:
  backend   → <name>-backend/
  frontend  → <name>-frontend/
  website   → <name>-website/
  all       → <name>/<name>-backend + <name>-frontend + <name>-website

选项:
  --type <type>             同上（website 可用别名 nuxt）
  --backend-from <path>     本地后端模板目录
  --frontend-from <path>    本地管理端模板目录
  --website-from <path>     本地官网（Nuxt）模板目录（别名 --nuxt-from）
  --from <path>             仅单侧 backend/frontend/website 时可用（兼容旧用法）
  --backend-repo <url>      后端 Git（默认: {$backendRepo}）
  --frontend-repo <url>     管理端 Git（默认: {$frontendRepo}）
  --website-repo <url>      官网 Git（默认: {$websiteRepo}，别名 --nuxt-repo）
  --repo <url>              单侧模式下的模板仓库（兼容）
  --ref, --branch <ref>     分支或 tag（默认: main）
  --image-namespace <n>     后端镜像命名空间前缀
  -d, --directory <dir>     自定义输出根目录
  --keep-git                保留模板 .git / 不重新 git init
  --with-composer           后端创建后执行 composer install
  --with-pnpm               前端/官网创建后执行 pnpm install
  -h, --help                显示帮助
  -V, --version             显示版本

环境变量:
  AORINEX_NEW_BACKEND_REPO / AORINEX_NEW_FRONTEND_REPO / AORINEX_NEW_WEBSITE_REPO
  AORINEX_NEW_BACKEND_FROM / AORINEX_NEW_FRONTEND_FROM / AORINEX_NEW_WEBSITE_FROM
  AORINEX_NEW_TEMPLATE_REF / AORINEX_NEW_IMAGE_NAMESPACE

示例:
  aorinex-new my-app all \\
    --backend-from /www/wwwroot/aorinex-backend \\
    --frontend-from /www/wwwroot/aorinex-frontend \\
    --website-from /www/wwwroot/aorinex-nuxt

  aorinex-new my-app website --website-from /www/wwwroot/aorinex-nuxt
  aorinex-new my-app nuxt --nuxt-from /www/wwwroot/aorinex-nuxt

HELP;
    }
}
