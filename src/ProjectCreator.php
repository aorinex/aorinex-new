<?php

declare(strict_types=1);

namespace Aorinex\AorinexNew;

use RuntimeException;

/**
 * 创建单个后端 / 管理端 / 官网工程目录。
 */
final class ProjectCreator
{
    public function __construct(
        private readonly string $kind,
        private readonly string $projectName,
        private readonly string $baseName,
        private readonly string $targetDir,
        private readonly ?string $fromPath,
        private readonly string $templateRepo,
        private readonly string $templateRef,
        private readonly string $imageNamespace,
        private readonly string $oldName,
        private readonly bool $keepGit,
        private readonly bool $withComposer,
        private readonly bool $withPnpm,
    ) {
    }

    public function run(): void
    {
        $this->assertProjectName($this->projectName);

        if (file_exists($this->targetDir)) {
            throw new RuntimeException("目标目录已存在: {$this->targetDir}");
        }

        $parent = dirname($this->targetDir);
        if (!is_dir($parent)) {
            throw new RuntimeException("父目录不存在: {$parent}");
        }

        $this->fetchTemplate();
        $this->renameProject();
        $this->prepareEnv();
        $this->resetGit();

        if ($this->kind === Config::TYPE_BACKEND && $this->withComposer) {
            $this->runComposerInstall();
        }
        if (
            ($this->kind === Config::TYPE_FRONTEND || $this->kind === Config::TYPE_WEBSITE)
            && $this->withPnpm
        ) {
            $this->runPnpmInstall();
        }
    }

    private function assertProjectName(string $name): void
    {
        if (!preg_match('/^[a-z][a-z0-9-]*$/', $name)) {
            throw new RuntimeException(
                "项目名无效: {$name}\n请使用 kebab-case，例如: my-app、foo-shop"
            );
        }
    }

    private function fetchTemplate(): void
    {
        if ($this->fromPath !== null) {
            $this->copyFromLocal($this->fromPath, $this->targetDir);
            echo "[{$this->kind}] 已从本地模板复制: {$this->fromPath}\n";

            return;
        }

        $this->assertCommand('git');
        echo "[{$this->kind}] 正在克隆模板 {$this->templateRepo} ({$this->templateRef}) ...\n";
        $cmd = sprintf(
            'git clone --depth 1 --branch %s %s %s',
            escapeshellarg($this->templateRef),
            escapeshellarg($this->templateRepo),
            escapeshellarg($this->targetDir)
        );
        $this->execOrFail(
            $cmd,
            "[{$this->kind}] git clone 失败（请检查仓库地址，或使用 --backend-from / --frontend-from / --website-from）"
        );
        echo "[{$this->kind}] 克隆完成: {$this->targetDir}\n";
    }

