import hashlib
import re
import sys

# 远端归一化 md5：与 check_drift.py 的 norm_text 完全一致。
# apply_brand 会改品牌词 / 域名后缀 / CDN 地址 / 资源引用形式，
# 这些不是「有人手改了代码」，比对前统一抹平，否则全站误报。
#
# 用法: python3 remote_norm_md5.py <plain|stripver> <root> <list_file>
BR = re.compile(r"sugus|heygirl|kelymi|kiss|kisss", re.I)
TLD = re.compile(r"[.](?:ai|live|autos|com|sbs|cam|fun|one|chat)", re.I)
CDN = re.compile(r"https?://[\w.-]*(?:b-cdn|cloudfront|akamaihd)[.]net", re.I)
MAILRE = re.compile(r"support@[\w.-]*mail[.]\w+", re.I)
RES = re.compile(r"(?:<CDN>/ai|\.)/(assets|_shared|js|media)/([^\"'\s)]+)")
VR = re.compile(r"[?]v=[A-Za-z0-9]+")
CRLF = chr(13) + chr(10)
LF = chr(10)


def norm(data, strip_ver):
    s = data.decode("utf-8", "replace")
    if strip_ver:
        s = VR.sub("?v=X", s)
    s = s.replace(CRLF, LF)
    s = MAILRE.sub("<MAIL>", s)
    s = CDN.sub("<CDN>", s)
    s = RES.sub(lambda m: "<A>/" + m.group(1) + "/" + m.group(2), s)
    s = BR.sub("<B>", s)
    return TLD.sub(".<TLD>", s)


def main():
    mode = sys.argv[1] if len(sys.argv) > 1 else "plain"
    list_file = sys.argv[-1]
    strip_ver = mode == "stripver"
    for p in open(list_file, encoding="utf-8"):
        p = p.strip()
        if not p:
            continue
        try:
            data = open(p, "rb").read()
        except Exception:
            continue
        print(hashlib.md5(norm(data, strip_ver).encode("utf-8")).hexdigest() + "  " + p)


if __name__ == "__main__":
    main()
