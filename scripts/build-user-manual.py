#!/usr/bin/env python3
"""Build the operator documentation from Markdown without a production dependency.

Use the bundled Codex Python runtime; see docs/user-manual/BUILD.md.
"""
import argparse
import hashlib
import html
import json
import re
from pathlib import Path

from PIL import Image as PILImage, ImageDraw, ImageFont
from docx import Document
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_CELL_VERTICAL_ALIGNMENT
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor
from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (BaseDocTemplate, Frame, PageTemplate, Paragraph,
    Spacer, PageBreak, Table, TableStyle, Image, Preformatted, KeepTogether)
from reportlab.platypus.tableofcontents import TableOfContents

ROOT = Path(__file__).resolve().parents[1]
MANUAL = ROOT / "docs/user-manual"
BLUE = "153B67"
FONT_DIR = Path("C:/Windows/Fonts")


def font_path(bold=False):
    candidates = [FONT_DIR / ("arialbd.ttf" if bold else "arial.ttf"),
                  Path("/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf" if bold else
                       "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf")]
    return next((p for p in candidates if p.exists()), None)


def plain(text):
    text = text.replace("✓", "Yes")
    text = re.sub(r"!\[([^]]*)\]\([^)]+\)", r"\1", text)
    text = re.sub(r"\[([^]]+)\]\([^)]+\)", r"\1", text)
    return re.sub(r"(\*\*|__|`)", "", text).strip()


def inline(text):
    text = text.replace("✓", "Yes")
    text = html.escape(text)
    text = re.sub(r"`([^`]+)`", r'<font face="Body">\1</font>', text)
    text = re.sub(r"\*\*([^*]+)\*\*", r"<b>\1</b>", text)
    # Relative source links stay readable; external and local heading links are clickable.
    def link(m):
        label, target = m.groups()
        if target.startswith(("https://", "http://")):
            return '<a href="%s" color="#153B67">%s</a>' % (target, label)
        return label
    return re.sub(r"\[([^]]+)\]\(([^)]+)\)", link, text)


def blocks(path):
    """Small deterministic Markdown reader for the sources used by this manual."""
    lines = path.read_text(encoding="utf-8-sig").splitlines()
    out, i = [], 0
    while i < len(lines):
        line = lines[i].strip()
        i += 1
        if not line or line in ("---", "***") or line.startswith("<!--"):
            continue
        if line.startswith("```"):
            code = []
            while i < len(lines) and not lines[i].strip().startswith("```"):
                code.append(lines[i]); i += 1
            i += 1
            out.append(("code", "\n".join(code))); continue
        heading = re.match(r"^(#{1,6})\s+(.+)$", line)
        if heading:
            out.append(("heading", len(heading[1]), heading[2])); continue
        image = re.match(r"^!\[([^]]*)\]\(([^)]+)\)$", line)
        if image:
            target = (path.parent / image[2]).resolve()
            if not target.is_relative_to(MANUAL.resolve()) or not target.is_file():
                raise ValueError("Missing or out-of-scope documentation image: " + str(target))
            out.append(("image", image[1], target)); continue
        if line.startswith("|"):
            rows = [[v.strip() for v in line.strip("|").split("|")]]
            while i < len(lines) and lines[i].strip().startswith("|"):
                cells = [v.strip() for v in lines[i].strip().strip("|").split("|")]
                i += 1
                if all(re.fullmatch(r":?-+:?", v) for v in cells):
                    continue
                rows.append(cells)
            width = max(map(len, rows))
            out.append(("table", [r + [""] * (width-len(r)) for r in rows])); continue
        item = re.match(r"^(\d+[.)]|[-*])\s+(.+)$", line)
        if item:
            out.append(("item", item[1], item[2])); continue
        text = [line]
        while i < len(lines) and lines[i].strip() and not re.match(
                r"^(#{1,6}\s|\||!\[|```|\d+[.)]\s|[-*]\s|---$)", lines[i].strip()):
            text.append(lines[i].strip()); i += 1
        out.append(("paragraph", " ".join(text).lstrip("> ")))
    return out


def export_blocks(source):
    output=[]
    contents=False
    for block in blocks(source):
        if block[0]=="heading":
            if block[1]==1: continue  # The formal cover already supplies the source titles.
            if block[1]<=2: contents=plain(block[2]).lower() in ("contents","table of contents")
        if not contents: output.append(block)
    return output


