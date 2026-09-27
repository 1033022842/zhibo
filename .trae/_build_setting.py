# -*- coding: utf-8 -*-
"""由 settings.html 生成 setting.html：
1) 用干净的 <head> 替换整段（Sugus 品牌 + settings_files 资源 + 站点脚本/样式）
2) 去掉 Bing 翻译痕迹（_mst* 属性与相关 CSS）
3) 中文文案还原英文（时区 <option> 按 tz 标识符生成；选择项按 input value 还原；其余手工字典）
4) 清理真实账号隐私数据（访客 id / email / token / csrf / 卡号后四位 / 用户 id）
用法：python .trae/_build_setting.py
"""
import io
import os
import re
import sys

ROOT = r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com"
SRC = os.path.join(ROOT, "settings.html")
DST = os.path.join(ROOT, "setting.html")
FILES = os.path.join(ROOT, "settings_files")

raw = io.open(SRC, encoding="utf-8").read()
log = []

# ---------------------------------------------------------------- 1. 时区 option：tz 标识符 -> 英文
TZ = {
    "UTC": "(GMT+00:00) UTC",
    "Africa/Casablanca": "(GMT+00:00) Casablanca",
    "Europe/London": "(GMT+00:00) Edinburgh",
    "Africa/Monrovia": "(GMT+00:00) Monrovia",
    "Europe/Dublin": "(GMT+00:00) Dublin",
    "Europe/Lisbon": "(GMT+00:00) Lisbon",
    "Europe/Zurich": "(GMT+01:00) Bern",
    "Europe/Warsaw": "(GMT+01:00) Warsaw",
    "Europe/Ljubljana": "(GMT+01:00) Ljubljana",
    "Europe/Copenhagen": "(GMT+01:00) Copenhagen",
    "Europe/Paris": "(GMT+01:00) Paris",
    "Europe/Prague": "(GMT+01:00) Prague",
    "Europe/Bratislava": "(GMT+01:00) Bratislava",
    "Europe/Budapest": "(GMT+01:00) Budapest",
    "Europe/Brussels": "(GMT+01:00) Brussels",
    "Europe/Stockholm": "(GMT+01:00) Stockholm",
    "Europe/Skopje": "(GMT+01:00) Skopje",
    "Europe/Berlin": "(GMT+01:00) Berlin",
    "Europe/Vienna": "(GMT+01:00) Vienna",
    "Europe/Rome": "(GMT+01:00) Rome",
    "Europe/Sarajevo": "(GMT+01:00) Sarajevo",
    "Europe/Zagreb": "(GMT+01:00) Zagreb",
    "Africa/Algiers": "(GMT+01:00) West Central Africa",
    "Europe/Belgrade": "(GMT+01:00) Belgrade",
    "Europe/Amsterdam": "(GMT+01:00) Amsterdam",
    "Europe/Madrid": "(GMT+01:00) Madrid",
    "Europe/Kaliningrad": "(GMT+02:00) Kaliningrad",
    "Africa/Harare": "(GMT+02:00) Harare",
    "Europe/Tallinn": "(GMT+02:00) Tallinn",
    "Europe/Bucharest": "(GMT+02:00) Bucharest",
    "Africa/Cairo": "(GMT+02:00) Cairo",
    "Africa/Johannesburg": "(GMT+02:00) Pretoria",
    "Europe/Sofia": "(GMT+02:00) Sofia",
    "Europe/Vilnius": "(GMT+02:00) Vilnius",
    "Asia/Jerusalem": "(GMT+02:00) Jerusalem",
    "Europe/Helsinki": "(GMT+02:00) Helsinki",
    "Europe/Riga": "(GMT+02:00) Riga",
    "Europe/Athens": "(GMT+02:00) Athens",
    "Europe/Istanbul": "(GMT+03:00) Istanbul",
    "Europe/Volgograd": "(GMT+03:00) Volgograd",
    "Africa/Nairobi": "(GMT+03:00) Nairobi",
    "Asia/Riyadh": "(GMT+03:00) Riyadh",
    "Asia/Baghdad": "(GMT+03:00) Baghdad",
    "Europe/Minsk": "(GMT+03:00) Minsk",
    "Asia/Kuwait": "(GMT+03:00) Kuwait",
    "Europe/Moscow": "(GMT+03:00) Moscow",
    "Asia/Tehran": "(GMT+03:30) Tehran",
    "Asia/Yerevan": "(GMT+04:00) Yerevan",
    "Asia/Baku": "(GMT+04:00) Baku",
    "Asia/Tbilisi": "(GMT+04:00) Tbilisi",
    "Europe/Samara": "(GMT+04:00) Samara",
    "Asia/Muscat": "(GMT+04:00) Abu Dhabi",
    "Asia/Kabul": "(GMT+04:30) Kabul",
    "Asia/Karachi": "(GMT+05:00) Islamabad",
    "Asia/Yekaterinburg": "(GMT+05:00) Ekaterinburg",
    "Asia/Tashkent": "(GMT+05:00) Tashkent",
    "Asia/Almaty": "(GMT+05:00) Almaty",
    "Asia/Colombo": "(GMT+05:30) Sri Jayawardenepura",
    "Asia/Kolkata": "(GMT+05:30) Chennai",
    "Asia/Kathmandu": "(GMT+05:45) Kathmandu",
    "Asia/Urumqi": "(GMT+06:00) Urumqi",
    "Asia/Dhaka": "(GMT+06:00) Dhaka",
    "Asia/Krasnoyarsk": "(GMT+07:00) Krasnoyarsk",
    "Asia/Novosibirsk": "(GMT+07:00) Novosibirsk",
    "Asia/Bangkok": "(GMT+07:00) Bangkok",
    "Asia/Jakarta": "(GMT+07:00) Jakarta",
    "Asia/Ulaanbaatar": "(GMT+08:00) Ulaanbaatar",
    "Asia/Irkutsk": "(GMT+08:00) Irkutsk",
    "Asia/Shanghai": "(GMT+08:00) Beijing",
    "Asia/Taipei": "(GMT+08:00) Taipei",
    "Asia/Kuala_Lumpur": "(GMT+08:00) Kuala Lumpur",
    "Asia/Singapore": "(GMT+08:00) Singapore",
    "Australia/Perth": "(GMT+08:00) Perth",
    "Asia/Chongqing": "(GMT+08:00) Chongqing",
    "Asia/Hong_Kong": "(GMT+08:00) Hong Kong",
    "Asia/Tokyo": "(GMT+09:00) Osaka",
    "Asia/Yakutsk": "(GMT+09:00) Yakutsk",
    "Asia/Seoul": "(GMT+09:00) Seoul",
    "Australia/Darwin": "(GMT+09:30) Darwin",
    "Australia/Adelaide": "(GMT+09:30) Adelaide",
    "Pacific/Guam": "(GMT+10:00) Guam",
    "Australia/Canberra": "(GMT+10:00) Canberra",
    "Australia/Melbourne": "(GMT+10:00) Melbourne",
    "Australia/Brisbane": "(GMT+10:00) Brisbane",
    "Australia/Sydney": "(GMT+10:00) Sydney",
    "Asia/Vladivostok": "(GMT+10:00) Vladivostok",
    "Pacific/Port_Moresby": "(GMT+10:00) Port Moresby",
    "Australia/Hobart": "(GMT+10:00) Hobart",
    "Pacific/Guadalcanal": "(GMT+11:00) Solomon Is.",
    "Asia/Srednekolymsk": "(GMT+11:00) Srednekolymsk",
    "Pacific/Noumea": "(GMT+11:00) New Caledonia",
    "Asia/Magadan": "(GMT+11:00) Magadan",
    "Asia/Kamchatka": "(GMT+12:00) Kamchatka",
    "Pacific/Auckland": "(GMT+12:00) Auckland",
    "Pacific/Fiji": "(GMT+12:00) Fiji",
    "Pacific/Majuro": "(GMT+12:00) Marshall Is.",
    "Pacific/Chatham": "(GMT+12:45) Chatham Is.",
    "Pacific/Tongatapu": "(GMT+13:00) Nuku'alofa",
    "Pacific/Fakaofo": "(GMT+13:00) Tokelau Is.",
    "Pacific/Apia": "(GMT+13:00) Samoa",
    "Atlantic/Azores": "(GMT-01:00) Azores",
    "Atlantic/Cape_Verde": "(GMT-01:00) Cape Verde Is.",
    "Atlantic/South_Georgia": "(GMT-02:00) Mid-Atlantic",
    "America/Nuuk": "(GMT-02:00) Greenland",
    "America/Guatemala": "(GMT-06:00) Central America",
    "America/Chihuahua": "(GMT-06:00) Chihuahua",
    "America/Mexico_City": "(GMT-06:00) Guadalajara",
    "America/Chicago": "(GMT-06:00) Central Time (US & Canada)",
    "America/Regina": "(GMT-06:00) Saskatchewan",
    "America/Monterrey": "(GMT-06:00) Monterrey",
    "America/Asuncion": "(GMT-03:00) Asuncion",
    "America/Sao_Paulo": "(GMT-03:00) Brasilia",
    "America/Argentina/Buenos_Aires": "(GMT-03:00) Buenos Aires",
    "America/Montevideo": "(GMT-03:00) Montevideo",
    "America/St_Johns": "(GMT-03:30) Newfoundland",
    "America/Guyana": "(GMT-04:00) Georgetown",
    "America/Caracas": "(GMT-04:00) Caracas",
    "America/Santiago": "(GMT-04:00) Santiago",
    "America/Halifax": "(GMT-04:00) Atlantic Time (Canada)",
    "America/La_Paz": "(GMT-04:00) La Paz",
    "America/Puerto_Rico": "(GMT-04:00) Puerto Rico",
    "America/Lima": "(GMT-05:00) Lima",
    "America/Indiana/Indianapolis": "(GMT-05:00) Indiana (East)",
    "America/Bogota": "(GMT-05:00) Bogota",
    "America/New_York": "(GMT-05:00) Eastern Time (US & Canada)",
    "America/Phoenix": "(GMT-07:00) Arizona",
    "America/Denver": "(GMT-07:00) Mountain Time (US & Canada)",
    "America/Mazatlan": "(GMT-07:00) Mazatlan",
    "America/Los_Angeles": "(GMT-08:00) Pacific Time (US & Canada)",
    "America/Tijuana": "(GMT-08:00) Tijuana",
    "America/Juneau": "(GMT-09:00) Alaska",
    "Pacific/Midway": "(GMT-11:00) Midway Island",
    "Pacific/Pago_Pago": "(GMT-11:00) American Samoa",
    "Pacific/Honolulu": "(GMT-10:00) Hawaii",
    "Etc/GMT+12": "(GMT-12:00) International Date Line West",
}

