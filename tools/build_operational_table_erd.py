import json, math, xml.etree.ElementTree as E
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
D=ROOT/'docs'
schema={k.split('.')[-1]:v for k,v in json.loads((D/'erd-operasional-schema.json').read_text()).items()}
for v in schema.values():
    for k in ['columns','indexes','foreign_keys']:
        if isinstance(v[k],dict): v[k]=list(v[k].values())
pages=[('01 Operasional inti',['program_bimbel','cabang','leads','followup_riwayat','sekolah','users','pendaftaran','pembayaran','sumber_leads','target_kinerja_cabang','target_kinerja_user','notifications']),('02 WhatsApp',['users','user_whatsapp_accounts','whatsapp_conversations','whatsapp_messages','cabang','leads','waha_webhook_events']),('03 API dan audit',['users','api_clients','api_idempotency_keys','crm_settings','cabang','activity_logs']),('04 Impor dan ekspor',['users','imports','failed_import_rows','exports'])]
mx=E.Element('mxfile',host='app.diagrams.net',type='device')
report=['# ERD operasional — struktur migration project','', 'Sumber: seluruh migration project hingga 2026-09-18, direkonstruksi pada SQLite di memori pada 2026-09-22. MySQL lokal tidak dapat dihubungi; kesesuaian dengan database live belum diverifikasi. Tidak ada data operasional atau kredensial yang diekspor.', '', 'Semua kolom ditampilkan, termasuk timestamp. Tipe fisik MySQL tidak ditampilkan karena metadata rekonstruksi menggunakan SQLite. PK = primary key; FK = foreign key; UQ = unique satu kolom; NULL = boleh kosong; POLY = referensi polimorfik tanpa constraint FK. Unique gabungan dicatat di bawah.', '', 'Garis solid: FK aktual, arah anak ke induk. Garis putus-putus: relasi notifications ke users melalui notifiable_type + notifiable_id. Ujung cakar bebek menunjukkan 0..N; lingkaran dan garis menunjukkan 0..1; garis ganda menunjukkan tepat 1.', '', 'Cakupan: 23 tabel operasional dalam empat halaman. Tabel framework (cache, antrean, sessions, password reset) tidak dimasukkan. waha_webhook_events tidak memiliki FK sehingga ditampilkan tanpa garis.', '']
colors={'leads':'#db006c','followup_riwayat':'#db006c','pendaftaran':'#dfd3e8','pembayaran':'#dfd3e8'}
for title,names in pages:
    cols=4 if len(names)>7 else 3
    W=360; RH=25; HH=36; GX=220; GY=240
    positions={}; yy=380
    for start in range(0,len(names),cols):
        batch=names[start:start+cols]
        for j,t in enumerate(batch): positions[t]=(100+j*(W+GX),yy)
        yy+=max(HH+RH*len(schema[t]['columns']) for t in batch)+GY
    width=cols*(W+GX)+80; height=yy
    diagram=E.SubElement(mx,'diagram',name=title,id=title[:2])
    model=E.SubElement(diagram,'mxGraphModel',grid='1',gridSize='10',page='1',pageWidth=str(width),pageHeight=str(height),pageScale='1')
    root=E.SubElement(model,'root'); E.SubElement(root,'mxCell',id='0'); E.SubElement(root,'mxCell',id='1',parent='0')
    def cell(id,value,x,y,w,h,style,parent='1'):
        c=E.SubElement(root,'mxCell',id=id,value=value,style=style,vertex='1',parent=parent)
        E.SubElement(c,'mxGeometry',x=str(x),y=str(y),width=str(w),height=str(h),attrib={'as':'geometry'})
    cell('title','ERD OPERASIONAL SIVMI CRM | '+title,90,35,width-180,48,'text;align=left;fontSize=28;fontStyle=1;fontColor=#16324f;')
    cell('subtitle','Struktur migration project • seluruh kolom • PK / FK / UQ / NULL • garis putus-putus = polimorfik',90,92,width-180,30,'text;align=left;fontSize=16;fontColor=#526579;')
    cell('note','Sumber: migration hingga 18 September 2026 | MySQL live belum terverifikasi | Detail kardinalitas dan indeks: berkas README',90,129,width-180,25,'text;align=left;fontSize=13;fontColor=#526579;')
    edges=[]
    for t in names:
        for f in schema[t]['foreign_keys']:
            ft=f['foreign_table'].split('.')[-1]
            if ft in names: edges.append((t,f['columns'][0],ft,f['foreign_columns'][0],False))
    if 'notifications' in names: edges.append(('notifications','notifiable_id','users','id',True))
    # Route through gutters and horizontal space above tables; never through a table body.
    for i,(t,c,ft,fc,poly) in enumerate(edges):
        x,y=positions[t]; tx,ty=positions[ft]
        sy=y+HH+RH*next(j for j,v in enumerate(schema[t]['columns']) if v['name']==c)+RH/2
        ey=ty+HH+RH*next(j for j,v in enumerate(schema[ft]['columns']) if v['name']==fc)+RH/2
        lane=20+(i%16)*10
        sx=x+W; ex=tx+W; ax=sx+lane; bx=ex+lane
        bus=min(y,ty)-30-lane
        nullable=next(v for v in schema[t]['columns'] if v['name']==c)['nullable']
        unique=any(v['unique'] and v['columns']==[c] for v in schema[t]['indexes'])
        start='ERzeroToOne' if unique else 'ERzeroToMany'
        end='ERzeroToOne' if nullable else 'ERone'
        style=f'edgeStyle=segmentEdgeStyle;rounded=0;html=1;strokeColor=#64748b;strokeWidth=1.3;startArrow={start};endArrow={end};startSize=12;endSize=12;exitX=1;exitY=0.5;entryX=1;entryY=0.5;'
        if poly: style+='dashed=1;strokeColor=#b26b25;'
        e=E.SubElement(root,'mxCell',id=f'e{i}',style=style,edge='1',parent='1',source=t+'__'+c,target=ft+'__'+fc)
        geo=E.SubElement(e,'mxGeometry',relative='1',attrib={'as':'geometry'})
        arr=E.SubElement(geo,'Array',attrib={'as':'points'})
        for px,py in [(ax,sy),(ax,bus),(bx,bus),(bx,ey)]: E.SubElement(arr,'mxPoint',x=str(px),y=str(py))
    report+=['## '+title,'','| Kolom anak | Kolom induk | Induk per anak | Anak per induk |','|---|---|---|---|']
    for t,c,ft,fc,poly in edges:
        nullable=next(v for v in schema[t]['columns'] if v['name']==c)['nullable']
        unique=any(v['unique'] and v['columns']==[c] for v in schema[t]['indexes'])
        report.append(f'| {t}.{c} | {ft}.{fc}'+(' (polimorfik)' if poly else '')+f' | {"0..1" if nullable else "1"} | {"0..1" if unique else "0..N"} |')
    report+=['']
    for t in names:
        x,y=positions[t]; data=schema[t]; color=colors.get(t,'#cfe5fa' if t in ['notifications','target_kinerja_user','target_kinerja_cabang'] else '#d9ead3')
        cell(t,t,x,y,W,HH+RH*len(data['columns']),f'swimlane;startSize={HH};horizontal=1;fillColor={color};swimlaneFillColor=#ffffff;strokeColor=#8795a5;fontSize=16;fontStyle=1;fontColor={"#ffffff" if color=="#db006c" else "#172b40"};collapsible=0;')
        pk={c for z in data['indexes'] if z['primary'] for c in z['columns']}
        fk={c for z in data['foreign_keys'] for c in z['columns']}
        uq={z['columns'][0] for z in data['indexes'] if z['unique'] and not z['primary'] and len(z['columns'])==1}
        for j,v in enumerate(data['columns']):
            c=v['name']; key='/'.join(k for k,yes in [('PK',c in pk),('FK',c in fk),('UQ',c in uq),('POLY',c in ['notifiable_id','subject_id'])] if yes)
            cell(t+'__'+c,'',0,HH+j*RH,W,RH,'group;',t)
            parent=t+'__'+c
            cell(parent+'_key',key,0,0,68,RH,'text;align=center;verticalAlign=middle;fontSize=10;fontStyle=1;strokeColor=#d7dee7;fillColor=#f8fafc;',parent)
            cell(parent+'_name',c,68,0,242,RH,f'text;align=left;spacingLeft=8;verticalAlign=middle;fontSize=13;strokeColor=#d7dee7;fillColor=#ffffff;fontStyle={5 if c in pk else 0};',parent)
            cell(parent+'_null','NULL' if v['nullable'] else '',310,0,50,RH,'text;align=center;verticalAlign=middle;fontSize=9;fontColor=#7c8898;strokeColor=#d7dee7;fillColor=#ffffff;',parent)
        for z in data['indexes']:
            if z['unique'] and len(z['columns'])>1: report.append('Unique gabungan `'+t+'`: `'+', '.join(z['columns'])+'`.')
    report+=['']
E.indent(mx)
path=D/'ERD-OPERASIONAL-TABEL-SIVMI-2026-09-22.drawio'
E.ElementTree(mx).write(path,encoding='utf-8',xml_declaration=True)
(D/'ERD-OPERASIONAL-TABEL-SIVMI-2026-09-22.md').write_text('\n'.join(report),encoding='utf-8')
# Validate every table field and every FK endpoint in each page against the metadata.
for d,(_,names) in zip(mx.findall('diagram'),pages):
    root=d.find('mxGraphModel/root'); ids={c.attrib['id'] for c in root}
    for t in names:
        assert all(t+'__'+v['name'] in ids for v in schema[t]['columns'])
    for c in root:
        if c.get('edge'): assert c.get('source') in ids and c.get('target') in ids
print(f'Validated {len(pages)} pages, {len(set(t for _,ns in pages for t in ns))} distinct tables; {path}')