def column_weights(rows):
    # A label column should not crowd a long explanatory column.
    weights = [min(65, max(12, max(len(plain(row[c])) for row in rows))) ** .6
               for c in range(len(rows[0]))]
    return [w / sum(weights) for w in weights]


def print_detail(path):
    # Actual pixels only: preserve the full source figure and enlarge selected form fields.
    regions = {
        "07-form.png": ((300,290,1210,744), "Tenant personal-information fields"),
        "11-draft-form.png": ((310,190,1220,744), "Assignment selection and lease-date fields"),
        "17-payment-form.png": ((1080,520,1840,710), "Payment amount and Treasury reference fields"),
        "27-assignment-form.png": ((310,190,1220,744), "Assignment input fields"),
        "50-rental-preview.jpg": ((310,0,1330,750), "Approved category rental computation"),
    }
    if path.name not in regions or path.parent.name == "workflows": return None
    rectangle, label = regions[path.name]
    with PILImage.open(path) as original:
        if original.width < rectangle[2] or original.height < rectangle[3]: return None
        folder=MANUAL/"qa"/"details"; folder.mkdir(parents=True,exist_ok=True)
        target=folder/(path.stem+"-detail.png")
        original.crop(rectangle).save(target,dpi=(180,180))
    return target, label + " enlarged from the actual screen above"


class ManualPDF(BaseDocTemplate):
    def __init__(self, output, title, metadata):
        super().__init__(str(output), pagesize=A4, leftMargin=45, rightMargin=45,
                         topMargin=48, bottomMargin=46, title=title,
                         author="MarketHub documentation", pageCompression=1)
        self.document_title = title
        self.metadata = metadata
        frame = Frame(self.leftMargin, self.bottomMargin, self.width, self.height,
                      leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)
        self.addPageTemplates(PageTemplate(id="main", frames=frame, onPage=self.chrome))

    def chrome(self, canvas, doc):
        canvas.saveState()
        canvas.setFont("Body", 8)
        canvas.setFillColor(colors.HexColor("#4C5968"))
        if doc.page > 1:
            canvas.drawString(45, A4[1]-29, "MarketHub  |  Operational user documentation")
        canvas.drawString(45, 27, "City Government of San Pedro, Laguna  |  " + self.metadata)
        canvas.drawRightString(A4[0]-45, 27, str(doc.page))
        canvas.restoreState()

    def afterFlowable(self, flowable):
        if isinstance(flowable, Paragraph) and hasattr(flowable, "toc_entry"):
            level, text, key = flowable.toc_entry
            self.canv.bookmarkPage(key)
            self.canv.addOutlineEntry(text, key, level=level, closed=False)
            if level == 0: self.notify("TOCEntry", (level, text, self.page, key))


def pdf_styles():
    styles = getSampleStyleSheet()
    styles.add(ParagraphStyle(name="Text", fontName="Body", fontSize=10.3, leading=14.7,
                             spaceAfter=7, textColor=colors.HexColor("#172232")))
    styles.add(ParagraphStyle(name="CoverTitle", fontName="BodyBold", fontSize=27,
                             leading=34, spaceAfter=20))
    styles.add(ParagraphStyle(name="Meta", fontName="Body", fontSize=11.5,
                             leading=18, spaceAfter=15))
    for level, size in ((1,20),(2,17),(3,12.4),(4,11.3),(5,10.8),(6,10.5)):
        styles.add(ParagraphStyle(name="H%d" % level, fontName="BodyBold", fontSize=size,
                                 leading=size*1.27, spaceBefore=14, spaceAfter=8,
                                 keepWithNext=True, textColor=colors.HexColor("#"+BLUE)))
    styles.add(ParagraphStyle(name="Cell", parent=styles["Text"], fontSize=8.5,
                             leading=11.5, spaceAfter=0))
    styles.add(ParagraphStyle(name="CellHead", parent=styles["Cell"], fontName="BodyBold",
                             textColor=colors.white))
    styles.add(ParagraphStyle(name="CaptionText", parent=styles["Text"], fontSize=8.8,
                             leading=12, textColor=colors.HexColor("#4C5968"), spaceAfter=12))
    styles.add(ParagraphStyle(name="ListText", parent=styles["Text"], leftIndent=15,
                             firstLineIndent=-15))
    styles.add(ParagraphStyle(name="SourceCode", fontName="Body", fontSize=8, leading=11,
                             spaceAfter=10))
    return styles