CJK = re.compile(r"[\u4e00-\u9fff]")


def fix_options(html):
    """按 tz 标识符还原时区 <option> 文案"""
    n = [0]

    def rep(m):
        attrs, inner = m.group(1), m.group(2)
        if not CJK.search(inner):
            return m.group(0)
        vm = re.search(r'value="([^"]*)"', attrs)
        if not vm:
            return m.group(0)
        v = vm.group(1)
        if v in TZ:
            n[0] += 1
            return "<option%s>%s</option>" % (attrs, TZ[v])
        if v == "off":
            n[0] += 1
            return "<option%s>Off</option>" % attrs
        if v == "":
            n[0] += 1
            return "<option%s>Auto — Shanghai (GMT+08:00) (detected)</option>" % attrs
        return m.group(0)

    return re.sub(r"<option([^>]*)>([^<>]*)</option>", rep, html), n[0]


# ---------------------------------------------------------------- 2. 选择项：input value -> 中文标签
def fix_pills(html, hand_keys):
    """<input value="EN">…<span>中文</span> 用 value 还原中文标签"""
    n = [0]
    pat = re.compile(
        r'(<input[^>]*\svalue="([^"<>]{1,80})"[^>]*>)(.{0,1600}?)(<span[^>]*>)([^<>]*[\u4e00-\u9fff][^<>]*)(</span>)',
        re.S)

    def rep(m):
        en, zh = m.group(2).strip(), m.group(5).strip()
        if not en or not zh or zh in hand_keys:
            return m.group(0)
        if en.lower() in ("1", "0", "true", "false", "delete", "off"):
            return m.group(0)
        n[0] += 1
        return m.group(1) + m.group(3) + m.group(4) + en + m.group(6)

    return pat.sub(rep, html), n[0]


