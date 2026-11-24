"""Cross-check editable documentation against source and captured permission evidence."""
import json,re,hashlib
from pathlib import Path
from zipfile import ZipFile
root=Path(__file__).resolve().parents[1]
base=root/'docs/user-manual';text=(base/'USER_MANUAL.md').read_text(encoding='utf-8')
roles=json.loads((base/'verification/role-permissions.json').read_text(encoding='utf-8'))
order=['Super Administrator','Market Administrator','Market Staff','Authorized Collection Officer','Treasury/Accounting Officer','LGU Management Viewer']
errors=[];count=0
for line in text.splitlines():
    cells=[x.strip() for x in line.split('|')[1:-1]]
    if len(cells)==7 and re.fullmatch(r'[a-z_]+\.[a-z_.]+',cells[0]):
        count+=1
        for role,value in zip(order,cells[1:]):
            expected=cells[0] in roles[role]
            if (value=='✓')!=expected:errors.append('Permission mismatch: '+cells[0]+' / '+role)
all_permissions=set(p for perms in roles.values() for p in perms)
if count!=len(all_permissions):errors.append('Permission row count differs from actual seeded permissions')
chapters=re.findall(r'^## Chapter (\d+) ',text,re.M)
if chapters!=[str(n) for n in range(1,15)]:errors.append('Chapter sequence is incomplete')
images=re.findall(r'!\[[^]]*\]\(([^)]+)\)',text)
for image in images:
    if not(base/image).is_file():errors.append('Missing image '+image)
source=(root/'app/Services/Reports.php').read_text(encoding='utf-8').split('public static function query',1)[0]
reports=re.findall(r'"[a-z-]+" => \["([^"\n]+)", "[a-z.]+"\]',source)
for label in reports:
    if label not in text:errors.append('Missing report '+label)
result={'chapters':len(chapters),'permission_rows':count,'roles':len(order),'reports':len(reports),'unique_manual_figures':len(set(images)),'actual_screenshots':sum(1 for path in (base/'screenshots').glob('*/*') if path.suffix.lower() in ['.png','.jpg','.jpeg']),'workflow_diagrams':len(list((base/'workflows').glob('*.mmd'))),'errors':errors}
(base/'verification/source-cross-check.json').write_text(json.dumps(result,indent=2)+'\n',encoding='utf-8')
print(json.dumps(result,indent=2))
raise SystemExit(bool(errors))