def make_pdf(source, output, title, version, date, revision):
    styles = pdf_styles()
    doc = ManualPDF(output, title, "v%s  |  %s" % (version, date))
    story = [Spacer(1, 78), Paragraph("CITY GOVERNMENT OF<br/>SAN PEDRO, LAGUNA", styles["Meta"]),
             Spacer(1, 20), Paragraph("Market Stall Management<br/>and Monitoring System", styles["CoverTitle"]),
             Paragraph(title, styles["H1"]), Spacer(1, 26),
             Paragraph("Version %s<br/>Prepared %s<br/>Source revision %s" %
                       (version, date, revision), styles["Meta"]),
             Spacer(1, 25), Paragraph("Operational reference and staff training guide", styles["Meta"]),
             PageBreak(), Paragraph("Contents", styles["H1"])]
    toc = TableOfContents()
    toc.levelStyles = [ParagraphStyle(name="TOC0", fontName="BodyBold", fontSize=10,
                                     leading=14, spaceBefore=7),
                       ParagraphStyle(name="TOC1", fontName="Body", fontSize=9,
                                      leading=12, leftIndent=14, spaceBefore=3)]
    story += [toc, PageBreak()]
    parsed = export_blocks(source)
    first_title = True
    for index, block in enumerate(parsed):
        kind = block[0]
        if kind == "heading":
            _, level, text = block
            if level == 1 and first_title:
                first_title = False; continue
            if level == 2 and re.match(r"Chapter\s+\d+", text, re.I) and story:
                if not isinstance(story[-1], PageBreak): story.append(PageBreak())
            p = Paragraph(inline(text), styles["H%d" % min(level, 6)])
            # The top outline level may not be skipped, so chapters are level zero.
            if level <= 3:
                p.toc_entry = (0 if level <= 2 else 1, plain(text), "section%d" % index)
            story.append(p)
        elif kind in ("paragraph", "item"):
            text = block[1] if kind == "paragraph" else block[1] + " " + block[2]
            style = "CaptionText" if re.match(r"^(Figure|Workflow)\s+\d", plain(text)) else (
                "ListText" if kind == "item" else "Text")
            story.append(Paragraph(inline(text), styles[style]))
        elif kind == "table":
            rows = block[1]
            rendered = [[Paragraph(inline(value), styles["CellHead" if n == 0 else "Cell"])
                         for value in row] for n, row in enumerate(rows)]
            table = Table(rendered, colWidths=[w*doc.width for w in column_weights(rows)],
                          repeatRows=1, hAlign="LEFT")
            table.setStyle(TableStyle([("BACKGROUND", (0,0),(-1,0),colors.HexColor("#"+BLUE)),
                ("ROWBACKGROUNDS",(0,1),(-1,-1),[colors.white,colors.HexColor("#F1F5F9")]),
                ("GRID",(0,0),(-1,-1),.45,colors.HexColor("#D9D9D9")),
                ("VALIGN",(0,0),(-1,-1),"MIDDLE"), ("LEFTPADDING",(0,0),(-1,-1),7),
                ("RIGHTPADDING",(0,0),(-1,-1),7), ("TOPPADDING",(0,0),(-1,-1),7),
                ("BOTTOMPADDING",(0,0),(-1,-1),7)]))
            story += [table, Spacer(1,10)]
        elif kind == "image":
            _, alt, path = block
            with PILImage.open(path) as img: width, height = img.size
            max_height = 535 if path.parent.name == "workflows" else 360
            ratio = min(doc.width/width, max_height/height)
            image = Image(str(path), width*ratio, height*ratio)
            caption = Paragraph(inline(alt), styles["CaptionText"])
            story.append(KeepTogether([image, Spacer(1,5),caption]))
            detail=print_detail(path)
            if detail:
                target,label=detail
                with PILImage.open(target) as img: dw,dh=img.size
                ratio=min(doc.width/dw,310/dh)
                story.append(KeepTogether([Image(str(target),dw*ratio,dh*ratio),Spacer(1,5),
                                           Paragraph(label,styles["CaptionText"])]))
        elif kind == "code":
            # Wrap at whitespace so command examples cannot cross a page margin.
            import textwrap
            wrapped = "\n".join(textwrap.fill(v, width=92, replace_whitespace=False)
                                for v in block[1].splitlines())
            story.append(Preformatted(wrapped, styles["SourceCode"]))
    doc.multiBuild(story)


