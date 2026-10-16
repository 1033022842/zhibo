# 后台安全：IP 白名单 + 谷歌验证码(2FA)

上线于 2026-10-16（commit `aa2de22`）。默认**全部关闭**，需在后台自行开启。

## 入口
后台左侧「直播运营」目录下：
- **访问控制**（IP 白名单）：`https://sugus.it.com/admin/live.AccessSetting/index`
- **谷歌验证码**（2FA 绑定）：`https://sugus.it.com/admin/live.TotpSetup/index`

菜单为 iframe 型，通过后台面板内嵌打开（Referer 鉴权）；直连需带 `?_key=<ADMIN_FLOAT_KEY>`。

## 一、IP 白名单（应用层）

- 存储：表 `lp_admin_ip_whitelist`（`ip` 支持单个 IP 或 CIDR 网段，如 `1.2.3.4`、`10.0.0.0/8`）
- 总开关：`lp_platform_config.admin_ip_whitelist_enabled`（`1` 开 / `0` 关，**默认 0**）
- 生效范围：`/admin` 全部请求（登录页 + 接口）
- 放行规则：①开关关 → 全放；②`127.0.0.1`/`::1` 永远放；③URL 带 `?_ipkey=<ADMIN_FLOAT_KEY>` 放；④命中白名单放；⑤其余 **403**
- 异常兜底：中间件读库/校验出错一律**放行**（绝不因白名单把后台锁死）
- 开启保护：白名单为空时**拒绝开启**；建议先点「一键加入当前 IP」再加总闸

### 被锁死时的恢复（SSH 到 172.81.98.55）
```bash
# 1) 最省事：后台地址后加应急旁路直接进
#    https://sugus.it.com/admin/?_ipkey=<ADMIN_FLOAT_KEY>

# 2) 强制关闭总开关（一行）
cd /www/wwwroot/douyin/php && php -r '$e=parse_ini_file(".env",true);$d=$e["DATABASE"];$p=new PDO("mysql:host={$d["HOSTNAME"]};port={$d["HOSTPORT"]};dbname={$d["DATABASE"]};charset=utf8mb4",$d["USERNAME"],$d["PASSWORD"]);$p->exec("UPDATE lp_platform_config SET value=\"0\" WHERE `key`=\"admin_ip_whitelist_enabled\"");echo "whitelist OFF\n";'
```
> 注意：确认域名 `sugus.it.com` 是**直连无 CDN**，`request()->ip()` 即真实客户端 IP。若将来给后台域挂 CDN，需在 nginx 配 realip，否则会取到 CDN 回源 IP。

## 二、谷歌验证码（2FA）

- 实现：纯 PHP `app\common\library\Totp`（RFC 6238，base32 + HMAC-SHA1，无第三方依赖，已用 RFC 官方测试向量验证）
- 字段：`ba_admin` 新增 `totp_secret` / `totp_enabled` / `totp_recovery`（恢复码为 sha256 哈希数组）
- 总开关：`lp_platform_config.admin_totp_enabled`（`1` 开 / `0` 关，**默认 0**）
- 生效条件：总开关开 **且** 该账号已绑定（`totp_enabled=1` 且密钥非空）
- 登录：密码校验通过后，若需要 2FA 则校验 6 位动态码（±1 个 30s 窗口）；也接受一次性恢复码
- 恢复码：8 个，形如 `XXXX-XXXX`，**每个只能用一次**，用后从库中移除
- 绑定页：服务端生成密钥 → 页面用**同源** JS（`/static/js/qrcode-generator.js`）本地渲染二维码，**密钥不经过任何第三方** → 输入 6 位码确认启用
- 前端：登录页在 `totp_required=true` 时显示「动态验证码」输入框

### 手机丢失 / 无法验证时的恢复（SSH）
```bash
# 用恢复码登录：在登录页动态码框输入一个一次性恢复码（形如 4F7K-9Q2M）

# 或强制解绑某账号 2FA + 关闭总开关（一行，账号名换成 aiadmin/admin）
cd /www/wwwroot/douyin/php && php -r '$e=parse_ini_file(".env",true);$d=$e["DATABASE"];$p=new PDO("mysql:host={$d["HOSTNAME"]};port={$d["HOSTPORT"]};dbname={$d["DATABASE"]};charset=utf8mb4",$d["USERNAME"],$d["PASSWORD"]);$p->exec("UPDATE ba_admin SET totp_secret=\"\",totp_enabled=0,totp_recovery=\"[]\" WHERE username=\"admin\"");$p->exec("UPDATE lp_platform_config SET value=\"0\" WHERE `key`=\"admin_totp_enabled\"");echo "2FA OFF\n";'
```

## 菜单与权限
- `ba_admin_rule` 两条 menu（pid=「直播运营」122）：`live/accessSetting`、`live/totpSetup`
- 超级管理员（`admin`，规则 `*`）可见；其他分组需在「权限管理」手动勾选授权

## 部署顺序（本次已执行）
1. 执行 `php/sql/upgrade_admin_security.sql`（幂等，只新增）
2. `deploy_web.sh php`
3. `deploy_web.sh admin`（重建后台前端）
4. 验证：后台登录 → 绑定页启用 2FA → 再登录需输码 ✔
5. 白名单：确认无误后自行「一键加入当前 IP」并打开总开关