# ---------------------------------------------------------------- 3. 手工字典
HAND = {
    "Candy AI：AI 女友应用 |聊天，附照片、语音和视频": "Candy AI: AI Girlfriend App | Chat w/ Pics, Voice, & Video",
    # 侧边栏 / 顶栏
    "收藏": "Collection",
    "塑造品格": "Create Character",
    "我的人工智能": "My AI",
    "私人内容": "Private Content",
    "购买代币": "Buy Tokens",
    "糖果店": "Candy Shop",
    "首页": "Home",
    "发现": "Discover",
    "短片": "Shorts",
    "聊天": "Chat",
    "成就": "Achievements",
    "新角色": "New character",
    "通知": "Notifications",
    "附属台": "Affiliate Program",
    "帮助中心": "Help center",
    "联系我们": "Contact us",
    "信任与安全": "Trust & Safety",
    "法律术语": "Legal",
    "糖果人工智能": "Candy AI",
    "国旗图标": "Flag icon",
    "令牌图标": "Token icon",
    "开放侧边栏": "Open sidebar",
    "展开菜单": "Expand menu",
    "打开用户菜单": "Open user menu",
    "男性侧脸图标": "Male profile icon",
    "我的个人资料": "My Profile",
    "登出": "Log out",
    "语言": "Language",
    "英文": "English",
    "英语": "English",
    "订阅": "Subscribe",
    "代币": "Tokens",
    "关闭": "Close",
    "结束": "End",
    "取消": "Cancel",
    "更多": "More",
    "拿100个代币": "Get 100 tokens",
    # 个人资料
    "配置文件设置": "Profile settings",
    "关于你": "About you",
    "你的名字": "Your name",
    "名称": "Name",
    "昵称": "Nickname",
    "电子邮件": "Email",
    "密码": "Password",
    "你的性别": "Your gender",
    "性别": "Gender",
    "年龄": "Age",
    "选择你的年龄": "Select your age",
    "位置": "Location",
    "🇺🇸 美利坚合众国": "🇺🇸 United States of America",
    "在你所有聊天中都被用过": "Used across all your chats",
    "这些信息只会在与AI角色的对话中使用。": "This info is only used in your chats with AI characters.",
    "艾尔文": "Alvin",
    "男性": "Men",
    "伙计": "Man",
    "女人": "Woman",
    "女子": "Women",
    "非二元性别": "Non-binary",
    "景观": "View",
    "2个已解锁的72个·15分": "2 unlocked of 72 · 15 pts",
    # Persona
    "你的人格": "Your personas",
    "创建你的Persona": "Create your Persona",
    "第二个人格": "Second persona",
    "存档人格": "Archived personas",
    "创造你的替代身份": "Create your alternative identity",
    "你有两种不同的描述": "You have two different Personas",
    "随时向你的伙伴展示你完全不同的一面。": "Show your companion a completely different side of you anytime.",
    "塑造本地参考和氛围": "Sets local reference and vibe",
    "你该怎么做": "What do you do",
    "你的简介": "Your bio",
    "写你的简介": "Write your bio",
    "简介 24% 完成": "Bio 24% complete",
    "几个词会影响他们和你说话的方式。": "A few words that shape how they talk to you.",
    "你喜欢什么": "What do you like",
    "你喜欢的性别": "Who you're interested in",
    "寻找": "Search",
    "爱好": "Hobbies",
    "背景设定": "Backstory",
    "返回": "Back",
    "💜 长期合作伙伴": "💜 Long-term partner",
    "😍 长期来看，但短期内还行": "😍 Long-term but short-term OK",
    "🥂 短期来看，但长期来说还可以": "🥂 Short-term but long-term OK",
    "🎉 短期乐趣": "🎉 Short-term fun",
    "👋 新朋友": "👋 New friends",
    "🤔 还在摸索中": "🤔 Still figuring it out",
    # 聊天定制
    "全局聊天定制": "Global chat customization",
    "为所有符合条件的角色设定默认聊天模式、回复长度和旁白。每个角色的设置总是优先考虑。":
        "Set the default chat mode, reply length, and narration for all eligible characters. Per-character settings always take priority.",
    "自定义聊天体验": "Customize your chat experience",
    "管理": "Manage",
    "聊天模式": "Chat mode",
    "经典": "Classic",
    "默认": "Default",
    "角色、原创模型和个性。": "Characters, original models, and personalities.",
    "平衡": "Balanced",
    "更快、直接的回答，带着刺激的升级": "Faster, straight answers with a steamy upgrade",
    "体贴": "Thoughtful",
    "擅长叙述和慢节奏故事": "Great at narration and slow-burn stories",
    "强大": "Powerful",
    "记忆力长4倍，回答最灵活，答案最长": "4x longer memory, most flexible responses, longest answers",
    "响应长度": "Response length",
    "短篇": "Short",
    "普通": "Normal",
    "长篇": "Long",
    "非常长": "Very long",
    "超长版（高级加）": "Extra long (Premium Plus)",
    "超长": "Extra long",
    "旁白": "Narration",
    "允许动作和场景方向用星号表示。": "Allow actions and scene direction using asterisks.",
    "存档设置": "Save settings",
    "我的时区": "My timezone",
    "角色会根据你的时间做出相应反应": "Characters react according to your time",
    "通过Premium Plus解锁时间感知聊天功能。": "Unlock time-aware chat with Premium Plus.",
    "通过Premium Plus解锁超长回复。": "Unlock extra-long replies with Premium Plus.",
    "用Premium+解锁": "Unlock with Premium+",
    "高级加": "Premium Plus",
    "升级到 Premium+": "Upgrade to Premium+",
    "Premium+ 中的一切": "Everything in Premium+",
    "高级版": "Premium",
    "升级": "Upgrade",
    # 通知
    "Candy.ai 的重要通知。": "Important notifications from Candy.ai.",
    "加载通知": "Loading notifications",
    "出了点问题": "Something went wrong",
    "Alexis Ivyedge 现已开放。": "Alexis Ivyedge is now available.",
    "Lexi Luna 现已开放。": "Lexi Luna is now available.",
    "萨迪·阿什沃斯现已可加入。": "Sadie Ashworth is now available.",
    "罗茜·里奥斯现已开放。": "Rosie Rios is now available.",
    "上午5：35": "5:35 AM",
    "凌晨1：24": "1:24 AM",
    "凌晨2：54": "2:54 AM",
    "凌晨4：15": "4:15 AM",
    # 媒体 / 通知开关
    "主动媒体": "Proactive media",
    "自动通知": "Automatic notifications",
    "自动加值": "Auto top-up",
    "人工智能与审核培训": "AI & moderation training",
    "关闭AI训练？": "Turn off AI training?",
    "Candy会停止利用你的对话和提示来改进产品。你可以随时从设置里重新开启这个功能。":
        "Sugus will stop using your chats and prompts to improve the product. You can turn this back on anytime from Settings.",
    "是的，关掉它": "Yes, turn it off",
    # 计费
    "当前计划": "Current plan",
    "变革计划": "Change plan",
    "支付方式": "Payment methods",
    "管理未来费用的支付方式。": "Manage how your future charges are paid.",
    "添加支付方式": "Add payment method",
    "添加备用卡": "Add backup card",
    "添加": "Add",
    "万事达卡 ····6768": "Mastercard ····0000",
    "截止日期：10月28日": "Due date: Oct 28",
    "付款日期：": "Payment date:",
    "订阅续订：": "Subscription renewal:",
    "取消订阅": "Cancel subscription",
    "它将不再适用于你的续订和购买。": "It will no longer be used for your renewals and purchases.",
    "+10代币": "+10 tokens",
    "如果你的卡坏了，我们会自动尝试。": "We'll automatically try it if your card fails.",
    "这张卡将用于您未来的续约和消费。": "This card will be used for your future renewals and purchases.",
    "更改默认支付方式？": "Change default payment method?",
    "是的，默认": "Yes, set as default",
    "把这张卡拿掉？": "Remove this card?",
    "是的，移除卡片": "Yes, remove card",
    # 危险区
    "危险区": "Danger zone",
    "如果你想永久删除这个账户及其所有数据，": "If you want to permanently delete this account and all of its data,",
    "删除账户": "Delete account",
    # 保留原样的语言名
    "日本語": "日本語",
}

