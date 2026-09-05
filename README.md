# aorinex-new

从 **aorinex Webman 后台模板**一键创建新项目（体验类似 `laravel new`）。

| 项 | 值 |
|----|-----|
| 包名 | `aorinex/aorinex-new` |
| 命令 | `aorinex-new` |
| 目录 | `/www/wwwroot/aorinex-new` |

## 它做什么

1. `git clone` 模板仓库（或 `--from` 复制本地模板）
2. 按白名单改名：`build.config.sh`、`Dockerfile`、`crontab_confg`、`README.md`
3. 生成 `.env`（从 `.env.example`，并写入随机 `JWT_SECRET`）
4. 去掉模板 `.git`，再 `git init`（可用 `--keep-git` 跳过）

## 本地立刻试用（未上 Packagist 前）

```bash
# 方式 A：直接跑 bin（无需 composer）
chmod +x /www/wwwroot/aorinex-new/bin/aorinex-new
/www/wwwroot/aorinex-new/bin/aorinex-new my-demo \
  --from /www/wwwroot/aorinex-backend

# 方式 B：加到 PATH，任意目录可用
sudo ln -sf /www/wwwroot/aorinex-new/bin/aorinex-new /usr/local/bin/aorinex-new
aorinex-new my-demo --from /www/wwwroot/aorinex-backend
```

创建后：

```bash
cd my-demo
composer install   # 若未加 --with-composer
# 编辑 .env 数据库配置
php webman start
```

## 常用参数

```text
aorinex-new <project-name> [选项]

  --from <path>           本地模板路径（内网推荐）
  --repo <url>            远程模板 Git 地址
  --ref / --branch <ref>  分支或 tag
  --image-namespace <n>   镜像前缀，如 registry/.../mirortho
  --with-composer         创建后自动 composer install
  -d, --directory <dir>   自定义输出目录
```

环境变量：`AORINEX_NEW_TEMPLATE_REPO`、`AORINEX_NEW_TEMPLATE_REF`、`AORINEX_NEW_IMAGE_NAMESPACE`。

## 发布到 Packagist（稍后可再细讲）

准备就绪后大致步骤：

1. 把本仓库推到 GitHub/GitLab（建议公开或 Packagist 能拉到）
2. 在 [packagist.org](https://packagist.org) 登录 → Submit → 填仓库 URL
3. 同事安装：

```bash
composer global require aorinex/aorinex-new
# 确保 ~/.config/composer/vendor/bin 在 PATH
aorinex-new my-app --repo <你的模板仓库地址>
```

> 注意：Packagist 的 vendor 名 `aorinex` 需与你在 Packagist/GitHub 上的账号或组织一致；若要用公司名，把 `composer.json` 里的 `name` 改成 `你的vendor/aorinex-new` 即可。

## 默认模板地址

当前默认 clone：

`https://github.com/aorinex/aorinex-backend.git`

模板尚未推到该地址时，请用 `--from` 或 `--repo` / 环境变量覆盖。
