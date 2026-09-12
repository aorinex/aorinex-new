<?php

declare(strict_types=1);

namespace Aorinex\AorinexNew;

use RuntimeException;

/**
 * 按 backend / frontend / website / all 组装输出目录。
 */
final class WorkspaceCreator
{
    public function __construct(
        private readonly string $type,
        private readonly string $baseName,
        private readonly string $rootDir,
        private readonly ?string $backendFrom,
        private readonly ?string $frontendFrom,
        private readonly ?string $websiteFrom,
        private readonly string $backendRepo,
        private readonly string $frontendRepo,
        private readonly string $websiteRepo,
        private readonly string $templateRef,
        private readonly string $imageNamespace,
        private readonly string $backendOldName,
        private readonly string $frontendOldName,
        private readonly string $websiteOldName,
        private readonly bool $keepGit,
        private readonly bool $withComposer,
        private readonly bool $withPnpm,
    ) {
    }

    public function run(): void
    {
        if (!preg_match('/^[a-z][a-z0-9-]*$/', $this->baseName)) {
            throw new RuntimeException(
                "项目名无效: {$this->baseName}\n请使用 kebab-case，例如: my-app、erp"
            );
        }

        if (!in_array($this->type, Config::TYPES, true)) {
            throw new RuntimeException(
                '类型无效: ' . $this->type . '（可选: backend、frontend、website、all）'
            );
        }

        match ($this->type) {
            Config::TYPE_BACKEND => $this->createBackendOnly(),
            Config::TYPE_FRONTEND => $this->createFrontendOnly(),
            Config::TYPE_WEBSITE => $this->createWebsiteOnly(),
            Config::TYPE_ALL => $this->createAll(),
        };
    }

    private function createBackendOnly(): void
    {
        $name = $this->baseName . '-backend';
        $dir = $this->rootDir;
        $this->createOne(
            kind: Config::TYPE_BACKEND,
            projectName: $name,
            targetDir: $dir,
            fromPath: $this->backendFrom,
            repo: $this->backendRepo,
            oldName: $this->backendOldName,
        );
        $this->printDone([$dir], Config::TYPE_BACKEND);
    }

    private function createFrontendOnly(): void
    {
        $name = $this->baseName . '-frontend';
        $dir = $this->rootDir;
        $this->createOne(
            kind: Config::TYPE_FRONTEND,
            projectName: $name,
            targetDir: $dir,
            fromPath: $this->frontendFrom,
            repo: $this->frontendRepo,
            oldName: $this->frontendOldName,
        );
        $this->printDone([$dir], Config::TYPE_FRONTEND);
    }

    private function createWebsiteOnly(): void
    {
        $name = $this->baseName . '-website';
        $dir = $this->rootDir;
        $this->createOne(
            kind: Config::TYPE_WEBSITE,
            projectName: $name,
            targetDir: $dir,
            fromPath: $this->websiteFrom,
            repo: $this->websiteRepo,
            oldName: $this->websiteOldName,
        );
        $this->printDone([$dir], Config::TYPE_WEBSITE);
    }

    private function createAll(): void
    {
        if (file_exists($this->rootDir)) {
            throw new RuntimeException("目标目录已存在: {$this->rootDir}");
        }
        if (!mkdir($this->rootDir, 0755, true) && !is_dir($this->rootDir)) {
            throw new RuntimeException("无法创建目录: {$this->rootDir}");
        }

        $backendName = $this->baseName . '-backend';
        $frontendName = $this->baseName . '-frontend';
        $websiteName = $this->baseName . '-website';
        $backendDir = $this->rootDir . DIRECTORY_SEPARATOR . $backendName;
        $frontendDir = $this->rootDir . DIRECTORY_SEPARATOR . $frontendName;
        $websiteDir = $this->rootDir . DIRECTORY_SEPARATOR . $websiteName;

        $this->createOne(
            kind: Config::TYPE_BACKEND,
            projectName: $backendName,
            targetDir: $backendDir,
            fromPath: $this->backendFrom,
            repo: $this->backendRepo,
            oldName: $this->backendOldName,
        );
        $this->createOne(
            kind: Config::TYPE_FRONTEND,
            projectName: $frontendName,
            targetDir: $frontendDir,
            fromPath: $this->frontendFrom,
            repo: $this->frontendRepo,
            oldName: $this->frontendOldName,
        );
        $this->createOne(
            kind: Config::TYPE_WEBSITE,
            projectName: $websiteName,
            targetDir: $websiteDir,
            fromPath: $this->websiteFrom,
            repo: $this->websiteRepo,
            oldName: $this->websiteOldName,
        );

        $this->writeRootReadme($backendName, $frontendName, $websiteName);
        $this->printDone([$backendDir, $frontendDir, $websiteDir], Config::TYPE_ALL);
    }

    private function createOne(
        string $kind,
        string $projectName,
        string $targetDir,
        ?string $fromPath,
        string $repo,
        string $oldName,
    ): void {
        $creator = new ProjectCreator(
            kind: $kind,
            projectName: $projectName,
            baseName: $this->baseName,
            targetDir: $targetDir,
            fromPath: $fromPath,
            templateRepo: $repo,
            templateRef: $this->templateRef,
            imageNamespace: $this->imageNamespace,
            oldName: $oldName,
            keepGit: $this->keepGit,
            withComposer: $this->withComposer,
            withPnpm: $this->withPnpm,
        );
        $creator->run();
    }

    private function writeRootReadme(string $backendName, string $frontendName, string $websiteName): void
    {
        $content = <<<MD
# {$this->baseName}

由 `aorinex-new` 从 aorinex 模板生成。

| 目录 | 说明 |
|------|------|
| `{$backendName}/` | Webman 后端 |
| `{$frontendName}/` | Vue Vben 管理端（web-ele） |
| `{$websiteName}/` | Nuxt 官网 |

## 后端

```bash
cd {$backendName}
composer install
# 编辑 .env
php webman start
```

## 管理端

```bash
cd {$frontendName}
pnpm install
# 编辑 apps/web-ele/.env.development 中的 VITE_DEV_PROXY_TARGET
pnpm run dev:ele
```

## 官网

```bash
cd {$websiteName}
pnpm install
cp .env.example .env
pnpm dev
```

MD;
        $path = $this->rootDir . DIRECTORY_SEPARATOR . 'README.md';
        file_put_contents($path, $content);
        echo "[all] 已写入根目录 README.md\n";
    }

    /**
     * @param list<string> $dirs
     */
    private function printDone(array $dirs, string $type): void
    {
        echo "\n";
        echo "✓ 创建完成（type={$type}）\n";
        foreach ($dirs as $dir) {
            echo "  - {$dir}\n";
        }
        echo "\n下一步:\n";
        if ($type === Config::TYPE_BACKEND || $type === Config::TYPE_ALL) {
            echo "  # 后端\n";
            echo "  cd .../*-backend && composer install && php webman start\n";
        }
        if ($type === Config::TYPE_FRONTEND || $type === Config::TYPE_ALL) {
            echo "  # 管理端\n";
            echo "  cd .../*-frontend && pnpm install && pnpm run dev:ele\n";
        }
        if ($type === Config::TYPE_WEBSITE || $type === Config::TYPE_ALL) {
            echo "  # 官网\n";
            echo "  cd .../*-website && pnpm install && cp .env.example .env && pnpm dev\n";
        }
        echo "\n";
    }
}
