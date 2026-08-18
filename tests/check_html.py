from html.parser import HTMLParser
from pathlib import Path
import sys

class Checker(HTMLParser):
    def __init__(self):
        super().__init__()
        self.inline_handlers = []
        self.ids = set()
        self.duplicate_ids = set()

    def handle_starttag(self, tag, attrs):
        attributes = dict(attrs)
        for name in attributes:
            if name.lower().startswith("on"):
                self.inline_handlers.append(name)
        identifier = attributes.get("id")
        if identifier:
            if identifier in self.ids:
                self.duplicate_ids.add(identifier)
            self.ids.add(identifier)

errors = []
for filename in ("index.html", "stats.html"):
    text = Path(filename).read_text(encoding="utf-8")
    checker = Checker()
    checker.feed(text)
    if checker.inline_handlers:
        errors.append(f"{filename}: gestionnaires inline interdits")
    if checker.duplicate_ids:
        errors.append(f"{filename}: identifiants dupliqués {checker.duplicate_ids}")
    if 'class="app-footer"' not in text:
        errors.append(f"{filename}: pied de page absent")
    if "scripts.js?v=" not in text:
        errors.append(f"{filename}: version du script absente")

index = Path("index.html").read_text(encoding="utf-8")
if index.count('data-feedback-type=') != 8:
    errors.append("index.html: huit boutons de feedback sont attendus")
if '<button class="smiley"' not in index:
    errors.append("index.html: les smileys doivent être des boutons")

if errors:
    print("\n".join(errors), file=sys.stderr)
    raise SystemExit(1)

print("Structure HTML vérifiée.")