def docx_inline(paragraph, text):
    for piece in re.split(r"(\*\*[^*]+\*\*|`[^`]+`)", text):
        if not piece: continue
        run = paragraph.add_run(plain(piece))
        if piece.startswith("**"): run.bold = True


def add_field(paragraph, name):
    field = OxmlElement("w:fldSimple"); field.set(qn("w:instr"), name)
    paragraph._p.append(field)


def make_docx(source, output, title, version, date, revision):
    doc = Document()
    section = doc.sections[0]
    section.page_width, section.page_height = Inches(8.2677), Inches(11.6929)
    section.left_margin = section.right_margin = Inches(.65)
    section.top_margin = Inches(.7); section.bottom_margin = Inches(.65)
    section.header_distance = section.footer_distance = Inches(.28)
    normal = doc.styles["Normal"]
    normal.font.name = "Arial"; normal.font.size = Pt(10.5)
    normal.paragraph_format.space_after = Pt(7)
    normal.paragraph_format.line_spacing = 1.12
    for n in range(1,10):
        style = doc.styles["Heading %d" % n]
        style.font.name = "Arial"; style.font.color.rgb = RGBColor.from_string(BLUE)
        style.font.size = Pt({1:20,2:17,3:12.5}.get(n,11))
    doc.styles["Title"].font.name = "Arial"
    doc.styles["Title"].font.color.rgb = RGBColor(0,0,0)
    doc.styles["Title"].font.size = Pt(28)
    doc.add_paragraph("CITY GOVERNMENT OF SAN PEDRO, LAGUNA")
    doc.add_paragraph("")
    doc.add_paragraph("Market Stall Management and Monitoring System", "Title")
    doc.add_paragraph(title, "Subtitle")
    doc.add_paragraph("Version %s\nPrepared %s\nSource revision %s" % (version,date,revision))
    doc.add_paragraph("Operational reference and staff training guide")
    doc.add_page_break()
    doc.add_heading("Contents", 1)
    parsed = export_blocks(source)
    headings = [(i,b) for i,b in enumerate(parsed) if b[0] == "heading" and b[1] == 2]
    for i,b in headings:
        p = doc.add_paragraph()
        p.paragraph_format.space_after = Pt(3)
        if b[1] == 3: p.paragraph_format.left_indent = Inches(.15)
        hyperlink = OxmlElement("w:hyperlink"); hyperlink.set(qn("w:anchor"), "section%d" % i)
        run = OxmlElement("w:r"); text = OxmlElement("w:t"); text.text = plain(b[2])
        run.append(text); hyperlink.append(run); p._p.append(hyperlink)
    doc.add_page_break()
    first_title = True
    for i,b in enumerate(parsed):
        kind = b[0]
        if kind == "heading":
            _,level,text = b
            if level == 1 and first_title: first_title = False; continue
            p = doc.add_heading(plain(text), min(level,9))
            if level == 2 and re.match(r"Chapter\s+\d+", text, re.I):
                p.paragraph_format.page_break_before = True
            start = OxmlElement("w:bookmarkStart"); start.set(qn("w:id"),str(i+1))
            start.set(qn("w:name"),"section%d" % i)
            end = OxmlElement("w:bookmarkEnd"); end.set(qn("w:id"),str(i+1))
            p._p.insert(0,start); p._p.append(end)
        elif kind in ("paragraph","item"):
            text = b[1] if kind == "paragraph" else b[1] + " " + b[2]
            p = doc.add_paragraph(); docx_inline(p,text)
            if kind == "item":
                p.paragraph_format.left_indent = Inches(.15)
                p.paragraph_format.first_line_indent = Inches(-.15)
            if re.match(r"^(Figure|Workflow)\s+\d", plain(text)):
                p.style = doc.styles["Caption"]
        elif kind == "table":
            rows = b[1]
            table = doc.add_table(rows=1, cols=len(rows[0])); table.alignment = WD_TABLE_ALIGNMENT.CENTER
            table.autofit = False
            widths = column_weights(rows)
            for c,w in zip(table.columns,widths): c.width = Inches(6.96*w)
            for n,row in enumerate(rows):
                cells = table.rows[0].cells if n == 0 else table.add_row().cells
                for j,value in enumerate(row):
                    cell = cells[j]; cell.width = Inches(6.96*widths[j])
                    cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
                    docx_inline(cell.paragraphs[0],value)
                    for run in cell.paragraphs[0].runs:
                        run.font.size = Pt(8.5)
                        if n == 0: run.bold = True; run.font.color.rgb = RGBColor(255,255,255)
                    props = cell._tc.get_or_add_tcPr()
                    shade = OxmlElement("w:shd"); shade.set(qn("w:fill"), BLUE if n==0 else ("F1F5F9" if n%2 else "FFFFFF")); props.append(shade)
                    borders = OxmlElement("w:tcBorders")
                    for edge in ("top","left","bottom","right"):
                        node=OxmlElement("w:"+edge); node.set(qn("w:val"),"single")
                        node.set(qn("w:sz"),"4"); node.set(qn("w:color"),"D9D9D9"); borders.append(node)
                    props.append(borders)
                    margins = OxmlElement("w:tcMar")
                    for edge in ("top","left","bottom","right"):
                        node=OxmlElement("w:"+edge); node.set(qn("w:w"),"90"); node.set(qn("w:type"),"dxa"); margins.append(node)
                    props.append(margins)
                if n==0:
                    repeat=OxmlElement("w:tblHeader"); table.rows[0]._tr.get_or_add_trPr().append(repeat)
            doc.add_paragraph("").paragraph_format.space_after = Pt(2)
        elif kind == "image":
            with PILImage.open(b[2]) as img: width,height = img.size
            max_height = 7.1 if b[2].parent.name == "workflows" else 4.8
            scale = min(6.96/width,max_height/height)
            p = doc.add_paragraph(); p.paragraph_format.keep_with_next = True
            p.add_run().add_picture(str(b[2]),width=Inches(width*scale),height=Inches(height*scale))
            doc.add_paragraph(b[1],"Caption")
            detail=print_detail(b[2])
            if detail:
                target,label=detail
                with PILImage.open(target) as img: dw,dh=img.size
                ratio=min(6.96/dw,4.3/dh)
                p=doc.add_paragraph(); p.paragraph_format.keep_with_next=True
                p.add_run().add_picture(str(target),width=Inches(dw*ratio),height=Inches(dh*ratio))
                doc.add_paragraph(label,"Caption")
        elif kind == "code":
            p=doc.add_paragraph(b[1]); p.paragraph_format.space_after = Pt(10)
            for run in p.runs: run.font.name="Arial"; run.font.size=Pt(8)
    section.header.paragraphs[0].text = "MarketHub  |  Operational user documentation"
    footer = section.footer.paragraphs[0]
    footer.add_run("City Government of San Pedro, Laguna  |  v%s  |  " % version)
    add_field(footer,"PAGE")
    for p in (section.header.paragraphs[0],footer):
        for run in p.runs: run.font.size=Pt(8)
    doc.core_properties.title = title
    doc.core_properties.subject = "Market Stall Management and Monitoring System"
    doc.core_properties.version = version
    doc.save(output)


