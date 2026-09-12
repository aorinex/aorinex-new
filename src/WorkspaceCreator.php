<?php

declare(strict_types=1);

namespace Aorinex\AorinexNew;

use RuntimeException;

/**
 * 按 backend / frontend / all 组装输出目录。
 */
final class WorkspaceCreator
{
    public function __construct(
        private readonly string $type,
        private readonly string $baseName,
        private readonly string $rootDir,
        private readonly ?string $backendFrom,
        private readonly ?string $frontendFrom,
        private readonly string $backendRepo,
        private readonly string $frontendRepo,
        private readonly string $templateRef,
        private readonly string $imageNamespace,
        private readonly string $backendOldName,
        private readonly string $frontendOldName,
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
                '类型无效: ' . $this->type . '（可选: backend、frontend、all）'
            );
        }

        match ($this->type) {
            Config::TYPE_BACKEND => $this->createBackendOnly(),
            Config::TYPE_FRONTEND => $this->createFrontendOnly(),
            Config::TYPE_ALL => $this->createBoth(),
        };
    }

    private function createBackendOnly(): void
    {
        $name = $this->baseName . '-backend';
        $dir = $this->rootDir;
        // rootDir 默认已是 <base>-backend；若用户 -d 指定则用指定目录
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

    private function createBoth(): void
    {
        if (file_exists($this->rootDir)) {
            throw new RuntimeException("目标目录已存在: {$this->rootDir}");
        }
        if (!mkdir($this->rootDir, 0755, true) && !is_dir($this->rootDir)) {
            throw new RuntimeException("无法创建目录: {$this->rootDir}");
        }

        $backendName = $this->baseName . '-backend';
        $frontendName = $this->baseName . '-frontend';
        $backendDir = $this->rootDir . DIRECTORY_SEPARATOR . $backendName;
        $frontendDir = $this->rootDir . DIRECTORY_SEPARATOR . $frontendName;

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

        $this->writeRootReadme($backendName, $frontendName);
        $this->printDone([$backendDir, $frontendDir], Config::TYPE_ALL);
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

    private function writeRootReadme(string $backendName, string $frontendName): void
    {
        $content = <<<MD
# {$this->baseName}

由 `aorinex-new` 从 aorinex 模板生成。

| 目录 | 说明 |
|------|------|
| `{$backendName}/` | Webman 后端 |
| `{$frontendName}/` | Vue Vben 前端（web-ele） |

## 后端

```bash
cd {$backendName}
composer install
# 编辑 .env
php webman start
```

## 前端

```bash
cd {$frontendName}
pnpm install
# 编辑 apps/web-ele/.env.development 中的 VITE_DEV_PROXY_TARGET
pnpm run dev:ele
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
            echo "  # 前端\n";
            echo "  cd .../*-frontend && pnpm install && pnpm run dev:ele\n";
        }
        echo "\n";
    }
}
