# aorinex-new

从 **aorinex** 后端（Webman）/ 管理端（Vue Vben Admin）/ 官网（Nuxt）模板一键创建新项目。

| 项 | 值 |
|----|-----|
| 包名 | `aorinex/aorinex-new` |
| 命令 | `aorinex-new` |
| 仓库 | https://github.com/aorinex/aorinex-new |

## 创建类型

| type | 输出 |
|------|------|
| `backend` | `<name>-backend/` |
| `frontend` | `<name>-frontend/` |
| `website`（别名 `nuxt`） | `<name>-website/`（与 backend 同级风格） |
| `all`（默认） | `<name>/` 下 backend + frontend + website |

## 本地试用

```bash
chmod +x /www/wwwroot/aorinex-new/bin/aorinex-new
ln -sf /www/wwwroot/aorinex-new/bin/aorinex-new /usr/local/bin/aorinex-new

# 三端一起（远程仓未就绪时用本地 --from）
aorinex-new my-app all \
  --backend-from /www/wwwroot/aorinex-backend \
  --frontend-from /www/wwwroot/aorinex-frontend \
  --website-from /www/wwwroot/aorinex-nuxt

# 只官网（Nuxt）
aorinex-new my-app website --website-from /www/wwwroot/aorinex-nuxt
# 或
aorinex-new my-app nuxt --nuxt-from /www/wwwroot/aorinex-nuxt

# 只后端
aorinex-new my-app backend --backend-from /www/wwwroot/aorinex-backend
```

## 默认远程模板

- 后端：`https://github.com/aorinex/aorinex-backend.git`
- 管理端：`https://github.com/ximengyi/aorinex-admin.git`
- 官网：`https://github.com/aorinex/aorinex-nuxt.git`

### 模板版本策略（重要）

默认 `--ref latest`：**每个远程仓各自取最新稳定 semver 标签**（优先正式版，忽略 `-rc`/`-beta` 等；若只有预发布标签才用预发布）。  
这样新建项目用的是发布过的稳定模板，而不是 `main` 上可能未完成的开发代码。

| 写法 | 行为 |
|------|------|
| （默认）/ `--ref latest` | 各仓最新稳定 tag |
| `--ref v1.2.0` | 固定该 tag |
| `--ref main` | 分支 tip（开发用） |

无任何 tag 时会警告并回退到 `main`。

组织仓未就绪时，请用对应的 `--*-from` / `--*-repo`。

## Packagist

```bash
composer global require aorinex/aorinex-new
aorinex-new my-app all
```
