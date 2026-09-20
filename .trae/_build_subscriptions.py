# -*- coding: utf-8 -*-
"""一次性构建脚本：由参考页 ai-girl-malaysia.com/taocan.html 生成 Subscriptions.html
做四件事：剥离 candy 追踪脚本与 PWA 资源、站内链接本地化、补站点通用脚本、替换支付入口。
生成后即可删除本脚本。
"""
import io
import os
import re

ROOT = r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com"
SRC = os.path.join(ROOT, "taocan.html")
DST = os.path.join(ROOT, "Subscriptions.html")

html = io.open(SRC, encoding="utf-8").read()
report = []


def sub(pattern, repl, text, flags=re.S | re.I, expect=None):
    new, n = re.subn(pattern, repl, text, flags=flags)
    report.append("%-46s %d" % (pattern[:44], n))
    return new


# ---------- A. 删除全部 <script>（稍后统一插入站点需要的） ----------
html = sub(r"<script\b[^>]*>.*?</script>\s*", "", html)

# ---------- B. 删除 candy 的 PWA / 追踪 / 版权资源引用 ----------
html = sub(r'<link rel="apple-touch-icon"[^>]*>\s*', "", html)
html = sub(r'<link rel="manifest"[^>]*>\s*', "", html)
html = sub(r'<link rel="canonical"[^>]*>\s*', "", html)
html = sub(r'<link rel="alternate"[^>]*>\s*', "", html)
html = sub(r'<meta name="action-cable-url"[^>]*>\s*', "", html)
html = sub(r'<meta name="csrf-[^>]*>\s*', "", html)
html = sub(r'<meta (?:property|name)="(?:og|twitter):[^"]*"[^>]*>\s*', "", html)
html = sub(r'<link rel="preconnect" href="https://cdn\.candy\.ai/"[^>]*>\s*', "", html)
html = sub(
    r'<link rel="icon"[^>]*>\s*',
    '<link rel="icon" type="image/png" href="./sugus-favicon.png">\n',
    html,
)
# 字体样式表去重（参考页里出现了两次，统一由 HEAD_ADD 补一条）
html = sub(r'<link rel="stylesheet" href="\./taocan_files/css2"[^>]*>\s*', "", html)

# ---------- C. 支付入口替换（卡支付下线，只留加密货币） ----------
PAY_BTN = (
    '<button type="button" id="%s" '
    'class="w-full px-4 py-4 bg-neutral-700 rounded-lg justify-center items-center gap-2 inline-flex mb-3">\n'
    '          <div class="text-white text-xs sm:text-sm font-medium leading-tight">Pay with</div>\n'
    '          <div class="w-16 h-5 flex gap-[4px]">\n'
    '            <img class="w-16 h-5" alt="Bitcoin" src="./taocan_files/bitcoin-eb1103743040983f739826c12ef909c787ed1e6104bb0f5eff7112d31fde0036.svg">\n'
    '            <img class="w-16 h-5" src="./taocan_files/eth-0f77f739edf37b7fc60d467b5fb8317f4467f3bfce952c865551f5d8f4953124.svg">\n'
    '            <img class="w-16 h-5" src="./taocan_files/litecoin-39b215ee717df466cd2599dc7a1f63ad0e05d619dca3a8b1a74be7112954086d.svg">\n'
    '          </div>\n'
    '        </button>'
)

pay_ids = ["sub-pay-crypto", "sub-pay-crypto-plus"]
state = {"i": 0}


def _pay_repl(m):
    out = PAY_BTN % pay_ids[min(state["i"], len(pay_ids) - 1)]
    state["i"] += 1
    return out


html, n = re.subn(
    r'<form[^>]*action="https://candy\.ai/coingate_subscription_checkout[^"]*"[^>]*>.*?</form>',
    _pay_repl,
    html,
    flags=re.S,
)
report.append("%-46s %d" % ("replace candy crypto form -> our button", n))

# 卡支付（One Click）整块移除：按钮
html = sub(
    r'<button[^>]*data-toggle-dialog-dialog-outlet="#one-click-subscription-upgrade-modal"[^>]*>.*?</button>\s*',
    "",
    html,
)
html = sub(
    r'<button[^>]*data-toggle-dialog-dialog-outlet="#one-click-subscription-extension-modal"[^>]*>.*?</button>\s*',
    "",
    html,
)

# 其余指向 candy 的表单动作一并失效
html = sub(r'action="https://candy\.ai/[^"]*"', 'action="javascript:void(0)"', html)