def diagram_text(draw, text, box, size=29, bold=False, fill="#172232"):
    font=ImageFont.truetype(str(font_path(bold)),size)
    x,y,w,h=box
    lines=[]
    for paragraph in text.split("\n"):
        line=""
        for word in paragraph.split():
            test=(line+" "+word).strip()
            if draw.textlength(test,font=font)>w-24 and line:
                lines.append(line); line=word
            else: line=test
        lines.append(line)
    line_h=size+9
    if len(lines)*line_h>h-12: raise ValueError("Diagram text overflow: "+text)
    yy=y+(h-len(lines)*line_h)/2
    for line in lines:
        draw.text((x+(w-draw.textlength(line,font=font))/2,yy),line,font=font,fill=fill)
        yy+=line_h


def make_diagrams():
    folder=MANUAL/"workflows"; folder.mkdir(parents=True,exist_ok=True)
    flows=json.loads((folder/"flows.json").read_text(encoding="utf-8"))
    for flow in flows:
        steps=flow["steps"]; height=240+len(steps)*180+125
        image=PILImage.new("RGB",(1600,height),"white"); d=ImageDraw.Draw(image)
        d.rectangle((0,0,1600,132),fill="#153B67")
        diagram_text(d,flow["title"],(36,15,1528,98),40,True,"white")
        diagram_text(d,"RESPONSIBILITY",(25,145,300,56),24,True)
        diagram_text(d,"ACTION AND CONTROL",(335,145,840,56),24,True)
        diagram_text(d,"IF VALIDATION FAILS",(1185,145,390,56),24,True)
        mermaid=["flowchart TD"]
        for index,step in enumerate(steps):
            actor,text=step[:2]; fail=step[2] if len(step)>2 else ""
            y=220+index*180
            d.rounded_rectangle((35,y+8,315,y+139),radius=16,fill="#E7EFF8")
            diagram_text(d,actor,(43,y+10,264,125),28,True)
            d.rounded_rectangle((360,y,1150,y+146),radius=17,fill="#F2F6FA",outline="#2C5B8E",width=3)
            diagram_text(d,text,(375,y+5,760,135),30)
            if fail:
                d.line((1150,y+73,1200,y+73),fill="#7C4A1E",width=4)
                d.polygon([(1200,y+73),(1185,y+65),(1185,y+81)],fill="#7C4A1E")
                d.rounded_rectangle((1200,y+4,1570,y+143),radius=12,fill="#FFF5E7",outline="#B78148",width=2)
                diagram_text(d,fail,(1210,y+8,350,130),25)
            if index<len(steps)-1:
                d.line((755,y+147,755,y+175),fill="#2C5B8E",width=4)
                d.polygon([(755,y+179),(747,y+166),(763,y+166)],fill="#2C5B8E")
            label=(actor+": "+text).replace('"',"'")
            mermaid.append('    N%d["%s"]' % (index,label))
            if index: mermaid.append("    N%d --> N%d" % (index-1,index))
            if fail:
                mermaid.append('    N%d -->|Validation fails| F%d["%s"]' % (index,index,fail.replace('"',"'")))
        diagram_text(d,flow["note"],(40,height-125,1520,92),24)
        image.save(folder/(flow["file"]+".png"),dpi=(240,240),optimize=True)
        (folder/(flow["file"]+".mmd")).write_text("\n".join(mermaid)+"\n",encoding="utf-8")
    return len(flows)


