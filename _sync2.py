import io
f = '.trae/deploy_all.py'
data = io.open(f, encoding='utf-8').read()
old = "        fastcgi_param SCRIPT_NAME /index.php;\n    }"
new = "        fastcgi_param SCRIPT_NAME /index.php;\n        fastcgi_param PATH_INFO $uri;\n    }"
assert old in data, 'anchor not found'
data = data.replace(old, new)
io.open(f, 'w', encoding='utf-8').write(data)
print('synced')
