# PHP Critical RCE 修补记录（USN-8948-1 / USN-8948-2）

> 执行日期：2026-10-22
> 影响：sugus / heygirl / kisss / kelymi 四站全部

## 一、漏洞

Ubuntu 于 2026-10-21 发布 **USN-8948-1 / USN-8948-2**，两个 CVE 均为 **Critical** 级远程代码执行：

| CVE | 描述 | 危害 |
|---|---|---|
| **CVE-2026-109618** | 内存耗尽（OOM）路径下，请求清理把"部分初始化"的内部结构当成有效数据处理 | 远程执行任意代码 |
| **CVE-2026-109619** | worker 复用时，上一个请求的残留状态在其内存已释放后仍被下一个请求使用，且发生在正常初始化重置之前 | 远程执行任意代码 |

> **为什么 CVE-2026-109619 对本站特别危险**：它专门针对 **PHP-FPM worker 复用**模型。
> 四站 pool 均为 `pm = dynamic` + `pm.max_requests = 800`，即每个 worker 连续处理 800 个请求，
> 复用频率极高。四站均公网可达、均有文件上传接口，属于直接可利用面。

## 二、修复版本

| 发行版 | 站点 | 修复版本 |
|---|---|---|
| Ubuntu 24.04 noble | sugus / heygirl / kelymi | `8.3.6-0ubuntu0.24.04.13` |
| ondrej PPA (jammy) | kisss | `8.3.36`（PPA 后缀 `8.3.36-1+...`；sury 源 `8.3.36-1+0~20261021.88+...`）|

## 三、执行结果

| 站点 | 升级前 | 升级后 | 状态 |
|---|---|---|---|
| sugus `172.81.98.55` | `8.3.6-0ubuntu0.24.04.11` | `8.3.6-0ubuntu0.24.04.13` | ✅ |
| heygirl `172.81.99.11` | `8.3.6-0ubuntu0.24.04.11` | `8.3.6-0ubuntu0.24.04.13` | ✅ |
| kelymi `172.81.99.42` | `8.3.6-0ubuntu0.24.04.11` | `8.3.6-0ubuntu0.24.04.13` | ✅ |
| kisss `103.217.252.52` | `8.3.35-1+ubuntu22.04.1+deb.sury.org+1` | `8.3.36-1+0~20261021.88+ubuntu22.04~1.gbp250f7c` | ✅ |

执行顺序：kisss（验证流程）→ kelymi → heygirl → sugus（主力站最后）。
全程 `systemctl reload php8.3-fpm`（不断连接），无停机。

## 四、两个必须记住的坑

### 坑 1：24.04 三台的 apt 源里没有 `noble-security`

```bash
# 升级前的源配置（错误状态）
/etc/apt/sources.list.d/ubuntu.sources
Suites: noble noble-updates          # ← 缺 noble-security
```

后果：`apt-cache policy php8.3-fpm` 候选版本是 **`.12`**，而修复版本是 **`.13`**。
**直接 `apt upgrade` 会显示"升级成功"、版本号也确实变了，但漏洞仍然存在。**

修复：单独加一个源文件（不动 `ubuntu.sources`，避免其他包行为变化）：

```bash
cat > /etc/apt/sources.list.d/ubuntu-security.sources <<'EOF'
Types: deb
URIs: http://security.ubuntu.com/ubuntu
Suites: noble-security
Components: main universe
Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg
EOF
apt-get update
```

> HK 镜像（`hk.archive.ubuntu.com`）的 `noble-updates` 会滞后，安全更新一定从
> `security.ubuntu.com` 取。

### 坑 2：launchpad PPA 对 kisss 返回 503

```
E: Failed to fetch https://ppa.launchpadcontent.net/ondrej/php/ubuntu/...php8.3-zip...503 Service Unavailable
```

解决：改用作者官方源 `packages.sury.org`（实测 200 / 0.18s，比 launchpad 快得多）：

```bash
curl -fsSL https://packages.sury.org/php/apt.gpg -o /usr/share/keyrings/deb.sury.org-php.gpg
echo 'deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ jammy main' \
  > /etc/apt/sources.list.d/sury-php.list
mv /etc/apt/sources.list.d/ondrej-ubuntu-php-jammy.list /root/ondrej-ppa.disabled
apt-get update
```

> sury 与 launchpad PPA 是同一作者、同一构建，版本号后缀不同但内容一致。

## 五、安全点：php.ini 不受 dpkg 托管

```bash
dpkg -S /etc/php/8.3/fpm/php.ini
# dpkg-query: no path found matching pattern /etc/php/8.3/fpm/php.ini
```

所以升级**不会覆盖**我们调过的参数。升级后实测四站参数原样保留：

| 站点 | max_execution_time | memory_limit | post_max_size | upload_max_filesize |
|---|---|---|---|---|
| sugus | 600 | 512M | 512M | 512M |
| heygirl | 300 | 256M | 512M | 512M |
| kelymi | 300 | 256M | 512M | 512M |
| kisss | 30 | 256M | 64M | 64M |

> kisss 的 64M/30s 曾在 2026-10-22 当天一并调到 `512M / 300s`（nginx 也是 `512m`），
> 四站现已一致。详见「七、kisss 上传限制已对齐」。

## 六、验证结果

### 扩展完整性（升级会重装全部 `php8.3-*` 包）

四站 `pdo_mysql / mbstring / curl / gd / zip / xml / bcmath / opcache / intl` **全部在位**。
opcache：FPM 已启用（`opcache.enable => On`），CLI 关闭是默认行为，正常。