# ---------------------------------------------------------------- 4. head 重建
NEW_HEAD = '''<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<style type="text/css">
.turbo-progress-bar {
  position: fixed;
  display: block;
  top: 0;
  left: 0;
  height: 3px;
  background: #0076ff;
  z-index: 2147483647;
  transition: width 300ms ease-out, opacity 150ms 150ms ease-in;
  transform: translate3d(0, 0, 0);
}
@media only screen and (max-width: 768px) { .pc-nav { display: none; } }
.login-pf{display:none;}
#user-login{display:none;}
</style>
<link rel="icon" type="image/png" href="./sugus-favicon.png">
<title>Sugus.ai: Create Your AI Girlfriend &amp; Start Chatting</title>
<meta name="description" content="Your dream companion awaits! Create your AI Girlfriend, shape her look, personality, and bring her to life in one click. 100% powered by Artificial Intelligence">
<meta name="viewport" content="width=device-width,initial-scale=1, maximum-scale=1">
<link rel="stylesheet" href="./assets/fy.css?v=20260921b" data-turbo-track="reload">
<link rel="stylesheet" href="./settings_files/application-8ee8a481074c4025471514720f49a183b2d681ef94947cc96604f49cfcc404bd.css" data-turbo-track="reload">
<link rel="stylesheet" href="./settings_files/css2" media="all" onload="this.media='all'">
<script src="./settings_files/jquery-3.6.0.min.js" data-turbo-track="reload"></script>
<script src="./settings_files/application-8e0f15c7ab8015fa09b9283587ea3992a13e25d296abc53659a4a68d93893d7e.js" data-turbo-track="reload" defer="defer"></script>
<script src="./settings_files/voice_call_addon-da279304726038af10a93380997f2f630ac7817d459ac09f7e097ea0b428e5c1.js" data-turbo-track="reload" defer="defer"></script>
<script src="./settings_files/alpine-69b009b4309a19f099cf57a5f03edb7fbc7a130fdc6a2e9b34bb32a1ca3ef10d.js" data-turbo-track="reload" defer="defer"></script>
<script src="./assets/layer/3.1.1/layer.js"></script>
<script src="./js/settings.js"></script>
<script src="./js/event.js"></script>
<script src="./js/main.js?v=20260921b"></script>
<script src="./js/shell-new.js?v=20260921b"></script>
</head>
'''

