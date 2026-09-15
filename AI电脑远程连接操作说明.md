# AI 电脑远程连接操作说明

> 适用机器：AI 电脑（局域网 IP 192.168.31.225，Windows，用户 administrator）
> 前置依赖：操作机需有 SSH 客户端（Windows 自带 OpenSSH 即可）
> 更新日期：2026-09-10

---

## 一、三种远程方式概览

| 方式 | 场景 | 特点 |
|---|---|---|
| **① 局域网 SSH 直连** | 和 AI 电脑同一网络（192.168.31.x） | 最快最稳，日常首选 |
| **② 反向隧道经服务器中转** | 在外网/异地（任何能上网的地方） | 走服务器跳板，AI 电脑主动外连不依赖公网 IP |
| **③ 向日葵远程桌面** | 需要 GUI 图形界面操作 | 键鼠直接控制桌面，适合改文件/看画面 |

---

## 二、方式一：局域网 SSH 直连（日常首选）

### 连接命令

```bash
ssh administrator@192.168.31.225
```

### 原理

- AI 电脑装有 **Windows OpenSSH Server**（sshd 服务，已设开机自启）
- 操作机的公钥（`~/.ssh/id_rsa.pub`，`id_ed25519.pub`）已装入 AI 电脑的
  `C:\ProgramData\ssh\administrators_authorized_keys`（管理员账户专用授权文件）→ **免密登录**

### 常用操作示例

```bash
# 执行命令
ssh administrator@192.168.31.225 "tasklist | findstr /i ffmpeg"

# 传文件到 AI 电脑
scp 本地文件 administrator@192.168.31.225:"D:/目标路径/"

# 从 AI 电脑拉文件
scp administrator@192.168.31.225:"D:/ever/douyin/worker.log" ./

# 看 worker 日志尾部
ssh administrator@192.168.31.225 "powershell -Command \"Get-Content D:\ever\douyin\worker.log -Tail 50 -Encoding Default\""
```

### 前提与限制

- **必须与 AI 电脑在同一局域网**（连同一个路由器，操作机 IP 为 192.168.31.x）
- AI 电脑是 DHCP，**IP 可能变**。连不上时先 `ping 192.168.31.225`，不通就扫网段找 22 端口：
  ```bash
  for i in $(seq 1 254); do (timeout 1 bash -c "echo > /dev/tcp/192.168.31.$i/22" 2>/dev/null && echo "SSH: 192.168.31.$i") & done; wait
  ```

---

## 三、方式二：外网反向隧道（经服务器中转）

### 架构

```
操作机（外网任意位置）
   │  ① 先登录服务器 38.181.44.164
   │  ② 经服务器上的隧道端口 12225 连到 AI 电脑
   ▼
服务器 38.181.44.164（公网跳板）
   ▲
   │  ③ AI 电脑主动外连建立的隧道（AI→服务器方向，无需 AI 电脑有公网IP）
   │     ssh -N -R 12225:localhost:22 root@38.181.44.164
AI 电脑（tunnel_loop.bat 常驻循环，断线 5 秒自动重连）
```

### 一条命令直连（操作机执行）

```bash
ssh -i ~/.ssh/id_rsa \
    -o ProxyCommand="ssh -i ~/.ssh/douyin_38_181 -o StrictHostKeyChecking=no -W %h:%p root@38.181.44.164" \
    -p 12225 administrator@127.0.0.1
```

> 说明：ProxyCommand 先连服务器（用服务器密钥 douyin_38_181），再经服务器本机 12225 端口（隧道出口）到达 AI 电脑，用操作机私钥 id_rsa 完成对 AI 电脑的认证。**此命令在外网/异地同样可用。**

### 隧道的自启动与自愈（已配置好，无需手动管）

| 保障 | 机制 |
|---|---|
| 开机自启 | 计划任务 `AITunnelKeep`（登录触发）+ Startup 文件夹的 `tunnel_loop.bat` 副本，双保险 |
| 断线重连 | `tunnel_loop.bat` 内部死循环：ssh 断开后 5 秒自动重连 |
| 相关文件 | AI 电脑 `C:\Users\Administrator\.ssh\tunnel_loop.bat`（循环脚本）+ `ai_tunnel_key`（连服务器的私钥） |

### 验证隧道是否活着

```bash
# 在服务器上查 12225 是否监听（有输出=活着）
ssh -i ~/.ssh/douyin_38_181 root@38.181.44.164 "ss -tln | grep 12225"

# 隧道断了？在局域网内连 AI 电脑手动拉起：
ssh administrator@192.168.31.225 "schtasks /run /tn AITunnelKeep"
```

---

## 四、方式三：向日葵远程桌面

- AI 电脑已装向日葵客户端，用团队账号登录后可直接图形界面控制
- 适用场景：改 bat 文件（要 GBK 编码记事本最稳）、看推流画面、ComfyUI 等图形程序
- SSH 能做的事优先用 SSH（快、可脚本化），GUI 需求再用向日葵

---

## 五、凭据与密钥清单

