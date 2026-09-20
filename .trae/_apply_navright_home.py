# -*- coding: utf-8 -*-
"""样板页改造：把 Home.html 顶栏右侧（Subscribe 75% OFF + My Profile）
换成新版导航右上角形态：成就徽章 + tokens 余额胶囊 + 头像菜单。
只替换这一块，其它 DOM 不动。可重复执行（已替换则跳过）。
"""
import io
import os

ROOT = r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com"
HOME = os.path.join(ROOT, "Home.html")

START_MARK = '<div class="flex items-center gap-x-4 lg:gap-x-6 nav-zk">'
END_MARK = '<!-- ===== 主内容区 ===== -->'

CLUSTER = '''<!-- 右上角：新版导航形态（成就徽章 + tokens 余额胶囊 + 头像菜单） -->
				<div class="fy-navright">
					<a class="fy-achv" href="./Game.html" title="Achievements" aria-label="Achievements">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9a6 6 0 1 0 12 0V4H6z"></path><path d="M6 6H3v2a3 3 0 0 0 3 3M18 6h3v2a3 3 0 0 1-3 3"></path><path d="M12 15v3m-4 3h8"></path></svg>
						<span class="fy-achv-tag">New</span>
					</a>
					<div class="fy-chipwrap" id="token-chip-wrap">
						<button type="button" class="fy-chip" id="token-chip-button" aria-expanded="false">
							<img class="fy-chip-coin" src="./taocan_files/token-4f2c951e0a476a1cf59eadbc2108289323778ccb84fb232782ae923a8d1b8961.svg" alt="Tokens">
							<span data-target="user-token-balance">0</span>
							<img class="fy-chip-arrow" src="./taocan_files/expand-cd76d60d9e4264d89102db00cc87d69cb1ecd6c96663e5cea3809a90dd47a9ab.svg" alt="">
						</button>
						<div class="fy-chip-pop hidden" id="navbar-tokens-dropdown">
							<div class="fy-chip-pop-in">
								<div class="fy-chip-pop-title">Tokens</div>
								<div class="fy-chip-pop-note">Used to unlock content, images and videos.</div>
							</div>
							<a class="fy-chip-pop-cta" href="./tokens.html">Buy more
								<img src="./taocan_files/add_white-d504ef86384a5fe4014170fa04483735c82f42d80301af36e1c50f979a2c2245.svg" alt="">
							</a>
						</div>
					</div>
					<div id="profileDropdownWrap" class="fy-profilewrap">
						<button type="button" class="fy-avatar-btn" id="user-menu-button" aria-expanded="false">
							<span class="sr-only">Open user menu</span>
							<img src="./taocan_files/user-136475e1e7120a61ab13061e84c88cdf96837d2fb8eafda7a8c5b73815512f42.svg" alt="Open user menu">
							<span class="fy-avatar-dot"></span>
						</button>
						<div id="profileDropdownMenu" class="fy-profile-menu" style="display:none">
							<a href="./Subscriptions.html"><img src="./taocan_files/premium-3fa8ec9f951178508109d03de202bc4a381ed87451b7dc9f8aa5a931bd485e02.svg" alt=""><span>Subscription</span></a>
							<a href="./tokens.html"><img src="./taocan_files/token-4f2c951e0a476a1cf59eadbc2108289323778ccb84fb232782ae923a8d1b8961.svg" alt=""><span>Tokens</span></a>
							<a href="./charactersIndex.html"><img src="./characters_files/love-lady-760605daa886b7e49e77d5d26c1047bc0f815c005a978a5c76810db20bc355dd.svg" alt=""><span>My AI</span></a>
							<a href="./upload_media.html"><svg viewBox="0 0 24 24" fill="none" stroke="#E75275" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg><span>Upload Media</span></a>
							<a href="./setting.html"><img src="./assets/settings-087d65eb2d9a7508f04c379467f39735f971ac6299872f159da8e6c1d735a710.svg" alt=""><span>Settings</span></a>
							<form method="post" onsubmit="return false"><button data-turbo="false" id="logout" type="submit"><img src="./assets/logout-c5bab4efa12211db16618320220423a09cf3d7f5b562be32cdd5110df2a21c18.svg" alt=""><span>Logout</span></button></form>
						</div>
					</div>
				</div>
			'''

html = io.open(HOME, encoding="utf-8").read()

if "fy-navright" in html:
    print("已改造过，跳过")
    raise SystemExit(0)

s = html.index(START_MARK)
e = html.index(END_MARK)
# 往前数两个 </div>：先关 #user-setting，再关导航栏
i1 = html.rfind("</div>", 0, e)
i2 = html.rfind("</div>", 0, i1)
print("start line:", html.count("\n", 0, s) + 1, " end line:", html.count("\n", 0, i2) + 1)

out = html[:s] + CLUSTER + html[i2:]
io.open(HOME, "w", encoding="utf-8").write(out)
print("Home.html 已更新，长度 %d -> %d" % (len(html), len(out)))