# ---------------------------------------------------------------- 3.5 去掉下载后缀（浏览器保存时加的".下载"）
for name in os.listdir(FILES):
    if name.endswith(".下载"):
        src = os.path.join(FILES, name)
        dst = os.path.join(FILES, name[: -len(".下载")])
        if not os.path.exists(dst):
            os.rename(src, dst)
            log.append("重命名 %s -> %s" % (name, os.path.basename(dst)))

# ---------------------------------------------------------------- 执行
body_at = raw.index("<body")
body = raw[body_at:]
body = body.replace("</body>", "</body>", 1)

# 4.1 清 Bing 痕迹
before = len(re.findall(r"\s_mst[\w-]*=\"[^\"]*\"", body))
body = re.sub(r"\s_mst[\w-]*=\"[^\"]*\"", "", body)
log.append("去掉 _mst* 属性 %d 个" % before)
body = re.sub(r"\[_mst\w*\]\s*\{[^}]*\}", "", body)
body = re.sub(r"\[_mst\w*\]\s*[,{]", "", body)

# 4.2 隐私清理
body = re.sub(r'name="authenticity_token" value="[^"]*"', 'name="authenticity_token" value=""', body)
body = re.sub(r'data-uta-api-[a-z-]*="[^"]*"', "", body)
body = re.sub(r'data-main-is-current-user-signed-in="[^"]*"', "", body)
body = re.sub(r'id="edit_user_\d+"', 'id="edit_user"', body)
before = body.count("csrf-token")
body = re.sub(r'<meta name="csrf-token"[^>]*>', "", body)
log.append("清理隐私类属性（csrf/meta %d 处）" % before)

