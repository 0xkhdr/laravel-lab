from pathlib import Path

p = Path('/workspace/packages/catalog/src/CatalogServiceProvider.php')
s = p.read_text()
start = s.index('/**')
end = s.index('class CatalogServiceProvider')
s = s[:start] + '/** Runtime configuration and default repository/SKU bindings only. */\n' + s[end:]
p.write_text(s)
p = Path('/workspace/packages/catalog/src/Scopes/AccessibleByUserScope.php')
s = p.read_text()
start = s.index('/**')
end = s.index('class AccessibleByUserScope')
s = s[:start] + '/** Optional global Product scope sharing the fail-closed local access filter. */\n' + s[end:]
p.write_text(s)
