# aorinex-new

从 **aorinex** 后端（Webman）/ 前端（Vue Vben Admin）模板一键创建新项目。

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
| `all`（默认） | `<name>/<name>-backend` + `<name>/<name>-frontend` |

## 本地试用

```bash
chmod +x /www/wwwroot/aorinex-new/bin/aorinex-new
ln -sf /www/wwwroot/aorinex-new/bin/aorinex-new /usr/local/bin/aorinex-new

# 前后端一起（推荐本地 --from，远程仓未就绪时）
aorinex-new my-app all \
  --backend-from /www/wwwroot/aorinex-backend \
  --frontend-from /www/wwwroot/aorinex-admin

# 只后端
aorinex-new my-app backend --backend-from /www/wwwroot/aorinex-backend

# 只前端（公开仓示例）
aorinex-new my-app frontend \
  --frontend-repo https://github.com/ximengyi/aorinex-admin.git
```

## 默认远程模板

- 后端：`https://github.com/aorinex/aorinex-backend.git`
- 前端：`https://github.com/ximengyi/aorinex-admin.git`（迁到组织 `aorinex/aorinex-admin` 后改 `Config` 或环境变量即可）

后端组织仓未就绪时，请用 `--backend-from` / `--backend-repo`。

## Packagist

```bash
composer global require aorinex/aorinex-new
aorinex-new my-app all
```