# ---------- D. 站内链接本地化 ----------
LINK_MAP = [
    ("https://candy.ai/achievements?src=navbar", "./Game.html"),
    ("https://candy.ai/achievements?src=sidebar", "./Game.html"),
    ("https://candy.ai/ai-anime", "./Anime.html"),
    ("https://candy.ai/ai-boyfriend", "./Guys.html"),
    ("https://candy.ai/ai-girlfriend/candy-assistant", "./Chat.html"),
    ("https://candy.ai/ai-girlfriend", "./Girls.html"),
    ("https://candy.ai/candy-shorts", "./shorts.html"),
    ("https://candy.ai/characters/new", "./create.html"),
    ("https://candy.ai/characters", "./characters.html"),
    ("https://candy.ai/collection", "./Girls.html"),
    ("https://candy.ai/feed/posts", "./posts.html"),
    ("https://candy.ai/invite", "./Inviter.html"),
    ("https://candy.ai/legal-information", "./setting.html"),
    ("https://candy.ai/private-content", "./private_content.html"),
    ("https://candy.ai/settings", "./setting.html"),
    ("https://candy.ai/shop/items?src=menu", "./shop.html"),
    ("https://candy.ai/subscriptions", "./Subscriptions.html"),
    ("https://candy.ai/tokens?from=profile_menu", "./tokens.html"),
    ("https://candy.ai/tokens", "./tokens.html"),
]
for old, new in LINK_MAP:
    n = html.count(old)
    html = html.replace(old, new)
    report.append("%-46s %d" % (("href " + old)[:44], n))

# 语言前缀的订阅页（/pl/subscriptions 等）
html = sub(r"https://candy\.ai/[a-zA-Z\-]{2,5}/subscriptions", "./Subscriptions.html", html)
# 根域名兜底
html = sub(r"https://candy\.ai/", "./Home.html", html)

# ---------- D. 去掉 candy 的埋点容器、访客标识与 one-click 弹窗 ----------
html = sub(r'<div data-controller="uta-api"[^>]*>\s*</div>\s*', "", html)
html = sub(r'\sdata-uta-api[a-z\-]*-value="[^"]*"', "", html)
html = sub(r'\sdata-uta-api-current-visitor-id="[^"]*"', "", html)
html = sub(r'\sdata-payment-gtm-tracker-[a-z\-]+-value="[^"]*"', "", html)
html = sub(r'<dialog[^>]*id="one-click-subscription-(?:extension|upgrade)-modal"[^>]*>.*?</dialog>\s*', "", html)

# ---------- D2. 品牌与联系方式本地化（站点品牌 Sugus.ai） ----------
html = html.replace("Candy.ai", "Sugus.ai").replace("Candy AI", "Sugus AI")
html = sub(r"<title>[^<]*</title>", "<title>Sugus.ai: Premium Membership</title>", html, flags=re.I)
html = html.replace("zhouyuan668855@gmail.com", "support@sugusmail.ai")
html = html.replace("support@candymail.ai", "support@sugusmail.ai")

# ---------- D3. 去掉 Turbo 布局帧的 src ----------
# candy 的 /layout/extras 路由我们没有，保留 src 会让 Turbo 找不到帧后整页跳 404
html = sub(r'(<turbo-frame id="layout_extras")\s+src="/layout/extras"', r"\1", html)

# ---------- E. 月付卡片：去掉「已是当前套餐」禁用态 ----------
html = html.replace('data-plan-type="monthly" data-plan-price="13.99" data-disabled="true"',
                    'data-plan-type="monthly" data-plan-price="13.99" data-disabled="false"')
html = html.replace('data-plan-type="monthly" data-plan-price="13.99" data-disabled="true" data-scroll-snap-carousel-target="item"',
                    'data-plan-type="monthly" data-plan-price="13.99" data-disabled="false" data-scroll-snap-carousel-target="item"')
html = sub(r"sub-card-default opacity-75 token-tier-current-locked", "sub-card-default", html)
html = sub(r'\sdata-locked-label="Current Plan"', "", html)
html = sub(r'\sdata-unlocked-label="Update Tokens"', "", html)
html = sub(r'\sdata-token-tier-current-plan="\d+"', "", html)
# 卡片上的「更新 tokens」文案改为购买
html = html.replace(">Update Tokens<", ">Continue<")

# ---------- G. 头部与底部补站点脚本 ----------
HEAD_ADD = (
    '<link rel="stylesheet" href="./taocan_files/css2" media="all" onload="this.media=\'all\'">\n'
    '<link rel="stylesheet" href="./assets/fy.css?v=20260918b" data-turbo-track="reload">\n'
    '<script src="./taocan_files/application-4fabe34a2be5cf77e725cab6b9d9b75c37fc2609031725eda4ad515c4ce19e22.js" data-turbo-track="reload" defer="defer"></script>\n'
    '<script src="./taocan_files/alpine-69b009b4309a19f099cf57a5f03edb7fbc7a130fdc6a2e9b34bb32a1ca3ef10d.js" data-turbo-track="reload" defer="defer"></script>\n'
    '<script src="./taocan_files/jquery-3.6.0.min.js"></script>\n'
    '<script src="./assets/layer/3.1.1/layer.js"></script>\n'
    '<script src="./js/main.js?v=20260918b"></script>\n'
    '<script src="./js/subscriptions.js"></script>\n'
)
assert "</head>" in html
html = html.replace("</head>", HEAD_ADD + "</head>", 1)

io.open(DST, "w", encoding="utf-8").write(html)
print("written:", DST, len(html), "bytes")
print("\n".join(report))
