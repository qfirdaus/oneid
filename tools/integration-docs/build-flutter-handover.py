#!/usr/bin/env python3
"""Build editable Word handover documents from local Markdown, standard library only."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from xml.sax.saxutils import escape
import re
import sys
ROOT=Path(__file__).resolve().parents[2]
DEST=ROOT/'docs/integration'
NAMES=['Serahan-Integrasi-OneID-Flutter-Staging','Borang-Maklumat-Aplikasi-Flutter-Staging']
if sys.argv[1:]:
    if any(name not in NAMES for name in sys.argv[1:]):
        raise SystemExit('Unknown document name')
    NAMES=sys.argv[1:]
W='http://schemas.openxmlformats.org/wordprocessingml/2006/main'
def para(text,style='Normal'):
    return f'<w:p><w:pPr><w:pStyle w:val="{style}"/></w:pPr><w:r><w:t xml:space="preserve">{escape(text)}</w:t></w:r></w:p>'
def table(lines):
    rows=[[s.strip() for s in l.strip().strip('|').split('|')] for l in lines]
    rows=[r for r in rows if not all(re.fullmatch(r'[:\- ]+',c) for c in r)]
    out='<w:tbl><w:tblPr><w:tblW w:w="9360" w:type="dxa"/><w:tblBorders>'+''.join(f'<w:{edge} w:val="single" w:sz="4" w:color="D7E3ED"/>' for edge in ['top','left','bottom','right','insideH','insideV'])+'</w:tblBorders><w:tblCellMar><w:top w:w="90" w:type="dxa"/><w:left w:w="110" w:type="dxa"/><w:bottom w:w="90" w:type="dxa"/><w:right w:w="110" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid><w:gridCol w:w="3600"/><w:gridCol w:w="5760"/></w:tblGrid>'
    for i,row in enumerate(rows):
        out+='<w:tr><w:trPr><w:cantSplit/>'+('<w:tblHeader/>' if i==0 else '')+'</w:trPr>'
        for j,cell in enumerate(row):
            shade='E8F2F8' if i==0 else ('F8FBFD' if i%2==0 else 'FFFFFF')
            out+=f'<w:tc><w:tcPr><w:tcW w:w="{3600 if j==0 else 5760}" w:type="dxa"/><w:shd w:fill="{shade}"/></w:tcPr>'+para(cell,'TableHeader' if i==0 else 'TableText')+'</w:tc>'
        out+='</w:tr>'
    return out+'</w:tbl>'+para('')
styles=f'''<?xml version="1.0" encoding="UTF-8"?><w:styles xmlns:w="{W}">
<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="22"/><w:lang w:val="ms-MY"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="270" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>
<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>
<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="120" w:after="220"/></w:pPr><w:rPr><w:b/><w:color w:val="123D60"/><w:sz w:val="38"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:outlineLvl w:val="0"/><w:spacing w:before="260" w:after="120"/></w:pPr><w:rPr><w:b/><w:color w:val="086B94"/><w:sz w:val="27"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="TableText"><w:name w:val="Table Text"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="40"/></w:pPr><w:rPr><w:sz w:val="20"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="TableHeader"><w:name w:val="Table Header"/><w:basedOn w:val="TableText"/><w:pPr><w:keepNext/></w:pPr><w:rPr><w:b/><w:color w:val="123D60"/></w:rPr></w:style><w:style w:type="paragraph" w:styleId="Code"><w:name w:val="Code"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="0" w:line="230"/><w:shd w:fill="F2F6FA"/></w:pPr><w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:sz w:val="18"/></w:rPr></w:style></w:styles>'''
for name in NAMES:
    lines=(DEST/(name+'.md')).read_text().splitlines();body=[];i=0
    while i<len(lines):
        line=lines[i]
        if line.startswith('```'):
            i+=1
            while i<len(lines) and not lines[i].startswith('```'):
                body.append(para(lines[i],'Code'));i+=1
            i+=1
            continue
        if line.startswith('|'):
            block=[]
            while i<len(lines) and lines[i].startswith('|'):block.append(lines[i]);i+=1
            body.append(table(block));continue
        if line.startswith('# '):body.append(para(line[2:],'Title'))
        elif line.startswith('## '):body.append(para(line[3:],'Heading1'))
        elif line.strip():body.append(para('• '+line[2:] if line.startswith('- ') else line))
        i+=1
    sect='<w:sectPr><w:footerReference w:type="default" r:id="rFooter"/><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1080" w:right="1273" w:bottom="1080" w:left="1273" w:header="500" w:footer="500"/></w:sectPr>'
    document=f'<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="{W}" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'+''.join(body)+sect+'</w:body></w:document>'
    footer=f'<w:ftr xmlns:w="{W}"><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:sz w:val="18"/><w:color w:val="708496"/></w:rPr><w:t>OneID · Flutter · STAGING   |   </w:t></w:r><w:fldSimple w:instr="PAGE"/></w:p></w:ftr>'
    with ZipFile(DEST/(name+'.docx'),'w',ZIP_DEFLATED) as z:
        z.writestr('[Content_Types].xml','''<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/></Types>''')
        z.writestr('_rels/.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rDoc" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>')
        z.writestr('word/_rels/document.xml.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rStyle" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rFooter" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/></Relationships>')
        z.writestr('word/document.xml',document);z.writestr('word/styles.xml',styles);z.writestr('word/footer1.xml',footer)
    print('Created '+name+'.docx')
