<?php

declare(strict_types=1);

namespace Aorinex\AorinexNew;

use RuntimeException;

final class ProjectCreator
{
    public function __construct(
        private readonly string $projectName,
        private readonly string $targetDir,
        private readonly ?string $fromPath,
        private readonly string $templateRepo,
        private readonly string $templateRef,
        private readonly string $imageNamespace,
        private readonly string $oldName,
        private readonly bool $keepGit,
        private readonly bool $withComposer,
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

        if ($this->withComposer) {
            $this->runComposerInstall();
        }

        $this->printNextSteps();
    }

    private function assertProjectName(string $name): void
    {
        if (!preg_match('/^[a-z][a-z0-9-]*$/', $name)) {
            throw new RuntimeException(
                "项目名无效: {$name}\n请使用 kebab-case，例如: my-backend、foo-api"
            );
        }
    }

    private function fetchTemplate(): void
    {
        if ($this->fromPath !== null) {
            $this->copyFromLocal($this->fromPath, $this->targetDir);
            echo "已从本地模板复制: {$this->fromPath}\n";

            return;
        }

        $this->assertCommand('git');
        $repo = $this->templateRepo;
        $ref = $this->templateRef;
        $target = $this->targetDir;

        echo "正在克隆模板 {$repo} ({$ref}) ...\n";
        $cmd = sprintf(
            'git clone --depth 1 --branch %s %s %s',
            escapeshellarg($ref),
            escapeshellarg($repo),
            escapeshellarg($target)
        );
        $this->execOrFail($cmd, 'git clone 失败（请检查仓库地址、分支权限，或改用 --from 本地路径）');
        echo "克隆完成: {$target}\n";
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
        $old = $this->oldName;
        $new = $this->projectName;
        $imageRepo = rtrim($this->imageNamespace, '/') . '/' . $new;

        foreach (Config::RENAME_FILES as $rel) {
            $path = $this->targetDir . DIRECTORY_SEPARATOR . $rel;
            if (!is_file($path)) {
                echo "跳过（文件不存在）: {$rel}\n";
                continue;
            }

            $content = file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException("无法读取: {$rel}");
            }

            if ($rel === 'build.config.sh') {
                $content = $this->patchBuildConfig($content, $new, $imageRepo);
            } else {
                $content = str_replace($old, $new, $content);
            }

            if ($rel === 'README.md') {
                $content = preg_replace(
                    '/^#\s+.+$/m',
                    '# ' . $new,
                    $content,
                    1
                ) ?? $content;
            }

            if (file_put_contents($path, $content) === false) {
                throw new RuntimeException("无法写入: {$rel}");
            }
            echo "已更新: {$rel}\n";
        }
    }

    private function patchBuildConfig(string $content, string $projectName, string $imageRepo): string
    {
        $content = preg_replace(
            '/^IMAGE_REPOSITORY=.*$/m',
            'IMAGE_REPOSITORY="' . $imageRepo . '"',
            $content,
            1
        ) ?? $content;

        $content = preg_replace(
            '/^PROJECT_NAME=.*$/m',
            'PROJECT_NAME="' . $projectName . '"',
            $content,
            1
        ) ?? $content;

        return $content;
    }

    private function prepareEnv(): void
    {
        $env = $this->targetDir . DIRECTORY_SEPARATOR . '.env';
        $example = $this->targetDir . DIRECTORY_SEPARATOR . '.env.example';

        if (is_file($env)) {
            echo ".env 已存在，跳过\n";
            return;
        }
        if (!is_file($example)) {
            echo "无 .env.example，跳过生成 .env\n";
            return;
        }

        $content = file_get_contents($example);
        if ($content === false) {
            throw new RuntimeException('无法读取 .env.example');
        }

        $secret = bin2hex(random_bytes(32));
        if (preg_match('/^JWT_SECRET=.*$/m', $content)) {
            $content = preg_replace('/^JWT_SECRET=.*$/m', 'JWT_SECRET=' . $secret, $content, 1) ?? $content;
        } else {
            $content .= "\nJWT_SECRET=" . $secret . "\n";
        }

        if (file_put_contents($env, $content) === false) {
            throw new RuntimeException('无法写入 .env');
        }
        echo "已生成 .env（含随机 JWT_SECRET）\n";
    }

    private function resetGit(): void
    {
        $gitDir = $this->targetDir . DIRECTORY_SEPARATOR . '.git';
        if (is_dir($gitDir)) {
            $this->removeDir($gitDir);
            echo "已移除模板 .git\n";
        }

        if ($this->keepGit) {
            return;
        }

        $this->assertCommand('git');
        $cwd = getcwd();
        chdir($this->targetDir);
        try {
            $this->execOrFail('git init', 'git init 失败');
            echo "已 git init 新仓库\n";
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
            echo "正在 composer install ...\n";
            $this->execOrFail('composer install --no-interaction', 'composer install 失败');
        } finally {
            if ($cwd !== false) {
                chdir($cwd);
            }
        }
    }

    private function printNextSteps(): void
    {
        $name = $this->projectName;
        echo "\n";
        echo "✓ 项目已创建: {$this->targetDir}\n";
        echo "\n下一步:\n";
        echo "  cd {$name}\n";
        if (!$this->withComposer) {
            echo "  composer install\n";
        }
        echo "  # 编辑 .env 中的数据库 / Redis\n";
        echo "  php webman start\n";
        echo "  # 或打包镜像: bash build.sh\n";
        echo "\n";
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