    private function copyFromLocal(string $source, string $dest): void
    {
        $source = rtrim($source, '/');
        if (!is_dir($source)) {
            throw new RuntimeException("本地模板目录不存在: {$source}");
        }

        if (!mkdir($dest, 0755, true) && !is_dir($dest)) {
            throw new RuntimeException("无法创建目录: {$dest}");
        }

        $excludes = Config::COPY_EXCLUDES;
        $dirIterator = new \RecursiveDirectoryIterator(
            $source,
            \FilesystemIterator::SKIP_DOTS
        );
        $filtered = new \RecursiveCallbackFilterIterator(
            $dirIterator,
            static function (\SplFileInfo $current) use ($source, $excludes): bool {
                if ($current->getFilename() === '.env') {
                    return false;
                }
                $rel = substr($current->getPathname(), strlen($source) + 1);
                $rel = str_replace('\\', '/', $rel);
                foreach ($excludes as $ex) {
                    if ($rel === $ex || str_starts_with($rel, $ex . '/')) {
                        return false;
                    }
                }

                return true;
            }
        );
        $iterator = new \RecursiveIteratorIterator(
            $filtered,
            \RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $rel = substr($file->getPathname(), strlen($source) + 1);
            $to = $dest . DIRECTORY_SEPARATOR . $rel;
            if ($file->isDir()) {
                if (!is_dir($to) && !mkdir($to, 0755, true) && !is_dir($to)) {
                    throw new RuntimeException("无法创建目录: {$to}");
                }
                continue;
            }

            $dir = dirname($to);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException("无法创建目录: {$dir}");
            }
            if (!copy($file->getPathname(), $to)) {
                throw new RuntimeException("复制失败: {$rel}");
            }
        }
    }

    private function renameProject(): void
    {
        $files = match ($this->kind) {
            Config::TYPE_FRONTEND => Config::FRONTEND_RENAME_FILES,
            Config::TYPE_WEBSITE => Config::WEBSITE_RENAME_FILES,
            default => Config::BACKEND_RENAME_FILES,
        };

        $old = $this->oldName;
        $new = $this->projectName;
        $imageRepo = rtrim($this->imageNamespace, '/') . '/' . $new;

        foreach ($files as $rel) {
            $path = $this->targetDir . DIRECTORY_SEPARATOR . $rel;
            if (!is_file($path)) {
                echo "[{$this->kind}] 跳过（文件不存在）: {$rel}\n";
                continue;
            }

            $content = file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException("无法读取: {$rel}");
            }

            if ($this->kind === Config::TYPE_BACKEND && $rel === 'build.config.sh') {
                $content = $this->patchBuildConfig($content, $new, $imageRepo);
            } elseif (
                ($this->kind === Config::TYPE_FRONTEND || $this->kind === Config::TYPE_WEBSITE)
                && $rel === 'package.json'
            ) {
                $content = $this->patchPackageJsonName($content, $new);
            } elseif ($this->kind === Config::TYPE_WEBSITE) {
                $content = $this->patchWebsiteSiblingNames($content);
            } else {
                $content = str_replace($old, $new, $content);
            }

            if (str_starts_with(basename($rel), 'README')) {
                $content = $this->patchReadmeTitle($content, $new);
            }

            if (file_put_contents($path, $content) === false) {
                throw new RuntimeException("无法写入: {$rel}");
            }
            echo "[{$this->kind}] 已更新: {$rel}\n";
        }
    }

    private function patchWebsiteSiblingNames(string $content): string
    {
        $map = [
            Config::DEFAULT_WEBSITE_OLD_NAME => $this->projectName,
            'aorinex-backend' => $this->baseName . '-backend',
            'aorinex-frontend' => $this->baseName . '-frontend',
        ];

        return str_replace(array_keys($map), array_values($map), $content);
    }

    private function patchBuildConfig(string $content, string $projectName, string $imageRepo): string
    {
        $content = preg_replace(
            '/^IMAGE_REPOSITORY=.*$/m',
            'IMAGE_REPOSITORY="' . $imageRepo . '"',
            $content,
            1
        ) ?? $content;

        return preg_replace(
            '/^PROJECT_NAME=.*$/m',
            'PROJECT_NAME="' . $projectName . '"',
            $content,
            1
        ) ?? $content;
    }

    private function patchPackageJsonName(string $content, string $projectName): string
    {
        $data = json_decode($content, true);
        if (!is_array($data)) {
            $fallbackOld = $this->kind === Config::TYPE_WEBSITE
                ? Config::DEFAULT_WEBSITE_OLD_NAME
                : Config::DEFAULT_FRONTEND_OLD_NAME;

            return str_replace($fallbackOld, $projectName, $content);
        }
        $data['name'] = $projectName;
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('无法编码 package.json');
        }

        return $encoded . "\n";
    }

    private function patchReadmeTitle(string $content, string $projectName): string
    {
        $patched = preg_replace('/^#\s+.+$/m', '# ' . $projectName, $content, 1);
        if (is_string($patched)) {
            return $patched;
        }

        // Vben README 标题在 <h1> 里
        $patched = preg_replace(
            '/<h1>.*?<\/h1>/s',
            '<h1>' . htmlspecialchars($projectName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1>',
            $content,
            1
        );

        return is_string($patched) ? $patched : $content;
    }

    private function prepareEnv(): void
    {
        if ($this->kind === Config::TYPE_FRONTEND) {
            echo "[{$this->kind}] 请按需编辑 apps/web-ele/.env.development 中的接口代理地址\n";

            return;
        }

        $env = $this->targetDir . DIRECTORY_SEPARATOR . '.env';
        $example = $this->targetDir . DIRECTORY_SEPARATOR . '.env.example';

        if (is_file($env)) {
            echo "[{$this->kind}] .env 已存在，跳过\n";

            return;
        }
        if (!is_file($example)) {
            echo "[{$this->kind}] 无 .env.example，跳过生成 .env\n";

            return;
        }

        $content = file_get_contents($example);
        if ($content === false) {
            throw new RuntimeException('无法读取 .env.example');
        }

        if ($this->kind === Config::TYPE_BACKEND) {
            $secret = bin2hex(random_bytes(32));
            if (preg_match('/^JWT_SECRET=.*$/m', $content)) {
                $content = preg_replace('/^JWT_SECRET=.*$/m', 'JWT_SECRET=' . $secret, $content, 1) ?? $content;
            } else {
                $content .= "\nJWT_SECRET=" . $secret . "\n";
            }
        }

        if (file_put_contents($env, $content) === false) {
            throw new RuntimeException('无法写入 .env');
        }

        if ($this->kind === Config::TYPE_BACKEND) {
            echo "[{$this->kind}] 已生成 .env（含随机 JWT_SECRET）\n";
        } else {
            echo "[{$this->kind}] 已从 .env.example 生成 .env\n";
        }
    }

    private function resetGit(): void
    {
        $gitDir = $this->targetDir . DIRECTORY_SEPARATOR . '.git';
        if (is_dir($gitDir)) {
            $this->removeDir($gitDir);
            echo "[{$this->kind}] 已移除模板 .git\n";
        }

        if ($this->keepGit) {
            return;
        }

        $this->assertCommand('git');
        $cwd = getcwd();
        chdir($this->targetDir);
        try {
            $this->execOrFail('git init', 'git init 失败');
            echo "[{$this->kind}] 已 git init 新仓库\n";
        } finally {
            if ($cwd !== false) {
                chdir($cwd);
            }
        }
    }

    private function runComposerInstall(): void
    {
        $this->assertCommand('composer');
        $cwd = getcwd();
        chdir($this->targetDir);
        try {
            echo "[{$this->kind}] 正在 composer install ...\n";
            $this->execOrFail('composer install --no-interaction', 'composer install 失败');
        } finally {
            if ($cwd !== false) {
                chdir($cwd);
            }
        }
    }

    private function runPnpmInstall(): void
    {
        $this->assertCommand('pnpm');
        $cwd = getcwd();
        chdir($this->targetDir);
        try {
            echo "[{$this->kind}] 正在 pnpm install ...\n";
            $this->execOrFail('pnpm install', 'pnpm install 失败');
        } finally {
            if ($cwd !== false) {
                chdir($cwd);
            }
        }
    }

    private function assertCommand(string $command): void
    {
        $which = trim((string) shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null'));
        if ($which === '') {
            throw new RuntimeException("未找到命令: {$command}");
        }
    }

    private function execOrFail(string $command, string $errorMessage): void
    {
        $output = [];
        $code = 0;
        exec($command . ' 2>&1', $output, $code);
        if ($code !== 0) {
            $detail = implode("\n", $output);
            throw new RuntimeException($errorMessage . ($detail !== '' ? "\n" . $detail : ''));
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        /** @var \SplFileInfo $item */
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($dir);
    }
}