# 4.3 时区 option
body, n = fix_options(body)
log.append("时区 option 还原 %d 条" % n)

# 4.4 选择项（占位输入里的英文）
body, n = fix_pills(body, set(HAND))
log.append("选择项（occupation/hobby/…）按 value 还原 %d 条" % n)

# 4.5 手工字典：长词优先
for zh in sorted(HAND, key=len, reverse=True):
    if zh in body:
        body = body.replace(zh, HAND[zh])
        log.append("  字典: %s -> %s" % (zh, HAND[zh]))

# 4.6 剩余零散单字（截图里的角标）
body = body.replace(">新<", ">New<")

# 4.7 外链清理：candy.ai 的 PWA/静态图标 → Sugus
body = re.sub(r"https://candy\.ai/assets/pwa/icons/apple/[^\"']+", "./sugus-favicon.png", body)

# 4.7b 站点既有约定（与 .trae/_build_subscriptions.py 保持一致）
#  - 去掉 Turbo 布局帧的 src：我们没有 /layout/extras 路由，留着会让 Turbo 整页跳 404
body = re.sub(r'(<turbo-frame id="layout_extras")\s+src="/layout/extras"', r"\1", body)
#  - 抓取账号的邮箱、品牌词
body = body.replace("zhouyuan668855@gmail.com", "support@sugusmail.ai")
body = body.replace("support@candymail.ai", "support@sugusmail.ai")
body = body.replace("Candy.ai", "Sugus.ai").replace("Candy AI", "Sugus AI")
body = body.replace("improve Candy", "improve Sugus")
body = body.replace("Candy Shop", "Shop")
#  - 顶栏 logo 换成站点的 Sugus 字标
body = re.sub(r'src="\./settings_files/logo-navbar-[0-9a-f]+\.svg"', 'src="./sugus-wordmark.png"', body)