def qa_pdf(path):
    import pdfplumber
    from pypdf import PdfReader
    reader=PdfReader(path)
    issues=[]
    with pdfplumber.open(path) as pdf:
        for n,page in enumerate(pdf.pages,1):
            if not page.extract_text(): issues.append("empty page %d" % n)
            outside=[c for c in page.chars if c.get("text","").strip() and
                     (c["x0"]<35 or c["x1"]>page.width-34 or c["top"]<15 or c["bottom"]>page.height-18)]
            if outside: issues.append("text outside print bounds page %d" % n)
    return {"pages":len(reader.pages),"outline_entries":len(reader.outline),
            "annotations":sum(len(p.get("/Annots",[])) for p in reader.pages),"issues":issues}


def render_pdf(path):
    import pypdfium2 as pdfium
    folder=MANUAL/"qa"/path.stem; folder.mkdir(parents=True,exist_ok=True)
    pdf=pdfium.PdfDocument(str(path)); thumbs=[]
    for n in range(len(pdf)):
        page=pdf[n]; bitmap=page.render(scale=1.5); img=bitmap.to_pil().convert("RGB")
        img.save(folder/("page-%03d.png" % (n+1)))
        thumb=img.copy(); thumb.thumbnail((298,422))
        canvas=PILImage.new("RGB",(318,454),"#dbe2e9")
        canvas.paste(thumb,((318-thumb.width)//2,9))
        ImageDraw.Draw(canvas).text((12,433),"Page %d" % (n+1),fill="black")
        thumbs.append(canvas)
        bitmap.close(); page.close()
    pdf.close()
    for start in range(0,len(thumbs),12):
        batch=thumbs[start:start+12]; sheet=PILImage.new("RGB",(1272,454*((len(batch)+3)//4)),"white")
        for j,img in enumerate(batch): sheet.paste(img,((j%4)*318,(j//4)*454))
        sheet.save(folder/("contact-%02d.png" % (start//12+1)))


def self_check():
    # Only disposable source/output files are used; no application DB is opened.
    folder=MANUAL/"qa"/"builder-self-check"
    folder.mkdir(parents=True,exist_ok=True)
    try:
        source=folder/"fixture.md"
        source.write_text("# Fixture\n\n## Chapter 1 Test\n\nText **bold** and `code`.\n\n"
                          "1. A numbered step\n\n| Field | Rule |\n| --- | --- |\n| ID | Required |\n\n"
                          "### Validation\n\n```\ncommand example\n```\n",encoding="utf-8")
        parsed=blocks(source)
        assert [b[0] for b in parsed]==["heading","heading","paragraph","item","table","heading","code"]
        make_pdf(source,folder/"fixture.pdf","Fixture", "1.0","2026-11-17","self-check")
        make_docx(source,folder/"fixture.docx","Fixture","1.0","2026-11-17","self-check")
        result=qa_pdf(folder/"fixture.pdf")
        assert result["pages"]>=3 and not result["issues"],result
        from zipfile import ZipFile
        with ZipFile(folder/"fixture.docx") as archive:
            xml=archive.read("word/document.xml")
            assert b"bookmarkStart" in xml and b"hyperlink" in xml and b"Field" in xml
        source.write_text("![Missing](missing.png)",encoding="utf-8")
        try: blocks(source)
        except ValueError: pass
        else: raise AssertionError("Missing image was silently accepted")
    finally:
        for filename in ("fixture.md","fixture.pdf","fixture.docx"):
            (folder/filename).unlink(missing_ok=True)
        folder.rmdir()
    print("Self-check passed: Markdown, DOCX bookmarks, PDF layout and missing-image guard")


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--diagrams-only",action="store_true")
    parser.add_argument("--self-check",action="store_true")
    parser.add_argument("--version",default="1.0")
    parser.add_argument("--date",default="2026-11-17")
    parser.add_argument("--source-revision")
    args=parser.parse_args()
    for name,bold in (("Body",False),("BodyBold",True)):
        path=font_path(bold)
        if not path: raise SystemExit("Arial or DejaVu Sans font is required")
        pdfmetrics.registerFont(TTFont(name,str(path)))
    pdfmetrics.registerFontFamily("Body",normal="Body",bold="BodyBold",italic="Body",boldItalic="BodyBold")
    MANUAL.mkdir(parents=True,exist_ok=True)
    if args.self_check: self_check(); return
    count=make_diagrams()
    if args.diagrams_only:
        print("Generated %d editable Mermaid and print PNG workflow diagrams" % count); return
    export=MANUAL/"exports"; export.mkdir(exist_ok=True)
    source_files=[ROOT/"routes/web.php",ROOT/"database/seeders/RoleSeeder.php",ROOT/"config/navigation.php"]
    digest=hashlib.sha256(b"".join(p.read_bytes() for p in source_files)).hexdigest()[:12]
    revision=args.source_revision or "source-sha256-"+digest
    jobs=[("USER_MANUAL.md","Market_Stall_User_Manual","Complete User Manual"),
          ("QUICK_REFERENCE_GUIDE.md","Quick_Reference_Guide","Quick Reference Guide")]
    manifest={"version":args.version,"prepared":args.date,"source_revision":revision,
              "workflow_diagrams":count,"pdf":{},"docx_visual_verification":"not performed by this builder; use render_docx.py"}
    for filename,stem,title in jobs:
        source=MANUAL/filename; output=export/(stem+".pdf")
        make_pdf(source,output,title,args.version,args.date,revision)
        result=qa_pdf(output)
        if result["issues"]: raise ValueError(result)
        render_pdf(output)
        manifest["pdf"][output.name]=result
        if filename=="USER_MANUAL.md":
            make_docx(source,export/(stem+".docx"),title,args.version,args.date,revision)
        manifest[filename+"_sha256"]=hashlib.sha256(source.read_bytes()).hexdigest()
    (export/"build-manifest.json").write_text(json.dumps(manifest,indent=2)+"\n",encoding="utf-8")
    print(json.dumps(manifest,indent=2))


if __name__=="__main__": main()