| 密钥/凭据 | 存放位置 | 用途 |
|---|---|---|
| `~/.ssh/id_rsa`（操作机私钥） | 操作机 | 免密登录 AI 电脑（局域网直连 & 隧道两种方式都用它） |
| `~/.ssh/douyin_38_181`（操作机私钥） | 操作机 | 免密登录服务器 38.181.44.164 |
| `ai_tunnel_key`（AI 电脑私钥） | AI 电脑 `C:\Users\Administrator\.ssh\` | AI 电脑 → 服务器的隧道认证（公钥已装服务器 `authorized_keys`，注释 ai-pc-tunnel） |
| 服务器 root 密码 | 见部署指南 | 备用（密钥失效时） |
| AI 电脑 administrators_authorized_keys | `C:\ProgramData\ssh\administrators_authorized_keys` | 已装入操作机 id_rsa.pub 和 id_ed25519.pub |
| 服务器 Redis 密码 | `redis4live2026` | 礼物队列等 |
| 数据库 zhibo | zhibo / 12345678 @38.181.44.164 | 播单/素材/订单 |

---

## 六、常见问题

### 1. AI 电脑重启后连不上（局域网）
- 重启不会自动跑推流（AIWorkerRoom1 是手动任务）——SSH 连上后执行：
  `ssh administrator@192.168.31.225 "schtasks /run /tn AIWorkerRoom1"`
- 隧道会随登录自启（AITunnelKeep），若没有：`schtasks /run /tn AITunnelKeep`

### 2. 外网隧道 12225 不监听
按顺序：
1. AI 电脑是否开机且已登录（隧道是登录态任务）
2. 局域网连上去看进程：`tasklist | findstr /i ssh`（应有外连的 ssh.exe）
3. 手动拉起：`schtasks /run /tn AITunnelKeep`
4. 服务器侧排查：`tail -20 /var/log/auth.log | grep ai-pc-tunnel`（有无认证记录）

### 3. SSH 提示 host key 变化（REMOTE HOST IDENTIFICATION HAS CHANGED）
AI 电脑重装系统/换 IP 后会出现，执行：
```bash
ssh-keygen -R 192.168.31.225
ssh-keygen -R "[127.0.0.1]:12225"   # 隧道方式用
```

### 4. 权限被拒（Permission denied）
- 确认用的是 `id_rsa`（隧道跳板命令里别写错成别的 key）
- AI 电脑端检查授权文件：`type C:\ProgramData\ssh\administrators_authorized_keys`（应有操作机公钥）

---

## 七、本地大模型服务（2026-09-15 上线；09-15 晚已暂停让位换脸）

> **状态：已暂停**——5090 显存让位给本地视频模型（换脸/角色动画）。聊天已切回 GLM 云端（172 .env [LLM]，本地配置以注释保留）。
> 恢复方法：`schtasks /change /tn OllamaServe /enable && schtasks /run /tn OllamaServe`，再把 172 .env 三行注释对调。模型文件保留在 `D:\ollama_models`（18GB，勿删）。frp 隧道仍常驻（换脸任务队列将复用）。

### 架构

```
用户 → Chat.html → 172 php(/api/live/chat)
                      │ .env [LLM] BASE_URL = http://127.0.0.1:11434/v1
                      ▼
          frp 隧道（172 frps ← AI电脑 frpc 反连，断线5秒自动重连）
                      ▼
        Ollama 0.34.0 @ AI 电脑（RTX 5090D 24G）
        模型 qwen3:30b-a3b-instruct-2507-q4_K_M（18GB，常驻显存）
```

### AI 电脑侧组件

| 组件 | 位置 | 自启/自愈 |
|---|---|---|
| Ollama serve | `C:\Users\Administrator\AppData\Local\Programs\Ollama\ollama.exe serve` | 计划任务 `OllamaServe`（登录触发） |
| 模型存储 | `D:\ollama_models`（env OLLAMA_MODEDS 写在系统环境变量） | — |
| 上下文/常驻 | `OLLAMA_CONTEXT_LENGTH=16384`、`OLLAMA_KEEP_ALIVE=12h` | 系统环境变量 |
| frpc 隧道 | `D:\frp\frp_win\frpc.exe -c D:\frp\llm\frpc.toml` | 计划任务 `AIFrpLLM` → `D:\frp\llm\frpc_llm_loop.bat` 死循环保活 |
| Defender 白名单 | `D:\frp`（frp 会被杀毒秒删，勿动） | — |

### 172 侧组件

| 组件 | 说明 |
|---|---|
| frps | systemd 服务 `frps`，`/etc/frp/frps.toml`，代理端口只绑 127.0.0.1（不对公网暴露） |
| tinyproxy | 带认证 HTTP 代理 :3128（AI 电脑出国下载用），凭据在 `/root/frp_token.txt` |
| .env [LLM] | 已切本地模型；GLM 回退配置以注释保留在 .env 内 |

### 排障速查

1. **聊天没回复**：`172: curl http://127.0.0.1:11434/api/version` 不通 → 隧道断，AI 电脑上 `tasklist | findstr frpc`，无则 `schtasks /run /tn AIFrpLLM`
2. **frpc 在但隧道不通**：172 `systemctl status frps`；token 变更需同步改 `D:\frp\llm\frpc.toml`
3. **首次请求慢（30s+）**：模型冷加载正常现象；`OLLAMA_KEEP_ALIVE=12h` 内不会复发
4. **换模型**：AI 电脑 `ollama pull <新模型>` → 172 改 `.env` 的 MODEL → 清 runtime 缓存
5. **临时回退 GLM**：.env 里取消注释 GLM 三行、注释本地两行，重启 php-fpm

> 模型下载源 registry.ollama.ai 走 CF 国内边缘，AI 电脑可直连（40+MB/s）；ollama.com/github 需走 172 的 tinyproxy。