### 技术冒烟

| 检查项 | sugus | heygirl | kisss | kelymi |
|---|---|---|---|---|
| `/ai/Home.html` | 200 | 200 | 200 | 200 |
| `/ai/Image.html` | 200 | 200 | 200 | 200 |
| `/ai/Chat.html` | 200 | 200 | — | — |
| `/api/customVideo/stats` | `00000` | `00000` | `00000` | `00000` |
| `php think ai:task:cron` | 正常 | 正常 | 正常 | 正常 |
| PDO 直连查询 | 116 条 | 79 条 | 0 条 | 1 条 |
| gd / zip / mbstring 实测 | ok | ok | ok | ok |
| fpm 错误日志（20min） | 0 | 0 | 0 | 0 |

### 业务链路

- 登录 API 返回结构化业务错误（`B0102 用户名或密码错误`）而非 500
  → 路由、DB 连接、bcrypt 校验、JSON 序列化全链路正常。
- **GPU worker 联动**：四站 `customVideo/pending` 均 200，`gpu-health` 全绿，
  四站积压均为 0 —— 昨天上线的单卡四站方案未受影响。

## 七、kisss 上传限制已对齐（2026-10-22）

kisss 原本是四站里唯一的小额度配置，AI 视频成品回传有 500 风险。已调到与其余三站一致：

| 项 | 改前 | 改后 |
|---|---|---|
| `upload_max_filesize` (fpm + cli) | 64M | **512M** |
| `post_max_size` (fpm + cli) | 64M | **512M** |
| `max_execution_time` (fpm) | 30 | **300** |
| nginx `client_max_body_size` | 64m | **512m** |

### ⚠️ 改 nginx 时踩的坑：`sites-enabled` 是实体文件，不是软链

kisss 的 `/etc/nginx/sites-enabled/kiss.conf` 是**实体文件**（其它站通常是 `sites-available` 的软链），
而且比 `sites-available/kiss.conf` **新**（多了 `kisss.si` 跳转、HLS `open_file_cache off`、http2）。
一开始只改了 `sites-available`，`nginx -s reload` 后 `nginx -T` 里依然是 64m —— 等于没改。

**判断方法**：
```bash
ls -l /etc/nginx/sites-enabled/        # 看是 lrwxrwxrwx 还是 -rw-r--r--
nginx -T | grep client_max_body_size   # 以实际加载的配置为准，不看文件
```

### 验证（不只改配置，要实测）

```
FPM 运行时： upload_max_filesize=512M  post_max_size=512M  max_execution_time=300
nginx -T  ： client_max_body_size 512m 已加载
70MB 上传 ： 旧限制会 413，现在 nginx 完整接收 73,400,518 B
```

### 顺带修好的一个小问题

kisss 的 nginx 把所有 `.php` 请求都 `fastcgi_param SCRIPT_FILENAME .../index.php`（ThinkPHP 单入口），
所以往 `public/` 放临时探针 PHP 不会被执行，而是被 ThinkPHP 接走（302 到 `/install/`）。
排查这类问题时别被误导 —— 该站的 PHP 行为验证一律走真实 API。

### 实测业务链路

插入一条真实的 `custom_image` 任务 → GPU worker 接单 → 生成 → 上传 → 回调：

```
18:42:52 [kiss] [task 2] 已接单: kind=image style=image_edit wf=qwenedit_api.json
18:43:25 [kiss] [task 2] 图片已生成: task_2_00001_.png
18:43:27 [kiss] [task 2] 完成 -> https://media.kisss.ai/sec/kiss/.../ci_kiss_*.png
```

DB `status=completed`、`result_type=image_file`，产物经 CDN 返回 **200**。
测试数据已清理（该站 `lp_ai_task` 归零）。

## 八、待办

1. ~~kisss 上传限制偏低~~ —— **已完成**，见「七」。
2. **恢复 launchpad PPA？** 不需要。`sury-php.list` 是作者官方源，长期更稳；
   `ondrej-ppa.disabled` 留在 `/root/` 备查即可。
3. **后续安全更新**：三台 24.04 已补上 `noble-security` 源，
   以后 `apt upgrade` 就能直接拿到安全更新，不会再出现"升了但没修上"。

## 九、回滚

每台升级前都留了完整备份：

```bash
# 备份位置
/root/php_sec_<site>_20261022/
  ├── php.ini
  ├── www.conf
  ├── versions.txt        # 41~42 个 php8.3-* 包的精确版本
  └── upgrade.log

# 精确降级（按 versions.txt 逐包指定旧版本）
apt-get install -y --allow-downgrades \
  $(awk '{print $1"="$2}' /root/php_sec_<site>_20261022/versions.txt)
systemctl reload php8.3-fpm
```

kisss 额外备份：`/root/php_sec_kisss_20261022020153/`（第一次尝试时的记录）。

## 十、工具

```bash
python .zcode/php_security_patch.py --check              # 只读体检（四站一屏）
python .zcode/php_security_patch.py --apply --site sugus # 升级单站
python .zcode/php_security_patch.py --rollback --site sugus
```

脚本内置保护：
- 候选版本低于修复版本 → **直接中止**，不假装修复成功
- 升级前自动备份 php.ini / www.conf / 逐包版本
- 升级后校验 php.ini 未被改动
- 冒烟不过 → 自动回滚
- 冒烟走**公网域名**（kisss 的 IP 直连是 `default_server{return 404}`，会误判）
