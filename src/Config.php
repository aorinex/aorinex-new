<?php

declare(strict_types=1);

namespace Aorinex\AorinexNew;

final class Config
{
    public const TYPE_BACKEND = 'backend';
    public const TYPE_FRONTEND = 'frontend';
    public const TYPE_WEBSITE = 'website';
    public const TYPE_ALL = 'all';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_BACKEND,
        self::TYPE_FRONTEND,
        self::TYPE_WEBSITE,
        self::TYPE_ALL,
    ];

    /**
     * CLI 别名 → 规范 type。
     *
     * @var array<string, string>
     */
    public const TYPE_ALIASES = [
        'nuxt' => self::TYPE_WEBSITE,
    ];

    public const DEFAULT_BACKEND_OLD_NAME = 'aorinex-backend';
    public const DEFAULT_FRONTEND_OLD_NAME = 'aorinex-admin';
    public const DEFAULT_WEBSITE_OLD_NAME = 'aorinex-nuxt';

    public const DEFAULT_IMAGE_NAMESPACE = 'docker-images-registry.cn-shanghai.cr.aliyuncs.com/mirortho';

    /**
     * 默认模板引用：解析各远程仓库的最新稳定 tag（非 main 分支 tip）。
     * 可用 --ref / AORINEX_NEW_TEMPLATE_REF 覆盖为具体 tag 或分支名。
     */
    public const REF_LATEST = 'latest';

    /**
     * 后端改名白名单。
     *
     * @var list<string>
     */
    public const BACKEND_RENAME_FILES = [
        'build.config.sh',
        'Dockerfile',
        'crontab_confg',
        'README.md',
    ];

    /**
     * 前端改名白名单。
     *
     * @var list<string>
     */
    public const FRONTEND_RENAME_FILES = [
        'package.json',
        'README.md',
        'README.zh-CN.md',
    ];

    /**
     * 官网（Nuxt）改名白名单（业务/README；AI 指引由 AI_GUIDANCE_* 扫描处理）。
     *
     * @var list<string>
     */
    public const WEBSITE_RENAME_FILES = [
        'package.json',
        'README.md',
        'app/components/SiteFooter.vue',
    ];

    /**
     * 各端 AI 指引：根级单文件（存在则改写兄弟仓名）。
     *
     * @var list<string>
     */
    public const AI_GUIDANCE_ROOT_FILES = [
        'AGENTS.md',
        'CLAUDE.md',
        'GEMINI.md',
        '.github/copilot-instructions.md',
    ];

    /**
     * 各端 AI 指引：目录 → 扩展名（递归扫描，存在则改写兄弟仓名）。
     *
     * @var array<string, list<string>>
     */
    public const AI_GUIDANCE_SCAN_DIRS = [
        '.cursor/rules' => ['mdc'],
        '.cursor/skills' => ['md'],
        '.claude/skills' => ['md'],
        '.agents/skills' => ['md'],
    ];

    /**
     * 从本地模板复制时排除的顶层目录/文件。
     *
     * @var list<string>
     */
    public const COPY_EXCLUDES = [
        '.git',
        'vendor',
        'runtime',
        'node_modules',
        'dist',
        '.turbo',
        '.output',
        '.nuxt',
        '.nitro',
        '.data',
        'coverage',
        'tests/tmp',
    ];

    public static function normalizeType(string $type): string
    {
        return self::TYPE_ALIASES[$type] ?? $type;
    }

    public static function defaultBackendRepo(): string
    {
        return self::envOr(
            'AORINEX_NEW_BACKEND_REPO',
            'AORINEX_NEW_TEMPLATE_REPO',
            'https://github.com/aorinex/aorinex-backend.git'
        );
    }

    public static function defaultFrontendRepo(): string
    {
        // 组织仓 aorinex/aorinex-admin 就绪后可改回；当前公开仓在个人账号下
        return self::envOr(
            'AORINEX_NEW_FRONTEND_REPO',
            null,
            'https://github.com/ximengyi/aorinex-admin.git'
        );
    }

    public static function defaultWebsiteRepo(): string
    {
        return self::envOr(
            'AORINEX_NEW_WEBSITE_REPO',
            'AORINEX_NEW_NUXT_REPO',
            'https://github.com/aorinex/aorinex-nuxt.git'
        );
    }

    public static function defaultTemplateRef(): string
    {
        return self::envOr('AORINEX_NEW_TEMPLATE_REF', null, self::REF_LATEST);
    }

    public static function isLatestRef(string $ref): bool
    {
        $normalized = strtolower(trim($ref));

        return $normalized === self::REF_LATEST
            || $normalized === '@latest'
            || $normalized === 'latest-tag';
    }

    public static function defaultImageNamespace(): string
    {
        $value = self::envOr('AORINEX_NEW_IMAGE_NAMESPACE', null, self::DEFAULT_IMAGE_NAMESPACE);

        return rtrim($value, '/');
    }

    public static function defaultBackendFrom(): ?string
    {
        $value = getenv('AORINEX_NEW_BACKEND_FROM');
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    public static function defaultFrontendFrom(): ?string
    {
        $value = getenv('AORINEX_NEW_FRONTEND_FROM');
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    public static function defaultWebsiteFrom(): ?string
    {
        foreach (['AORINEX_NEW_WEBSITE_FROM', 'AORINEX_NEW_NUXT_FROM'] as $key) {
            $value = getenv($key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function envOr(string $primary, ?string $fallback, string $default): string
    {
        $value = getenv($primary);
        if (is_string($value) && $value !== '') {
            return $value;
        }
        if ($fallback !== null) {
            $value = getenv($fallback);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $default;
    }
}
