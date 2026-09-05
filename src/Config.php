<?php

declare(strict_types=1);

namespace Aorinex\AorinexNew;

final class Config
{
    /** 模板仓库中的默认项目名（改名时的旧值） */
    public const DEFAULT_TEMPLATE_NAME = 'aorinex-backend';

    /** 默认镜像仓库命名空间前缀（不含项目名） */
    public const DEFAULT_IMAGE_NAMESPACE = 'docker-images-registry.cn-shanghai.cr.aliyuncs.com/mirortho';

    /**
     * 仅在这些文件中做项目名替换（白名单，避免误伤业务注释 / vendor）。
     *
     * @var list<string>
     */
    public const RENAME_FILES = [
        'build.config.sh',
        'Dockerfile',
        'crontab_confg',
        'README.md',
    ];

    /**
     * 从本地模板复制时默认排除的目录/文件。
     *
     * @var list<string>
     */
    public const COPY_EXCLUDES = [
        '.git',
        'vendor',
        'runtime',
        'node_modules',
        '.env',
        'tests/tmp',
    ];

    public static function defaultTemplateRepo(): string
    {
        $fromEnv = getenv('AORINEX_NEW_TEMPLATE_REPO');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        // 发布到 Git 后改成真实地址；本地可用 --from 指向目录
        return 'https://github.com/aorinex/aorinex-backend.git';
    }

    public static function defaultTemplateRef(): string
    {
        $fromEnv = getenv('AORINEX_NEW_TEMPLATE_REF');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        return 'main';
    }

    public static function defaultImageNamespace(): string
    {
        $fromEnv = getenv('AORINEX_NEW_IMAGE_NAMESPACE');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return rtrim($fromEnv, '/');
        }

        return self::DEFAULT_IMAGE_NAMESPACE;
    }
}