#  - 站内链接本地化：candy.ai 的页面路径换成站点自己的页面；其余一律去掉域名留根相对路径
#    （与 .trae/_build_subscriptions.py、旧版 setting.html 的做法一致：整页 0 处 candy.ai）
URL_MAP = [
    ("https://candy.ai/subscriptions?tab=premium-plus", "./Subscriptions.html"),
    ("https://candy.ai/ai-girlfriend/candy-assistant", "./Chat.html"),
    ("https://candy.ai/shop/items?src=menu", "./shop.html"),
    ("https://candy.ai/tokens?from=profile_menu", "./purchasePoints.html"),
    ("https://candy.ai/group_chats/new", "./Chat.html"),
    ("https://candy.ai/private-content", "./private_content.html"),
    ("https://candy.ai/characters/new", "./characters.html"),
    ("https://candy.ai/candy-shorts", "./shorts.html"),
    ("https://candy.ai/create-video", "./Image.html"),
    ("https://candy.ai/subscriptions", "./Subscriptions.html"),
    ("https://candy.ai/ai-girlfriend", "./Girls.html"),
    ("https://candy.ai/ai-boyfriend", "./Guys.html"),
    ("https://candy.ai/feed/posts", "./posts.html"),
    ("https://candy.ai/ai-anime", "./Anime.html"),
    ("https://candy.ai/engagement", "./Home.html"),
    ("https://candy.ai/settings", "./setting.html"),
    ("https://candy.ai/collection", "./Gallery.html"),
    ("https://candy.ai/characters", "./charactersIndex.html"),
    ("https://candy.ai/tokens", "./purchasePoints.html"),
    ("https://candy.ai/invite", "./Inviter.html"),
    ("https://candy.ai/", "./index.html"),
]
for a, b in URL_MAP:
    body = body.replace(a, b)
body = body.replace("https://candy.ai", "").replace("wss://candy.ai", "")

out = NEW_HEAD + body
io.open(DST, "w", encoding="utf-8", newline="").write(out)

# 4.8 校验：还剩多少中文
left = {}
for i, ln in enumerate(out.split("\n"), 1):
    for m in re.finditer(r"[^<>\"'=]{0,50}[\u4e00-\u9fff][^<>\"'=]{0,50}", ln):
        s = m.group(0).strip()
        left.setdefault(s, []).append(i)

print("==== 生成完成 ====")
print("输出 %s  字符数 %d  行数 %d" % (DST, len(out), out.count("\n") + 1))
for l in log:
    if not l.startswith("  字典"):
        print(" -", l)
print("手工字典替换 %d 条" % len([l for l in log if l.startswith("  字典")]))
print("\n==== 仍含中文的片段 %d 组 ====" % len(left))
for s in sorted(left, key=lambda x: (-len(left[x]), x)):
    print("[%2d次] 行%s | %s" % (len(left[s]), left[s][:5], s))
