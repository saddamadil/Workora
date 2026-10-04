#!/usr/bin/env python3
"""List every fixed English phrase the interface shows, so it can be translated.

Reads Blade views (text between tags, plus title/placeholder/aria-label/alt/sub attributes) and the
flash messages set in controllers. Phrases with {{ }} values inside them are skipped: only whole,
fixed phrases are translated, which keeps user data and numbers safe.
Usage: scripts/extract-phrases.py > phrases-en.json
"""
import html, json, re, sys
from pathlib import Path

root = Path(__file__).resolve().parent.parent

def strip_balanced(src: str) -> str:
    """Remove Blade directives such as @if (...) including their balanced parentheses."""
    out, i, n = [], 0, len(src)
    pat = re.compile(r'@(?!@)(?:if|elseif|else|endif|foreach|endforeach|forelse|empty|endforelse|for|endfor|while|endwhile|can|cannot|canany|endcan|endcannot|unless|endunless|isset|endisset|auth|endauth|guest|endguest|error|enderror|include|includeIf|includeWhen|extends|section|endsection|yield|push|endpush|stack|php|endphp|selected|checked|disabled|readonly|required|class|json|csrf|method|props|aware|use|vite|livewire|production|env|endenv|once|endonce|break|continue|switch|case|endswitch|default|lang|js)\b')
    while i < n:
        m = pat.search(src, i)
        if not m:
            out.append(src[i:]); break
        out.append(src[i:m.start()])
        j = m.end()
        k = j
        while k < n and src[k] in ' \t': k += 1
        if k < n and src[k] == '(':
            depth, k2, instr = 0, k, None
            while k2 < n:
                c = src[k2]
                if instr:
                    if c == '\\': k2 += 1
                    elif c == instr: instr = None
                elif c in '\'"': instr = c
                elif c == '(': depth += 1
                elif c == ')':
                    depth -= 1
                    if depth == 0: break
                k2 += 1
            i = k2 + 1
        else:
            i = j
        out.append('\x01')
    return ''.join(out)

def clean(src: str) -> str:
    src = re.sub(r'\{\{--.*?--\}\}', '', src, flags=re.S)
    src = re.sub(r'@php\b.*?@endphp', '\x01', src, flags=re.S)
    src = re.sub(r'<script\b.*?</script>', '\x01', src, flags=re.S | re.I)
    src = re.sub(r'<style\b.*?</style>', '\x01', src, flags=re.S | re.I)
    src = re.sub(r'\{!!.*?!!\}', '\x00', src, flags=re.S)
    src = re.sub(r'\{\{.*?\}\}', '\x00', src, flags=re.S)
    src = strip_balanced(src)
    return src

phrases = set()
def add(s: str):
    s = html.unescape(re.sub(r'\s+', ' ', s)).strip()
    if '\x00' in s or '\x01' in s or '@' in s: return
    if len(s) < 2 or not re.search(r'[A-Za-z]{2}', s): return
    if re.fullmatch(r'[\w./:#%+-]+', s) and not s[0].isupper(): return   # identifiers, urls, classes
    if re.search(r'[{}$<>=;]|=>|\(\)|->', s): return
    phrases.add(s)

for f in sorted((root / 'resources/views').rglob('*.blade.php')):
    if '/emails/' in str(f):
        pass
    src = clean(f.read_text())
    for tag in re.finditer(r'<[^>]+>', src):
        t = tag.group(0)
        for a in re.finditer(r'\s(?:title|placeholder|aria-label|alt|sub|label|heading|description|button|empty)="([^"]*)"', t):
            add(a.group(1))
    for text in re.split(r'<[^>]+>', src):
        add(text)

for f in sorted((root / 'app').rglob('*.php')):
    s = f.read_text()
    for m in re.finditer(r"->with\(\s*'(?:status|error)'\s*,\s*'((?:[^'\\]|\\.)*)'\s*\)", s):
        add(m.group(1).replace("\\'", "'"))

json.dump(sorted(phrases), sys.stdout, ensure_ascii=False, indent=0)
