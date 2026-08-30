import io
f = 'Financial.html'
data = io.open(f, encoding='utf-8').read()
old = '<script src="./js/settings.js"></script>'
new = '<script src="./js/financial.js"></script>'
assert old in data
data = data.replace(old, new)
io.open(f, 'w', encoding='utf-8').write(data)
print('Financial.html switched')
